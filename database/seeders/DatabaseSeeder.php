<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\RunStep;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the SyntaVex demo dataset: one workspace, four workflows and 40 runs
 * spread across the last 14 days, including the flagship run #8421 that the
 * product narrative (Human Attention queue) is built around.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Runs are keyed 8382..8421 in chronological order, so #8421 is the newest. */
    private const FIRST_RUN_KEY = 8382;

    private const TOTAL_RUNS = 40;

    /** Relative cost weight per step type when splitting a run's duration budget. */
    private const DURATION_WEIGHTS = [
        'webhook' => 1,
        'retrieval' => 3,
        'llm_reasoning' => 6,
        'approval_gate' => 1,
        'mutation' => 2,
    ];

    /** Only retrieval and reasoning steps burn tokens. */
    private const TOKEN_WEIGHTS = [
        'webhook' => 0,
        'retrieval' => 2,
        'llm_reasoning' => 6,
        'approval_gate' => 0,
        'mutation' => 0,
    ];

    private Workspace $workspace;

    /** @var array<string, Workflow> */
    private array $workflows = [];

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@syntavex.local'],
            [
                'name' => 'SyntaVex Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $this->seedWorkspace();
        $this->seedWorkflows();
        $this->seedRuns();
    }

    private function seedWorkspace(): void
    {
        $this->workspace = Workspace::create([
            'name' => 'Northstar Support',
            'slug' => 'northstar-support',
            'tier' => 'Production',
        ]);
    }

    private function seedWorkflows(): void
    {
        $definitions = [
            ['Priority Refund Review', 'priority-refund-review', 'webhook'],
            ['Enterprise Ticket Triage', 'enterprise-ticket-triage', 'webhook'],
            ['Weekly Account Health Brief', 'weekly-account-health-brief', 'cron'],
            ['API Anomaly Investigator', 'api-anomaly-investigator', 'event'],
        ];

        foreach ($definitions as [$name, $slug, $trigger]) {
            $this->workflows[$slug] = Workflow::create([
                'workspace_id' => $this->workspace->id,
                'name' => $name,
                'slug' => $slug,
                'trigger_type' => $trigger,
                'is_active' => true,
            ]);
        }
    }

    private function seedRuns(): void
    {
        $specs = $this->buildRunSpecs();

        usort($specs, fn (array $a, array $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        foreach ($specs as $index => $spec) {
            $this->createRun($spec, (string) (self::FIRST_RUN_KEY + $index));
        }
    }

    /**
     * 40 run specs: 32 completed, 5 failed, 3 needs_review.
     *
     * @return list<array<string, mixed>>
     */
    private function buildRunSpecs(): array
    {
        $specs = [];

        // Volume per workflow — ticket triage is the busiest, the weekly brief the quietest.
        $volume = [
            'priority-refund-review' => 11,
            'enterprise-ticket-triage' => 15,
            'weekly-account-health-brief' => 4,
            'api-anomaly-investigator' => 10,
        ];

        // The three runs sitting in the Human Attention queue.
        $specs[] = [
            'workflow' => 'priority-refund-review',
            'status' => 'needs_review',
            'scenario' => 'flagship',
            'error_message' => null,
            // Forced to be the newest run so it lands on run_key 8421.
            'created_at' => now()->subMinutes(47),
        ];
        $specs[] = [
            'workflow' => 'priority-refund-review',
            'status' => 'needs_review',
            'scenario' => 'goodwill_no_ticket',
            'error_message' => null,
            'created_at' => $this->organicTimestamp(1, 5),
        ];
        $specs[] = [
            'workflow' => 'enterprise-ticket-triage',
            'status' => 'needs_review',
            'scenario' => 'low_confidence_downgrade',
            'error_message' => null,
            'created_at' => $this->organicTimestamp(2, 9),
        ];

        $volume['priority-refund-review'] -= 2;
        $volume['enterprise-ticket-triage'] -= 1;

        // Five failures, each with a plausible upstream cause.
        $failures = [
            ['priority-refund-review', 'ConnectionRefused: Stripe sandbox'],
            ['enterprise-ticket-triage', 'RateLimitExceeded: HubSpot API'],
            ['enterprise-ticket-triage', 'ValidationError: missing customer_id'],
            ['weekly-account-health-brief', 'Timeout 504: upstream retrieval service'],
            ['api-anomaly-investigator', 'Timeout 504: LLM provider'],
        ];

        foreach ($failures as [$slug, $message]) {
            $specs[] = [
                'workflow' => $slug,
                'status' => 'failed',
                'scenario' => null,
                'error_message' => $message,
                'created_at' => $this->organicTimestamp(),
            ];
            $volume[$slug]--;
        }

        // Everything else completed cleanly.
        foreach ($volume as $slug => $remaining) {
            for ($i = 0; $i < $remaining; $i++) {
                $specs[] = [
                    'workflow' => $slug,
                    'status' => 'completed',
                    'scenario' => null,
                    'error_message' => null,
                    'created_at' => $this->organicTimestamp(),
                ];
            }
        }

        if (count($specs) !== self::TOTAL_RUNS) {
            throw new \RuntimeException('Expected '.self::TOTAL_RUNS.' run specs, built '.count($specs).'.');
        }

        return $specs;
    }

    /** A timestamp somewhere in the last 14 days that does not look machine-spaced. */
    private function organicTimestamp(?int $minDays = null, ?int $maxDays = null): Carbon
    {
        return now()
            ->subDays(random_int($minDays ?? 0, $maxDays ?? 14))
            ->subHours(random_int(1, 23))
            ->subMinutes(random_int(0, 59))
            ->subSeconds(random_int(0, 59));
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function createRun(array $spec, string $runKey): void
    {
        $workflow = $this->workflows[$spec['workflow']];
        $createdAt = $spec['created_at'];

        $steps = match ($spec['scenario']) {
            'flagship' => $this->flagshipSteps($createdAt),
            'goodwill_no_ticket' => $this->goodwillSteps(),
            'low_confidence_downgrade' => $this->lowConfidenceSteps(),
            default => $this->generatedSteps($spec['workflow'], $spec['status'], $spec['error_message']),
        };

        $totals = [
            'duration' => array_sum(array_column($steps, 'duration_ms')),
            'tokens' => array_sum(array_column($steps, 'tokens_used')),
            'cost' => round(array_sum(array_column($steps, 'cost_usd')), 4),
        ];

        $run = $this->persist(new WorkflowRun([
            'workflow_id' => $workflow->id,
            'run_key' => $runKey,
            'status' => $spec['status'],
            'total_duration_ms' => $totals['duration'],
            'total_tokens' => $totals['tokens'],
            'total_cost_usd' => $totals['cost'],
            'error_message' => $spec['error_message'],
        ]), $createdAt, $createdAt->copy()->addMilliseconds($totals['duration']));

        $cursor = $createdAt->copy();
        $persistedSteps = [];

        foreach ($steps as $offset => $step) {
            $startedAt = $cursor->copy();
            $finishedAt = $startedAt->copy()->addMilliseconds($step['duration_ms'] ?? 0);
            $cursor = $finishedAt;

            $persistedSteps[] = $this->persist(new RunStep([
                'workflow_run_id' => $run->id,
                'step_order' => $offset + 1,
                'step_name' => $step['step_name'],
                'step_type' => $step['step_type'],
                'status' => $step['status'],
                'duration_ms' => $step['duration_ms'],
                'tokens_used' => $step['tokens_used'],
                'cost_usd' => $step['cost_usd'],
                'input_payload' => $step['input_payload'],
                'output_payload' => $step['output_payload'],
            ]), $startedAt, $finishedAt);
        }

        if ($spec['scenario'] !== null) {
            $this->createApprovalRequest($spec['scenario'], $run, $persistedSteps, $cursor);
        }

        $this->createAuditEvents($spec, $run, $createdAt, $cursor);
    }

    // ---------------------------------------------------------------------
    // Scripted scenarios
    // ---------------------------------------------------------------------

    /**
     * Run #8421 — the refund that breached the automated approval ceiling.
     *
     * The outage date in the agent's rationale is derived from the run's own
     * timestamp rather than written in by hand, so the narrative still lines up
     * with the dates on screen whenever the dataset is reseeded.
     *
     * @return list<array<string, mixed>>
     */
    private function flagshipSteps(Carbon $runAt): array
    {
        $outageDate = $runAt->copy()->subDay()->toDateString();

        return [
            $this->step('Zendesk refund request received', 'webhook', 'completed', 312, null,
                ['source' => 'zendesk.webhook', 'ticket_id' => 'ZD-40219', 'event' => 'refund.requested'],
                [
                    'customer_id' => 'CUS-9281',
                    'ticket_id' => 'ZD-40219',
                    'requested_amount' => 120.00,
                    'currency' => 'USD',
                    'reason' => '47-minute API outage',
                ],
            ),
            $this->step('Fetch account context (HubSpot)', 'retrieval', 'completed', 1184, 1420,
                ['customer_id' => 'CUS-9281', 'objects' => ['company', 'subscription', 'health_score']],
                [
                    'customer_id' => 'CUS-9281',
                    'account_name' => 'Northwind Logistics',
                    'tier' => 'Enterprise',
                    'churn_risk' => 'low',
                    'mrr_usd' => 8400,
                    'lifetime_refunds_usd' => 240.00,
                    'open_tickets' => 2,
                ],
            ),
            $this->step('Evaluate refund policy', 'llm_reasoning', 'completed', 4260, 12480,
                ['policy_document' => 'refund-policy-v4', 'context' => 'account+incident', 'model' => 'claude-opus-5'],
                [
                    'confidence' => 0.94,
                    'decision' => 'approve',
                    'rationale' => 'Verified 47-minute platform outage on '.$outageDate.' affecting this account. Enterprise tier with low churn risk and a clean refund history; goodwill credit is proportionate to the SLA breach.',
                    'drafted_apology' => "Hi Dana, you're right, and I'm sorry. Our API was unavailable for 47 minutes yesterday, which is squarely on us and well outside the uptime we commit to on your Enterprise plan. I've put through a $120 credit to cover the affected window; it should land on your next invoice. The root cause has been fixed and I'm happy to share the incident write-up if useful.",
                    'policy_references' => ['refund-policy-v4 §3.2', 'sla-enterprise §1.4'],
                ],
            ),
            $this->step('Policy ceiling check', 'approval_gate', 'blocked', 94, null,
                ['requested_amount' => 120.00, 'policy_limit' => 100.00, 'rule' => 'automated_refund_ceiling'],
                [
                    'policy_limit' => 100.00,
                    'requested_amount' => 120.00,
                    'over_by' => 20.00,
                    'reason' => 'Exceeds automated approval ceiling',
                    'escalated_to' => 'human_review',
                ],
            ),
            $this->step('Issue Stripe credit', 'mutation', 'pending', null, null,
                ['provider' => 'stripe', 'customer_id' => 'CUS-9281', 'amount' => 120.00, 'currency' => 'USD'],
                null,
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function goodwillSteps(): array
    {
        return [
            $this->step('Zendesk refund request received', 'webhook', 'completed', 286, null,
                ['source' => 'zendesk.webhook', 'event' => 'refund.requested'],
                ['customer_id' => 'CUS-7734', 'requested_amount' => 65.00, 'currency' => 'USD', 'reason' => 'Goodwill for repeated onboarding friction', 'ticket_id' => null],
            ),
            $this->step('Fetch account context (HubSpot)', 'retrieval', 'completed', 1042, 1180,
                ['customer_id' => 'CUS-7734'],
                ['customer_id' => 'CUS-7734', 'account_name' => 'Calder Retail Group', 'tier' => 'Growth', 'churn_risk' => 'medium', 'mrr_usd' => 1900],
            ),
            $this->step('Evaluate refund policy', 'llm_reasoning', 'completed', 3680, 9240,
                ['policy_document' => 'refund-policy-v4', 'model' => 'claude-opus-5'],
                [
                    'confidence' => 0.71,
                    'decision' => 'approve_with_flag',
                    'rationale' => 'Amount is within the automated ceiling, but no support ticket is linked to the request, so the stated justification cannot be corroborated.',
                ],
            ),
            $this->step('Ticket linkage check', 'approval_gate', 'blocked', 78, null,
                ['rule' => 'goodwill_requires_ticket_reference', 'ticket_id' => null],
                ['policy_rule' => 'goodwill_requires_ticket_reference', 'ticket_id' => null, 'amount' => 65.00, 'reason' => 'Goodwill credit has no linked support ticket'],
            ),
            $this->step('Issue Stripe credit', 'mutation', 'pending', null, null,
                ['provider' => 'stripe', 'customer_id' => 'CUS-7734', 'amount' => 65.00, 'currency' => 'USD'],
                null,
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lowConfidenceSteps(): array
    {
        return [
            $this->step('Zendesk ticket created', 'webhook', 'completed', 244, null,
                ['source' => 'zendesk.webhook', 'event' => 'ticket.created'],
                ['ticket_id' => 'ZD-40188', 'customer_id' => 'CUS-3115', 'subject' => 'Bulk export finishes but rows are missing', 'initial_priority' => 'P1'],
            ),
            $this->step('Load account & SLA tier', 'retrieval', 'completed', 890, 960,
                ['customer_id' => 'CUS-3115'],
                ['account_name' => 'Halden Freight', 'tier' => 'Enterprise', 'sla_response_minutes' => 30, 'open_incidents' => 0],
            ),
            $this->step('Classify intent & urgency', 'llm_reasoning', 'completed', 3120, 7480,
                ['taxonomy' => 'support-intent-v3', 'model' => 'claude-opus-5'],
                [
                    'confidence' => 0.42,
                    'intent' => 'data_integrity_question',
                    'proposed_priority' => 'P3',
                    'original_priority' => 'P1',
                    'rationale' => 'Wording suggests an export filter misunderstanding rather than data loss, but the ticket lacks a row count or job id to confirm.',
                ],
            ),
            $this->step('Confidence threshold check', 'approval_gate', 'blocked', 61, null,
                ['rule' => 'min_confidence_for_priority_change', 'threshold' => 0.75, 'observed' => 0.42],
                ['threshold' => 0.75, 'observed_confidence' => 0.42, 'change' => 'P1 -> P3', 'reason' => 'Confidence below threshold for an automated priority downgrade'],
            ),
            $this->step('Assign to escalation queue', 'mutation', 'pending', null, null,
                ['queue' => 'tier-2-enterprise', 'ticket_id' => 'ZD-40188'],
                null,
            ),
        ];
    }

    /**
     * @param  list<RunStep>  $steps
     */
    private function createApprovalRequest(string $scenario, WorkflowRun $run, array $steps, Carbon $at): void
    {
        $gate = collect($steps)->firstWhere('step_type', 'approval_gate');

        [$risk, $summary] = match ($scenario) {
            'flagship' => ['critical', 'Agent authorised a $120 refund above the $100 policy ceiling'],
            'goodwill_no_ticket' => ['medium', 'Goodwill credit of $65 issued with no linked support ticket. Policy requires a ticket reference before payout.'],
            'low_confidence_downgrade' => ['low', 'Low-confidence (0.42) escalation tier downgrade from P1 to P3 on an Enterprise account. Informational review.'],
        };

        $this->persist(new ApprovalRequest([
            'workflow_run_id' => $run->id,
            'run_step_id' => $gate->id,
            'risk_level' => $risk,
            'summary' => $summary,
            'status' => 'pending',
            'resolved_by' => null,
            'resolution_notes' => null,
            'resolved_at' => null,
        ]), $at, $at);
    }

    // ---------------------------------------------------------------------
    // Generated runs
    // ---------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function generatedSteps(string $workflowSlug, string $status, ?string $errorMessage): array
    {
        $catalog = $this->stepCatalog($workflowSlug);
        $count = random_int(3, min(6, count($catalog)));
        $chosen = array_slice($catalog, 0, $count);

        $statuses = array_fill(0, $count, 'completed');
        if ($status === 'failed') {
            // Fail on the last step reached; nothing downstream ran.
            $statuses[$count - 1] = 'failed';
        }

        $durationBudget = random_int(800, 15000);
        $tokenBudget = random_int(2000, 35000);
        $rate = random_int(50, 57) / 1_000_000; // blended USD per token

        $durations = $this->split($durationBudget, array_map(
            fn (array $s) => self::DURATION_WEIGHTS[$s['type']],
            $chosen,
        ));
        $tokens = $this->split($tokenBudget, array_map(
            fn (array $s) => self::TOKEN_WEIGHTS[$s['type']],
            $chosen,
        ));

        $steps = [];

        foreach ($chosen as $i => $definition) {
            $failed = $statuses[$i] === 'failed';
            $stepTokens = $tokens[$i] > 0 ? $tokens[$i] : null;

            $steps[] = $this->step(
                $definition['name'],
                $definition['type'],
                $statuses[$i],
                $durations[$i],
                $stepTokens,
                ($definition['in'])(),
                $failed ? ['error' => $errorMessage, 'retryable' => str_contains((string) $errorMessage, 'Timeout')] : ($definition['out'])(),
                $stepTokens === null ? null : round($stepTokens * $rate, 4),
            );
        }

        return $steps;
    }

    /**
     * Ordered step pipeline per workflow; a run executes the first N of these.
     *
     * @return list<array{name: string, type: string, in: callable, out: callable}>
     */
    private function stepCatalog(string $slug): array
    {
        $customer = fn () => 'CUS-'.random_int(1000, 9999);
        $ticket = fn () => 'ZD-'.random_int(39000, 40500);

        return match ($slug) {
            'priority-refund-review' => [
                ['name' => 'Zendesk refund request received', 'type' => 'webhook',
                    'in' => fn () => ['source' => 'zendesk.webhook', 'event' => 'refund.requested'],
                    'out' => fn () => ['customer_id' => $customer(), 'ticket_id' => $ticket(), 'requested_amount' => round(random_int(1500, 9500) / 100, 2), 'currency' => 'USD']],
                ['name' => 'Fetch account context (HubSpot)', 'type' => 'retrieval',
                    'in' => fn () => ['objects' => ['company', 'subscription']],
                    'out' => fn () => ['tier' => ['Growth', 'Enterprise', 'Starter'][random_int(0, 2)], 'churn_risk' => ['low', 'low', 'medium'][random_int(0, 2)], 'mrr_usd' => random_int(400, 9000)]],
                ['name' => 'Evaluate refund policy', 'type' => 'llm_reasoning',
                    'in' => fn () => ['policy_document' => 'refund-policy-v4', 'model' => 'claude-opus-5'],
                    'out' => fn () => ['confidence' => round(random_int(78, 99) / 100, 2), 'decision' => 'approve', 'within_policy_ceiling' => true]],
                ['name' => 'Policy ceiling check', 'type' => 'approval_gate',
                    'in' => fn () => ['rule' => 'automated_refund_ceiling', 'policy_limit' => 100.00],
                    'out' => fn () => ['policy_limit' => 100.00, 'result' => 'pass']],
                ['name' => 'Issue Stripe credit', 'type' => 'mutation',
                    'in' => fn () => ['provider' => 'stripe'],
                    'out' => fn () => ['credit_note_id' => 'cn_'.strtolower(bin2hex(random_bytes(6))), 'state' => 'issued']],
                ['name' => 'Post resolution note to Zendesk', 'type' => 'mutation',
                    'in' => fn () => ['channel' => 'zendesk.comment', 'public' => true],
                    'out' => fn () => ['comment_id' => random_int(800000, 899999), 'ticket_status' => 'solved']],
            ],
            'enterprise-ticket-triage' => [
                ['name' => 'Zendesk ticket created', 'type' => 'webhook',
                    'in' => fn () => ['source' => 'zendesk.webhook', 'event' => 'ticket.created'],
                    'out' => fn () => ['ticket_id' => $ticket(), 'customer_id' => $customer(), 'initial_priority' => ['P1', 'P2', 'P3'][random_int(0, 2)]]],
                ['name' => 'Load account & SLA tier', 'type' => 'retrieval',
                    'in' => fn () => ['source' => 'hubspot'],
                    'out' => fn () => ['tier' => 'Enterprise', 'sla_response_minutes' => [15, 30, 60][random_int(0, 2)]]],
                ['name' => 'Search similar past tickets', 'type' => 'retrieval',
                    'in' => fn () => ['index' => 'support-history', 'top_k' => 5],
                    'out' => fn () => ['matches' => random_int(2, 9), 'top_similarity' => round(random_int(61, 96) / 100, 2)]],
                ['name' => 'Classify intent & urgency', 'type' => 'llm_reasoning',
                    'in' => fn () => ['taxonomy' => 'support-intent-v3', 'model' => 'claude-opus-5'],
                    'out' => fn () => ['confidence' => round(random_int(80, 99) / 100, 2), 'intent' => ['billing_dispute', 'integration_error', 'feature_request', 'data_integrity_question'][random_int(0, 3)], 'proposed_priority' => ['P1', 'P2', 'P3'][random_int(0, 2)]]],
                ['name' => 'Assign to escalation queue', 'type' => 'mutation',
                    'in' => fn () => ['queue' => 'tier-2-enterprise'],
                    'out' => fn () => ['assigned' => true, 'assignee_group' => 'tier-2-enterprise']],
                ['name' => 'Notify account owner in Slack', 'type' => 'mutation',
                    'in' => fn () => ['channel' => '#cs-enterprise'],
                    'out' => fn () => ['delivered' => true, 'message_ts' => (string) random_int(1750000000, 1790000000).'.00'.random_int(10, 99)]],
            ],
            'weekly-account-health-brief' => [
                ['name' => 'Scheduled brief window opened', 'type' => 'webhook',
                    'in' => fn () => ['trigger' => 'cron', 'expression' => '0 6 * * MON'],
                    'out' => fn () => ['accounts_in_scope' => random_int(18, 44), 'window_days' => 7]],
                ['name' => 'Pull usage telemetry (Snowflake)', 'type' => 'retrieval',
                    'in' => fn () => ['warehouse' => 'analytics_wh', 'window_days' => 7],
                    'out' => fn () => ['rows' => random_int(4000, 26000), 'accounts' => random_int(18, 44)]],
                ['name' => 'Fetch support ticket volume', 'type' => 'retrieval',
                    'in' => fn () => ['source' => 'zendesk.search'],
                    'out' => fn () => ['tickets' => random_int(30, 190), 'escalations' => random_int(0, 7)]],
                ['name' => 'Summarise account health signals', 'type' => 'llm_reasoning',
                    'in' => fn () => ['model' => 'claude-opus-5', 'format' => 'per-account bullets'],
                    'out' => fn () => ['confidence' => round(random_int(84, 98) / 100, 2), 'accounts_flagged' => random_int(1, 6)]],
                ['name' => 'Draft CSM brief', 'type' => 'llm_reasoning',
                    'in' => fn () => ['model' => 'claude-opus-5', 'tone' => 'concise, action-first'],
                    'out' => fn () => ['sections' => 4, 'word_count' => random_int(380, 900)]],
                ['name' => 'Publish brief to Notion', 'type' => 'mutation',
                    'in' => fn () => ['database' => 'CSM Weekly Briefs'],
                    'out' => fn () => ['page_id' => strtolower(bin2hex(random_bytes(8))), 'published' => true]],
            ],
            default => [
                ['name' => 'Datadog anomaly event received', 'type' => 'webhook',
                    'in' => fn () => ['source' => 'datadog.monitor', 'event' => 'anomaly.detected'],
                    'out' => fn () => ['monitor_id' => random_int(100000, 199999), 'service' => ['checkout-api', 'billing-api', 'search-api'][random_int(0, 2)], 'deviation_sigma' => round(random_int(25, 70) / 10, 1)]],
                ['name' => 'Fetch error traces (Sentry)', 'type' => 'retrieval',
                    'in' => fn () => ['window_minutes' => 30],
                    'out' => fn () => ['events' => random_int(12, 900), 'distinct_issues' => random_int(1, 6)]],
                ['name' => 'Correlate with deploy timeline', 'type' => 'retrieval',
                    'in' => fn () => ['source' => 'github.deployments'],
                    'out' => fn () => ['deploys_in_window' => random_int(0, 3), 'suspect_sha' => substr(bin2hex(random_bytes(4)), 0, 7)]],
                ['name' => 'Diagnose probable root cause', 'type' => 'llm_reasoning',
                    'in' => fn () => ['model' => 'claude-opus-5', 'context' => 'traces+deploys+metrics'],
                    'out' => fn () => ['confidence' => round(random_int(76, 97) / 100, 2), 'root_cause' => ['connection pool exhaustion', 'N+1 query regression', 'upstream provider latency', 'cache stampede after deploy'][random_int(0, 3)]]],
                ['name' => 'Open PagerDuty incident', 'type' => 'mutation',
                    'in' => fn () => ['service' => 'platform-oncall', 'urgency' => 'high'],
                    'out' => fn () => ['incident_id' => 'PD-'.random_int(4000, 4999), 'state' => 'triggered']],
                ['name' => 'Post incident summary to Slack', 'type' => 'mutation',
                    'in' => fn () => ['channel' => '#incidents'],
                    'out' => fn () => ['delivered' => true, 'thread_replies' => random_int(0, 5)]],
            ],
        };
    }

    // ---------------------------------------------------------------------
    // Audit trail
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $spec
     */
    private function createAuditEvents(array $spec, WorkflowRun $run, Carbon $startedAt, Carbon $finishedAt): void
    {
        $actor = match ($spec['workflow']) {
            'priority-refund-review' => 'agent:refund-reviewer',
            'enterprise-ticket-triage' => 'agent:ticket-triage',
            'weekly-account-health-brief' => 'agent:health-brief',
            default => 'agent:anomaly-investigator',
        };

        $this->persist(new AuditEvent([
            'workspace_id' => $this->workspace->id,
            'workflow_run_id' => $run->id,
            'action' => 'run_created',
            'performed_by' => 'system',
            'details' => [
                'run_key' => $run->run_key,
                'workflow' => $spec['workflow'],
                'trigger' => $this->workflows[$spec['workflow']]->trigger_type,
            ],
        ]), $startedAt, $startedAt);

        [$action, $details] = match ($spec['status']) {
            'completed' => ['run_completed', [
                'run_key' => $run->run_key,
                'duration_ms' => $run->total_duration_ms,
                'tokens' => $run->total_tokens,
                'cost_usd' => (float) $run->total_cost_usd,
            ]],
            'failed' => ['run_failed', [
                'run_key' => $run->run_key,
                'error' => $spec['error_message'],
                'failed_at_step' => $run->steps()->count(),
            ]],
            default => ['approval_requested', [
                'run_key' => $run->run_key,
                'risk_level' => match ($spec['scenario']) {
                    'flagship' => 'critical',
                    'goodwill_no_ticket' => 'medium',
                    default => 'low',
                },
                'queue' => 'human_attention',
            ]],
        };

        $this->persist(new AuditEvent([
            'workspace_id' => $this->workspace->id,
            'workflow_run_id' => $run->id,
            'action' => $action,
            'performed_by' => $spec['status'] === 'failed' ? 'system' : $actor,
            'details' => $details,
        ]), $finishedAt, $finishedAt);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function step(
        string $name,
        string $type,
        string $status,
        ?int $durationMs,
        ?int $tokens,
        ?array $input,
        ?array $output,
        ?float $cost = null,
    ): array {
        return [
            'step_name' => $name,
            'step_type' => $type,
            'status' => $status,
            'duration_ms' => $durationMs,
            'tokens_used' => $tokens,
            'cost_usd' => $cost ?? ($tokens === null ? null : round($tokens * 0.00005, 4)),
            'input_payload' => $input,
            'output_payload' => $output,
        ];
    }

    /**
     * Split a budget across weighted slots, giving the remainder to the heaviest slot.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function split(int $total, array $weights): array
    {
        $sum = array_sum($weights);

        if ($sum === 0) {
            return array_fill(0, count($weights), 0);
        }

        $parts = [];
        foreach ($weights as $weight) {
            $parts[] = $weight === 0 ? 0 : (int) floor($total * $weight / $sum);
        }

        $heaviest = array_search(max($weights), $weights, true);
        $parts[$heaviest] += $total - array_sum($parts);

        return $parts;
    }

    /**
     * Save a model with explicit, backdated timestamps.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function persist(Model $model, Carbon $createdAt, Carbon $updatedAt): Model
    {
        $model->created_at = $createdAt;
        $model->updated_at = $updatedAt;
        $model->save();

        return $model;
    }
}
