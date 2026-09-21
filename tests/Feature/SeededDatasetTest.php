<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\RunStep;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededDatasetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_seeds_one_workspace_with_four_workflows_and_forty_runs(): void
    {
        $this->assertSame(1, Workspace::query()->count());
        $this->assertSame(4, Workflow::query()->count());
        $this->assertSame(40, WorkflowRun::query()->count());

        $this->assertSame(
            ['api-anomaly-investigator', 'enterprise-ticket-triage', 'priority-refund-review', 'weekly-account-health-brief'],
            Workflow::query()->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_the_status_split_is_the_one_the_narrative_is_built_on(): void
    {
        $counts = WorkflowRun::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $this->assertSame(['completed' => 32, 'failed' => 5, 'needs_review' => 3], $counts);
    }

    public function test_run_keys_run_chronologically_and_end_on_the_flagship(): void
    {
        $keys = WorkflowRun::query()->orderBy('created_at')->pluck('run_key')->all();

        $this->assertSame(range(8382, 8421), array_map('intval', $keys));
        $this->assertSame('8421', end($keys));

        $newest = WorkflowRun::query()->orderByDesc('created_at')->firstOrFail();
        $this->assertSame('8421', $newest->run_key);
    }

    public function test_every_run_total_is_the_sum_of_its_own_steps(): void
    {
        foreach (WorkflowRun::query()->with('steps')->get() as $run) {
            $this->assertSame(
                (int) $run->steps->sum('duration_ms'),
                $run->total_duration_ms,
                "Run #{$run->run_key} duration does not match its steps.",
            );

            $this->assertSame(
                (int) $run->steps->sum('tokens_used'),
                $run->total_tokens,
                "Run #{$run->run_key} token total does not match its steps.",
            );

            $this->assertEqualsWithDelta(
                round((float) $run->steps->sum('cost_usd'), 4),
                (float) $run->total_cost_usd,
                0.0001,
                "Run #{$run->run_key} cost does not match its steps.",
            );
        }
    }

    public function test_every_step_is_ordered_and_timed_inside_its_run(): void
    {
        foreach (WorkflowRun::query()->with('steps')->get() as $run) {
            $orders = $run->steps->pluck('step_order')->all();
            $this->assertSame(range(1, count($orders)), $orders);

            foreach ($run->steps as $step) {
                $this->assertTrue(
                    $step->created_at->greaterThanOrEqualTo($run->created_at),
                    "A step on run #{$run->run_key} starts before the run does.",
                );
            }
        }
    }

    public function test_failed_runs_carry_an_error_message_and_a_failed_step(): void
    {
        $failed = WorkflowRun::query()->where('status', 'failed')->with('steps')->get();

        $this->assertCount(5, $failed);

        foreach ($failed as $run) {
            $this->assertNotNull($run->error_message);
            $this->assertSame(1, $run->steps->where('status', 'failed')->count());
            $this->assertSame('failed', $run->steps->last()->status);
        }
    }

    public function test_clean_runs_carry_no_error_message(): void
    {
        $this->assertSame(
            0,
            WorkflowRun::query()->where('status', '!=', 'failed')->whereNotNull('error_message')->count(),
        );
    }

    public function test_every_pending_approval_points_at_a_blocked_gate_in_its_own_run(): void
    {
        $approvals = ApprovalRequest::query()->with(['runStep', 'workflowRun'])->get();

        $this->assertCount(3, $approvals);
        $this->assertSame(['critical', 'low', 'medium'], $approvals->pluck('risk_level')->sort()->values()->all());

        foreach ($approvals as $approval) {
            $this->assertSame('pending', $approval->status);
            $this->assertNull($approval->resolved_at);
            $this->assertSame('approval_gate', $approval->runStep->step_type);
            $this->assertSame('blocked', $approval->runStep->status);
            $this->assertSame($approval->workflow_run_id, $approval->runStep->workflow_run_id);
            $this->assertSame('needs_review', $approval->workflowRun->status);
        }
    }

    public function test_every_run_is_bracketed_by_two_audit_events(): void
    {
        $this->assertSame(80, AuditEvent::query()->count());

        foreach (WorkflowRun::query()->with('auditEvents')->get() as $run) {
            $actions = $run->auditEvents->pluck('action')->all();

            $this->assertCount(2, $actions);
            $this->assertSame('run_created', $actions[0]);
            $this->assertContains($actions[1], ['run_completed', 'run_failed', 'approval_requested']);
        }
    }

    public function test_the_flagship_refund_is_internally_consistent(): void
    {
        $run = WorkflowRun::query()->where('run_key', '8421')->with('steps')->firstOrFail();

        $webhook = $run->steps->firstWhere('step_type', 'webhook');
        $gate = $run->steps->firstWhere('step_type', 'approval_gate');
        $mutation = $run->steps->firstWhere('step_type', 'mutation');
        $approval = $run->approvalRequests()->firstOrFail();

        $amount = $webhook->output_payload['requested_amount'];
        $this->assertEquals(120.0, $amount);
        $this->assertEquals($amount, $gate->input_payload['requested_amount']);
        $this->assertEquals($amount, $gate->output_payload['requested_amount']);
        $this->assertEquals($amount, $mutation->input_payload['amount']);
        $this->assertStringContainsString('$120', $approval->summary);

        $this->assertEquals(
            $gate->output_payload['requested_amount'] - $gate->output_payload['policy_limit'],
            $gate->output_payload['over_by'],
        );

        $this->assertSame('CUS-9281', $webhook->output_payload['customer_id']);
        $this->assertSame('CUS-9281', $mutation->input_payload['customer_id']);
        $this->assertSame('ZD-40219', $webhook->input_payload['ticket_id']);
        $this->assertSame('ZD-40219', $webhook->output_payload['ticket_id']);

        $this->assertSame('blocked', $gate->status);
        $this->assertSame('pending', $mutation->status);
        $this->assertNull($mutation->output_payload);
    }

    public function test_the_flagship_rationale_dates_itself_from_the_run(): void
    {
        $run = WorkflowRun::query()->where('run_key', '8421')->with('steps')->firstOrFail();
        $reasoning = $run->steps->firstWhere('step_type', 'llm_reasoning');

        preg_match('/\d{4}-\d{2}-\d{2}/', $reasoning->output_payload['rationale'], $quoted);

        $this->assertNotEmpty($quoted, 'The rationale should date the outage it refers to.');
        $this->assertSame(
            $run->created_at->copy()->subDay()->toDateString(),
            $quoted[0],
            'The outage date drifted away from the run it belongs to.',
        );
    }

    public function test_only_reasoning_and_retrieval_steps_burn_tokens(): void
    {
        $spending = RunStep::query()
            ->whereNotNull('tokens_used')
            ->where('tokens_used', '>', 0)
            ->pluck('step_type')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['llm_reasoning', 'retrieval'], $spending);
    }

    public function test_reseeding_is_deterministic_where_the_narrative_depends_on_it(): void
    {
        $flagship = WorkflowRun::query()->where('run_key', '8421')->firstOrFail();

        $this->assertSame(5850, $flagship->total_duration_ms);
        $this->assertSame(13900, $flagship->total_tokens);
        $this->assertSame('0.6950', $flagship->total_cost_usd);
        $this->assertSame(5, $flagship->steps()->count());
    }
}
