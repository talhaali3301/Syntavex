<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\WorkflowRun;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DestructiveInterceptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DestructiveInterceptSeeder::class);
        $this->user = User::query()->where('email', 'admin@syntavex.local')->firstOrFail();
    }

    private function intercept(): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->whereHas('workflowRun.workflow', fn ($query) => $query->where('slug', 'account-lifecycle-automation'))
            ->firstOrFail();
    }

    private function approvalFor(string $runKey): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->whereHas('workflowRun', fn ($query) => $query->where('run_key', $runKey))
            ->firstOrFail();
    }

    private function visit(string $uri = '/reviews'): TestResponse
    {
        return $this->actingAs($this->user)->get($uri);
    }

    private function props(string $uri = '/reviews'): array
    {
        $response = $this->visit($uri);
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    private function decide(ApprovalRequest $approval, array $payload): TestResponse
    {
        return $this->actingAs($this->user)
            ->post("/reviews/{$approval->id}/decision", $payload);
    }

    public function test_the_review_queue_requires_authentication(): void
    {
        $this->get('/reviews')->assertRedirect('/login');
        $this->post("/reviews/{$this->intercept()->id}/decision", ['decision' => 'approve'])
            ->assertRedirect('/login');
    }

    public function test_it_lists_every_approval_still_waiting_on_a_human(): void
    {
        $pending = ApprovalRequest::query()->where('status', 'pending')->pluck('id')->sort()->values();

        $this->visit()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Reviews/Index')
                ->has('queue', $pending->count())
                ->has('stats')
                ->has('constellation')
                ->where('stats.pending', $pending->count())
        );

        $this->assertSame(
            $pending->all(),
            collect($this->props()['queue'])->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_the_queue_is_ordered_by_severity_then_by_the_worst_breach(): void
    {
        $queue = $this->props()['queue'];

        $ranks = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $levels = array_map(fn (array $item): int => $ranks[$item['risk']['level']], $queue);

        $this->assertSame($levels, collect($levels)->sort()->values()->all());

        $this->assertSame($this->intercept()->workflowRun->run_key, $queue[0]['run_key']);
        $this->assertGreaterThan($queue[1]['risk']['score'], $queue[0]['risk']['score']);
    }

    public function test_the_flagship_intercept_is_derived_entirely_from_its_own_rows(): void
    {
        $approval = $this->intercept();
        $gate = $approval->workflowRun->steps()->where('step_type', 'approval_gate')->firstOrFail();
        $out = $gate->output_payload;

        $item = collect($this->props()['queue'])->firstWhere('id', $approval->id);

        $this->assertTrue($item['frozen']);
        $this->assertSame('DESTRUCTIVE', $item['category']);
        $this->assertSame('CRITICAL', $item['risk']['level_label']);
        $this->assertSame(
            'Delete '.number_format($out['rows_affected']).' customer records flagged dormant',
            $item['headline'],
        );
        $this->assertSame(number_format($out['rows_affected']).' rows', $item['impact_label']);
        $this->assertSame($out['rows_affected'], 14802);
        $this->assertSame('data.destructive.bulk_delete', $item['intercept']['rule']);
        $this->assertTrue($item['intercept']['irreversible']);
        $this->assertStringContainsString('threshold 500 rows', $item['intercept']['measure']);
        $this->assertStringContainsString('29.6× over', $item['intercept']['measure']);

        $rows = collect($item['impact_rows'])->pluck('value', 'label');
        $this->assertSame('14,802 rows', $rows['customer records']);
        $this->assertSame('3,941 rows', $rows['subscriptions (cascade)']);
        $this->assertSame('none referenced', $rows['snapshot']);
    }

    public function test_only_irreversible_items_are_frozen(): void
    {
        foreach ($this->props()['queue'] as $item) {
            $gate = WorkflowRun::query()
                ->findOrFail($item['run_id'])
                ->steps()
                ->where('step_type', 'approval_gate')
                ->first();

            $this->assertSame((bool) ($gate?->output_payload['irreversible'] ?? false), $item['frozen']);
        }
    }

    public function test_a_critical_but_reversible_breach_renders_as_a_normal_row(): void
    {
        $queue = collect($this->props()['queue']);

        $refund = $queue->firstWhere('run_key', '8421');
        $this->assertSame('CRITICAL', $refund['risk']['level_label']);
        $this->assertFalse($refund['frozen']);
        $this->assertFalse($refund['intercept']['irreversible']);
        $this->assertNotSame('DESTRUCTIVE', $refund['category']);

        $this->assertSame(
            [$this->intercept()->workflowRun->run_key],
            $queue->where('frozen', true)->pluck('run_key')->all(),
        );
    }

    public function test_every_item_carries_the_inspector_payload_its_expansion_renders(): void
    {
        foreach ($this->props()['queue'] as $item) {
            $run = WorkflowRun::query()->findOrFail($item['run_id']);

            $this->assertCount($run->steps()->count(), $item['detail']['trace']['nodes']);
            $this->assertCount($run->steps()->count(), $item['detail']['reasoning']['entries']);
            $this->assertNotNull($item['detail']['policy']['risk']);
            $this->assertNotEmpty($item['readout']['lines']);
        }
    }

    public function test_approving_signs_the_record_releases_the_held_step_and_clears_the_queue_row(): void
    {
        $approval = $this->intercept();
        $run = $approval->workflowRun;

        $this->assertSame('pending', $run->steps()->where('step_type', 'mutation')->firstOrFail()->status);

        $this->decide($approval, ['decision' => 'approve', 'note' => 'Scope confirmed with the data owner.'])
            ->assertRedirect('/reviews');

        $approval->refresh();
        $run->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertSame($this->user->name, $approval->resolved_by);
        $this->assertSame('Scope confirmed with the data owner.', $approval->resolution_notes);
        $this->assertNotNull($approval->resolved_at);

        $this->assertSame('completed', $run->status);
        $this->assertNull($run->error_message);
        $this->assertSame('completed', $run->steps()->where('step_type', 'mutation')->firstOrFail()->status);

        $this->assertDatabaseHas('audit_events', [
            'workflow_run_id' => $run->id,
            'action' => 'approval_approved',
            'performed_by' => $this->user->name,
        ]);

        $this->assertNotContains(
            $approval->id,
            collect($this->props()['queue'])->pluck('id')->all(),
        );
    }

    public function test_rejecting_stops_the_run_and_records_why(): void
    {
        $approval = $this->intercept();

        $this->decide($approval, ['decision' => 'reject', 'note' => 'Null last_seen_at is not dormancy.'])
            ->assertRedirect('/reviews');

        $approval->refresh();
        $run = $approval->workflowRun->refresh();

        $this->assertSame('rejected', $approval->status);
        $this->assertNotNull($approval->resolved_at);
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Null last_seen_at is not dormancy.', $run->error_message);
        $this->assertSame('blocked', $run->steps()->where('step_type', 'mutation')->firstOrFail()->status);

        $this->assertDatabaseHas('audit_events', [
            'workflow_run_id' => $run->id,
            'action' => 'approval_rejected',
        ]);
    }

    public function test_requesting_changes_hands_the_run_back_without_resolving_it(): void
    {
        $approval = $this->intercept();

        $this->decide($approval, ['decision' => 'request_changes', 'note' => 'Re-run with NULL excluded.'])
            ->assertRedirect('/reviews');

        $approval->refresh();
        $run = $approval->workflowRun->refresh();

        $this->assertSame('changes_requested', $approval->status);
        $this->assertNull($approval->resolved_at);
        $this->assertSame($this->user->name, $approval->resolved_by);

        $this->assertSame('needs_review', $run->status);
        $this->assertSame('pending', $run->steps()->where('step_type', 'mutation')->firstOrFail()->status);

        $props = $this->props();
        $this->assertNotContains($approval->id, collect($props['queue'])->pluck('id')->all());
        $this->assertContains($approval->id, collect($props['resolved'])->pluck('id')->all());
    }

    public function test_an_approval_can_only_be_decided_once(): void
    {
        $approval = $this->intercept();

        $this->decide($approval, ['decision' => 'approve'])->assertRedirect('/reviews');

        $this->decide($approval, ['decision' => 'reject'])
            ->assertSessionHasErrors('decision');

        $this->assertSame('approved', $approval->refresh()->status);
        $this->assertSame(1, AuditEvent::query()
            ->where('workflow_run_id', $approval->workflow_run_id)
            ->whereIn('action', ['approval_approved', 'approval_rejected'])
            ->count());
    }

    public function test_an_unrecognised_verdict_is_rejected(): void
    {
        $this->decide($this->intercept(), ['decision' => 'shrug'])
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending', $this->intercept()->refresh()->status);
    }

    public function test_the_runs_explorer_and_inspector_reflect_an_approval(): void
    {
        $approval = $this->intercept();
        $run = $approval->workflowRun;

        $this->decide($approval, ['decision' => 'approve', 'note' => 'Signed after data-owner review.']);

        $row = collect($this->props('/runs')['runs'])->firstWhere('id', $run->id);
        $this->assertSame('completed', $row['status']);
        $this->assertSame('COMPLETED', $row['status_label']);

        $inspector = $this->props("/runs/{$run->id}")['run'];
        $this->assertSame('completed', $inspector['status']);
        $this->assertFalse($inspector['decision']['pending']);
        $this->assertSame('approved', $inspector['decision']['resolution']['status']);
        $this->assertSame($this->user->name, $inspector['decision']['resolution']['by']);
        $this->assertSame('Signed after data-owner review.', $inspector['decision']['resolution']['notes']);
        $this->assertStringContainsString('APPROVED', $inspector['decision']['eyebrow']);
    }

    public function test_the_runs_explorer_and_inspector_reflect_a_rejection(): void
    {
        $approval = $this->approvalFor('8421');
        $run = $approval->workflowRun;

        $this->decide($approval, ['decision' => 'reject', 'note' => 'Outside the ceiling; refund manually.']);

        $row = collect($this->props('/runs')['runs'])->firstWhere('id', $run->id);
        $this->assertSame('failed', $row['status']);
        $this->assertSame('critical', $row['tone']);

        $inspector = $this->props("/runs/{$run->id}")['run'];
        $this->assertSame('failed', $inspector['status']);
        $this->assertSame('rejected', $inspector['decision']['resolution']['status']);
        $this->assertStringContainsString('Outside the ceiling', $inspector['error_message']);
    }

    public function test_the_queue_empties_once_every_pending_item_is_processed(): void
    {
        $pending = ApprovalRequest::query()->where('status', 'pending')->get();
        $this->assertGreaterThan(0, $pending->count());

        foreach ($pending as $index => $approval) {
            $this->decide($approval, ['decision' => $index % 2 === 0 ? 'approve' : 'reject']);
        }

        $props = $this->props();

        $this->assertSame([], $props['queue']);
        $this->assertSame(0, $props['stats']['pending']);
        $this->assertSame(0, $props['stats']['critical']);
        $this->assertSame('—', $props['stats']['average_wait_label']);
        $this->assertNull($props['stats']['oldest_wait_label']);
        $this->assertSame($pending->count(), $props['stats']['approved'] + $props['stats']['rejected']);
        $this->assertNotEmpty($props['resolved']);
        $this->assertSame(0, ApprovalRequest::query()->where('status', 'pending')->count());
    }
}
