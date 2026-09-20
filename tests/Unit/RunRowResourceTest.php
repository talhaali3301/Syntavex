<?php

namespace Tests\Unit;

use App\Http\Resources\RunRowResource;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\RunStep;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The derivations behind a Runs Explorer row — objective, agent handle, token
 * formatting, risk score and decision headline — exercised directly on
 * in-memory models so each formula is pinned independently of the seeder.
 */
class RunRowResourceTest extends TestCase
{
    /**
     * @param  list<RunStep>  $steps
     * @param  list<AuditEvent>  $auditEvents
     * @return array<string, mixed>
     */
    private function row(
        array $steps = [],
        array $auditEvents = [],
        ?ApprovalRequest $approval = null,
        array $attributes = [],
    ): array {
        $run = new WorkflowRun([
            'run_key' => '9001',
            'status' => 'needs_review',
            'total_duration_ms' => 5850,
            'total_tokens' => 13900,
            'total_cost_usd' => 0.695,
            ...$attributes,
        ]);

        $run->id = 1;
        $run->created_at = Carbon::parse('2026-09-19 21:35:22');

        $run->setRelation('workflow', new Workflow(['name' => 'Priority Refund Review']));
        $run->setRelation('steps', new EloquentCollection($steps));
        $run->setRelation('auditEvents', new EloquentCollection($auditEvents));
        $run->setRelation(
            'approvalRequests',
            new EloquentCollection($approval === null ? [] : [$approval]),
        );

        return (new RunRowResource($run))->toArray(Request::create('/runs'));
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<string, mixed>|null  $output
     */
    private function step(
        string $name,
        string $type,
        string $status = 'completed',
        ?array $input = null,
        ?array $output = null,
    ): RunStep {
        $step = new RunStep([
            'step_name' => $name,
            'step_type' => $type,
            'status' => $status,
            'input_payload' => $input,
            'output_payload' => $output,
        ]);

        $step->id = random_int(1, 100000);

        return $step;
    }

    private function approval(string $risk = 'critical', string $status = 'pending'): ApprovalRequest
    {
        $approval = new ApprovalRequest([
            'risk_level' => $risk,
            'status' => $status,
            'summary' => 'Seeded summary, shown verbatim',
            'resolved_by' => $status === 'pending' ? null : 'mara.kessler',
        ]);

        $approval->created_at = Carbon::parse('2026-09-19 21:35:27');

        return $approval;
    }

    // ---------------------------------------------------------------------
    // Column formatting
    // ---------------------------------------------------------------------

    public function test_it_formats_duration_tokens_and_cost_for_the_table(): void
    {
        $row = $this->row();

        $this->assertSame('5.85s', $row['duration_label']);
        $this->assertSame('13.9k', $row['tokens_label']);
        $this->assertSame('$0.70', $row['cost_label']);
        $this->assertSame(0.695, $row['cost_usd']);
        $this->assertSame('Sep 19 · 21:35:22', $row['started_label']);
        $this->assertSame('NEEDS REVIEW', $row['status_label']);
        $this->assertSame('review', $row['tone']);
    }

    public function test_it_renders_dashes_rather_than_zeroes_for_missing_totals(): void
    {
        $row = $this->row(attributes: [
            'total_duration_ms' => null,
            'total_tokens' => null,
            'total_cost_usd' => null,
        ]);

        $this->assertSame('—', $row['duration_label']);
        $this->assertSame('—', $row['tokens_label']);
        $this->assertSame('—', $row['cost_label']);
        $this->assertNull($row['cost_usd']);
    }

    public function test_token_counts_under_a_thousand_are_not_abbreviated(): void
    {
        $this->assertSame('942', $this->row(attributes: ['total_tokens' => 942])['tokens_label']);
        $this->assertSame('1.0k', $this->row(attributes: ['total_tokens' => 1000])['tokens_label']);
    }

    // ---------------------------------------------------------------------
    // Objective
    // ---------------------------------------------------------------------

    public function test_a_refund_objective_names_its_amount_and_ticket(): void
    {
        $row = $this->row([
            $this->step('Zendesk refund request received', 'webhook', output: [
                'requested_amount' => 120.0,
                'ticket_id' => 'ZD-40219',
            ]),
        ]);

        $this->assertSame('Refund $120.00 · ticket ZD-40219', $row['objective']);
    }

    public function test_a_refund_with_no_linked_ticket_still_reads_as_a_refund(): void
    {
        // The missing ticket is the reason the run needs review, so it must not
        // fall through to a generic step-name objective.
        $row = $this->row([
            $this->step('Zendesk refund request received', 'webhook', output: [
                'requested_amount' => 65.0,
                'ticket_id' => null,
            ]),
        ]);

        $this->assertSame('Refund $65.00 · no ticket', $row['objective']);
    }

    public function test_it_reads_triage_health_brief_and_anomaly_objectives(): void
    {
        $this->assertSame(
            'Triage ticket ZD-40188 · P1',
            $this->row([
                $this->step('Zendesk ticket created', 'webhook', output: [
                    'ticket_id' => 'ZD-40188',
                    'initial_priority' => 'P1',
                ]),
            ])['objective'],
        );

        $this->assertSame(
            'Health brief · 31 accounts · 7d',
            $this->row([
                $this->step('Scheduled brief window opened', 'webhook', output: [
                    'accounts_in_scope' => 31,
                    'window_days' => 7,
                ]),
            ])['objective'],
        );

        $this->assertSame(
            'Anomaly · checkout-api · 4.2σ',
            $this->row([
                $this->step('Datadog anomaly event received', 'webhook', output: [
                    'service' => 'checkout-api',
                    'deviation_sigma' => 4.2,
                ]),
            ])['objective'],
        );
    }

    public function test_an_unrecognised_payload_falls_back_to_the_reasoning_step_name(): void
    {
        $row = $this->row([
            $this->step('Some trigger', 'webhook', output: ['unmapped' => true]),
            $this->step('Diagnose probable root cause', 'llm_reasoning'),
        ]);

        $this->assertSame('Diagnose probable root cause', $row['objective']);
    }

    // ---------------------------------------------------------------------
    // Agent handle
    // ---------------------------------------------------------------------

    public function test_the_agent_handle_comes_from_the_latest_agent_audit_event(): void
    {
        $older = new AuditEvent(['performed_by' => 'agent:ticket-triage']);
        $older->created_at = Carbon::parse('2026-09-19 21:00:00');

        $newer = new AuditEvent(['performed_by' => 'agent:refund-reviewer']);
        $newer->created_at = Carbon::parse('2026-09-19 21:35:27');

        $system = new AuditEvent(['performed_by' => 'system']);
        $system->created_at = Carbon::parse('2026-09-19 21:40:00');

        $this->assertSame('refund-reviewer', $this->row([], [$older, $newer, $system])['agent']);
    }

    public function test_a_run_with_no_agent_actor_reads_as_system(): void
    {
        $event = new AuditEvent(['performed_by' => 'system']);
        $event->created_at = Carbon::parse('2026-09-19 21:00:00');

        $this->assertSame('system', $this->row([], [$event])['agent']);
    }

    // ---------------------------------------------------------------------
    // Expansion: counts, risk and decision
    // ---------------------------------------------------------------------

    public function test_the_expansion_counts_steps_tool_calls_and_policy_hits(): void
    {
        $row = $this->row([
            $this->step('Webhook', 'webhook'),
            $this->step('Fetch context', 'retrieval'),
            $this->step('Reason', 'llm_reasoning'),
            $this->step('Gate cleared', 'approval_gate', 'completed'),
            $this->step('Gate blocked', 'approval_gate', 'blocked'),
            $this->step('Write back', 'mutation'),
        ]);

        $this->assertSame(6, $row['expansion']['steps']);
        // retrieval + mutation are the outbound calls.
        $this->assertSame(2, $row['expansion']['tool_calls']);
        // Only the gate that did NOT clear counts as a policy hit.
        $this->assertSame(1, $row['expansion']['policy_hits']);
    }

    public function test_a_run_with_no_steps_has_no_expansion(): void
    {
        $this->assertNull($this->row()['expansion']);
    }

    public function test_the_risk_score_combines_level_policy_breach_and_confidence(): void
    {
        $row = $this->row(
            [
                $this->step('Reason', 'llm_reasoning', input: ['model' => 'claude-opus-5'], output: ['confidence' => 0.94, 'decision' => 'approve']),
                $this->step('Policy ceiling check', 'approval_gate', 'blocked',
                    input: ['rule' => 'automated_refund_ceiling', 'requested_amount' => 120.0, 'policy_limit' => 100.0],
                    output: ['policy_limit' => 100.0, 'over_by' => 20.0],
                ),
            ],
            approval: $this->approval('critical'),
        );

        // 0.75 base + 0.15 × (20 ÷ 100) + 0.10 × (1 − 0.94) = 0.786 -> 0.79
        $this->assertSame(0.79, $row['expansion']['risk']['score']);
        $this->assertSame('CRITICAL', $row['expansion']['risk']['level_label']);
        $this->assertSame('critical', $row['expansion']['risk']['tone']);
        $this->assertSame('automated_refund_ceiling breached', $row['expansion']['risk']['rule']);
    }

    public function test_the_breach_term_is_capped_so_the_score_never_exceeds_one(): void
    {
        $row = $this->row(
            [
                $this->step('Reason', 'llm_reasoning', output: ['confidence' => 0.1]),
                $this->step('Gate', 'approval_gate', 'blocked',
                    input: ['rule' => 'ceiling', 'policy_limit' => 10.0],
                    output: ['policy_limit' => 10.0, 'over_by' => 9999.0],
                ),
            ],
            approval: $this->approval('critical'),
        );

        $this->assertLessThanOrEqual(1.0, $row['expansion']['risk']['score']);
        // 0.75 + 0.15 (capped) + 0.10 × 0.9 = 0.99
        $this->assertSame(0.99, $row['expansion']['risk']['score']);
    }

    public function test_a_run_with_no_approval_carries_no_risk_score(): void
    {
        $row = $this->row([$this->step('Reason', 'llm_reasoning', output: ['decision' => 'approve'])]);

        $this->assertNull($row['expansion']['risk']);
    }

    public function test_a_verdict_with_an_amount_reads_as_a_refund_decision(): void
    {
        $row = $this->row([
            $this->step('Reason', 'llm_reasoning', output: ['confidence' => 0.94, 'decision' => 'approve']),
            $this->step('Gate', 'approval_gate', 'blocked', input: ['requested_amount' => 120.0]),
        ]);

        $this->assertSame('Refund $120.00 approved', $row['expansion']['decision']['headline']);
        $this->assertSame('confidence 0.94', $row['expansion']['decision']['confidence_label']);
    }

    public function test_a_multi_word_verdict_is_read_back_as_a_phrase(): void
    {
        // Regression: this used to render "Approve_with_flagd by agent".
        $row = $this->row([
            $this->step('Reason', 'llm_reasoning', output: ['decision' => 'approve_with_flag']),
        ]);

        $this->assertSame('Approved with flag by agent', $row['expansion']['decision']['headline']);
    }

    public function test_a_rejection_reads_as_rejected(): void
    {
        $row = $this->row([
            $this->step('Reason', 'llm_reasoning', output: ['decision' => 'reject']),
        ]);

        $this->assertSame('Rejected by agent', $row['expansion']['decision']['headline']);
    }

    public function test_a_priority_change_is_described_instead_of_a_step_name(): void
    {
        $row = $this->row([
            $this->step('Classify intent & urgency', 'llm_reasoning', output: [
                'confidence' => 0.42,
                'original_priority' => 'P1',
                'proposed_priority' => 'P3',
            ]),
        ]);

        $this->assertSame('Priority P1 → P3 proposed', $row['expansion']['decision']['headline']);
    }

    public function test_an_unsigned_approval_is_labelled_unsigned_and_a_resolved_one_names_its_signer(): void
    {
        $steps = [$this->step('Reason', 'llm_reasoning', output: ['decision' => 'approve'])];

        $pending = $this->row($steps, approval: $this->approval('critical'));
        $this->assertFalse($pending['expansion']['decision']['signed']);
        $this->assertSame('unsigned', $pending['expansion']['decision']['signed_label']);
        $this->assertSame('Seeded summary, shown verbatim', $pending['expansion']['decision']['summary']);

        $resolved = $this->row($steps, approval: $this->approval('critical', 'approved'));
        $this->assertTrue($resolved['expansion']['decision']['signed']);
        $this->assertSame('signed by mara.kessler', $resolved['expansion']['decision']['signed_label']);
    }

    public function test_a_run_with_no_approval_needs_none(): void
    {
        $row = $this->row([$this->step('Reason', 'llm_reasoning', output: ['decision' => 'approve'])]);

        $this->assertSame('no approval required', $row['expansion']['decision']['signed_label']);
        $this->assertNull($row['expansion']['decision']['summary']);
    }

    public function test_the_trace_carries_one_toned_dot_per_step(): void
    {
        $row = $this->row([
            $this->step('Webhook', 'webhook', 'completed'),
            $this->step('Gate', 'approval_gate', 'blocked'),
            $this->step('Write back', 'mutation', 'pending'),
        ]);

        $this->assertSame(
            ['completed', 'review', 'idle'],
            array_column($row['expansion']['trace'], 'tone'),
        );
    }
}
