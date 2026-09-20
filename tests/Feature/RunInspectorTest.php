<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\RunStep;
use App\Models\User;
use App\Models\WorkflowRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Run Inspector, exercised against the real seeded dataset. The flagship run
 * #8421 carries the breached policy gate; a plain completed run covers the
 * paths where there is no risk flag and nothing to sign.
 */
class RunInspectorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->where('email', 'admin@syntavex.local')->firstOrFail();
    }

    private function flagship(): WorkflowRun
    {
        return WorkflowRun::query()->where('run_key', '8421')->firstOrFail();
    }

    private function completedRun(): WorkflowRun
    {
        return WorkflowRun::query()
            ->where('status', 'completed')
            ->whereDoesntHave('approvalRequests')
            ->orderBy('id')
            ->firstOrFail();
    }

    private function visit(string $uri): TestResponse
    {
        return $this->actingAs($this->user)->get($uri);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(WorkflowRun $run): array
    {
        $response = $this->visit("/runs/{$run->id}");
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_the_inspector_requires_authentication(): void
    {
        $this->get('/runs/'.$this->flagship()->id)->assertRedirect('/login');
    }

    public function test_an_unknown_run_is_a_404(): void
    {
        $this->visit('/runs/999999')->assertNotFound();
    }

    public function test_it_renders_the_flagship_run(): void
    {
        $this->visit('/runs/'.$this->flagship()->id)->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Runs/Inspector')
                ->where('run.run_key', '8421')
                ->where('run.status', 'needs_review')
                ->where('run.workflow.name', 'Priority Refund Review')
                ->where('run.workspace.slug', 'northstar-support')
                ->has('header', 4)
                ->has('related')
        );
    }

    public function test_the_trace_renders_every_step_the_run_recorded(): void
    {
        $run = $this->flagship();
        $props = $this->props($run);

        $this->assertCount($run->steps()->count(), $props['run']['trace']['nodes']);
        $this->assertCount($run->steps()->count(), $props['run']['reasoning']['entries']);

        $orders = array_column($props['run']['trace']['nodes'], 'order');
        $this->assertSame($orders, array_values(array_unique($orders)));
        $this->assertSame($orders, collect($orders)->sort()->values()->all());
    }

    public function test_the_blocked_policy_gate_is_the_selected_step(): void
    {
        $run = $this->flagship();
        $gate = $run->steps()->where('step_type', 'approval_gate')->firstOrFail();

        $selected = collect($this->props($run)['run']['trace']['nodes'])
            ->firstWhere('selected', true);

        $this->assertSame($gate->id, $selected['id']);
        $this->assertStringContainsString(
            sprintf('step %02d selected', $gate->step_order),
            $this->props($run)['run']['trace']['summary'],
        );
    }

    public function test_every_reasoning_entry_carries_its_own_timestamp(): void
    {
        $run = $this->flagship();

        foreach ($this->props($run)['run']['reasoning']['entries'] as $entry) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}\.\d{3}$/', $entry['time']);
            $this->assertNotEmpty($entry['lines']);
        }
    }

    public function test_tool_calls_mirror_the_recorded_retrieval_and_mutation_steps(): void
    {
        $run = $this->flagship();
        $steps = $run->steps()->whereIn('step_type', ['retrieval', 'mutation'])->get();

        $calls = $this->props($run)['run']['toolCalls'];

        $this->assertCount($steps->count(), $calls['items']);
        $this->assertSame(
            $steps->pluck('id')->all(),
            array_column($calls['items'], 'id'),
        );

        $cost = $steps->sum(fn (RunStep $step): float => (float) $step->cost_usd);
        $this->assertStringContainsString('$'.number_format($cost, 2), $calls['summary']);

        // The Stripe credit never executed, so it must not claim an output.
        $pending = collect($calls['items'])->firstWhere('status', 'pending');
        $this->assertNotNull($pending);
        $this->assertNull($pending['output']);
    }

    public function test_the_risk_and_confidence_come_from_the_stored_rows(): void
    {
        $run = $this->flagship();
        $approval = $run->approvalRequests()->firstOrFail();
        $reasoning = $run->steps()->where('step_type', 'llm_reasoning')->firstOrFail();

        $policy = $this->props($run)['run']['policy'];

        $this->assertSame($approval->risk_level, $policy['risk']['level']);
        $this->assertSame('CRITICAL RISK', $policy['risk']['level_label']);
        $this->assertGreaterThan(0.75, $policy['risk']['score']);
        $this->assertSame(
            (float) $reasoning->output_payload['confidence'],
            $policy['confidence']['value'],
        );
        $this->assertSame('clear', $policy['confidence']['verdict']);
        $this->assertSame($approval->summary, $policy['breach']['summary']);
        $this->assertSame('automated_refund_ceiling', $policy['breach']['rule']);
        $this->assertSame(1, $policy['counts']['breached']);
    }

    public function test_a_pending_approval_offers_the_action_bar(): void
    {
        $decision = $this->props($this->flagship())['run']['decision'];

        $this->assertTrue($decision['pending']);
        $this->assertNull($decision['resolution']);
        $this->assertStringContainsString('UNSIGNED', $decision['eyebrow']);
        $this->assertStringContainsString('Refund $120.00', $decision['headline']);
    }

    public function test_a_resolved_approval_shows_its_resolution_instead(): void
    {
        $run = $this->flagship();

        $run->approvalRequests()->firstOrFail()->update([
            'status' => 'approved',
            'resolved_by' => 'mara.kessler',
            'resolution_notes' => 'Outage verified against the incident record.',
            'resolved_at' => now(),
        ]);

        $decision = $this->props($run->fresh())['run']['decision'];

        $this->assertFalse($decision['pending']);
        $this->assertSame('approved', $decision['resolution']['status']);
        $this->assertSame('mara.kessler', $decision['resolution']['by']);
        $this->assertStringContainsString('APPROVED', $decision['eyebrow']);
    }

    public function test_a_clean_run_renders_without_a_risk_flag_or_action_bar(): void
    {
        $run = $this->completedRun();
        $props = $this->props($run);

        $this->assertNull($props['run']['policy']['risk']);
        $this->assertNull($props['run']['policy']['breach']);
        $this->assertFalse($props['run']['decision']['pending']);
        $this->assertStringContainsString('NO APPROVAL REQUIRED', $props['run']['decision']['eyebrow']);
        $this->assertNotEmpty($props['run']['trace']['nodes']);
    }

    public function test_a_run_with_no_tool_calls_renders_an_empty_panel(): void
    {
        $run = $this->flagship();
        $run->steps()->whereIn('step_type', ['retrieval', 'mutation'])->delete();

        $calls = $this->props($run->fresh())['run']['toolCalls'];

        $this->assertSame([], $calls['items']);
        $this->assertNull($calls['note']);
        $this->assertStringContainsString('0 invocations', $calls['summary']);
    }

    public function test_a_run_with_no_steps_at_all_still_renders(): void
    {
        $run = $this->flagship();
        ApprovalRequest::query()->where('workflow_run_id', $run->id)->delete();
        $run->steps()->delete();

        $props = $this->props($run->fresh());

        $this->assertSame([], $props['run']['trace']['nodes']);
        $this->assertSame([], $props['run']['reasoning']['entries']);
        $this->assertSame([], $props['run']['toolCalls']['items']);
        $this->assertNull($props['run']['policy']['risk']);
    }

    public function test_a_failed_run_surfaces_its_error(): void
    {
        $run = WorkflowRun::query()->where('status', 'failed')->orderBy('id')->firstOrFail();
        $props = $this->props($run);

        $this->assertSame($run->error_message, $props['run']['error_message']);
        $this->assertSame('critical', $props['run']['tone']);
    }

    public function test_related_runs_exclude_this_run_and_link_to_real_ids(): void
    {
        $run = $this->flagship();
        $related = $this->props($run)['related'];

        $this->assertNotEmpty($related);
        $this->assertLessThanOrEqual(4, count($related));

        foreach ($related as $item) {
            $this->assertNotSame($run->id, $item['id']);
            $this->assertNotEmpty($item['label']);
            $this->assertDatabaseHas('workflow_runs', ['id' => $item['id']]);
        }
    }

    public function test_the_metadata_strip_reports_real_utc_timestamps(): void
    {
        $run = $this->flagship();
        $rows = collect($this->props($run)['run']['metadata'])->keyBy('label');

        $this->assertSame(
            $run->created_at->format('Y-m-d H:i:s'),
            $rows['Started (UTC)']['value'],
        );
        $this->assertSame(
            $run->created_at->copy()->addMilliseconds($run->total_duration_ms)->format('Y-m-d H:i:s'),
            $rows['Ended (UTC)']['value'],
        );
        $this->assertSame('northstar-support', $rows['Workspace']['value']);
        $this->assertSame('claude-opus-5', $rows['Model']['value']);
    }

    public function test_the_header_reports_the_runs_own_totals(): void
    {
        $run = $this->flagship();
        $header = collect($this->props($run)['header'])->keyBy('label');

        $this->assertSame(
            number_format($run->total_duration_ms / 1000, 2).'s',
            $header['DURATION']['value'],
        );
        $this->assertSame(
            '$'.number_format((float) $run->total_cost_usd, 2),
            $header['COST']['value'],
        );
        $this->assertStringContainsString('k', $header['TOKENS']['value']);
        $this->assertStringContainsString('p95', $header['DURATION']['caption']);
    }
}
