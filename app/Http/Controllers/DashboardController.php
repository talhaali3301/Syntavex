<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApprovalRequestResource;
use App\Http\Resources\RunListResource;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\RunStep;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Command Centre (Overview).
 *
 * Every figure on this screen is derived from the workspace's own runs —
 * nothing on the page is hardcoded.
 */
class DashboardController extends Controller
{
    private const RECENT_RUN_LIMIT = 6;

    private const HUMAN_ATTENTION_LIMIT = 3;

    /**
     * Per-run spend the workspace is budgeted against, in USD. Used as the
     * denominator for the cost-efficiency term of the Fleet Trust Index.
     */
    private const TARGET_COST_PER_RUN_USD = 0.75;

    /** Run statuses that mean a human had to (or still has to) step in. */
    private const INTERVENTION_STATUSES = ['needs_review', 'failed'];

    /** Decision-graph canvas, in SVG user units. */
    private const GRAPH_WIDTH = 1000;

    private const GRAPH_HEIGHT = 700;

    /** Severity ordering used to pick the most urgent pending approval. */
    private const RISK_WEIGHTS = ['critical' => 3, 'high' => 2, 'medium' => 1, 'low' => 0];

    private const STATUS_TONES = [
        'completed' => 'completed',
        'needs_review' => 'review',
        'failed' => 'critical',
        'running' => 'info',
    ];

    public function index(): Response
    {
        $workspace = Workspace::query()->orderBy('id')->firstOrFail();

        /** @var Collection<int, Workflow> $workflows */
        $workflows = $workspace->workflows()->orderBy('id')->get();
        $workflowIds = $workflows->pluck('id')->all();

        /** @var Collection<int, WorkflowRun> $runs */
        $runs = WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->orderByDesc('created_at')
            ->get();

        $topApproval = $this->mostUrgentPendingApproval($workflowIds);

        return Inertia::render('Dashboard/Index', [
            'workspace' => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'tier' => $workspace->tier,
                'workflow_count' => $workflows->count(),
                'active_workflow_count' => $workflows->where('is_active', true)->count(),
            ],
            'kpis' => $this->kpis($workflowIds, $runs),
            'fleetTrust' => $this->fleetTrust($runs),
            'humanAttention' => ApprovalRequestResource::collection(
                $this->pendingApprovals($workflowIds)
            )->resolve(),
            'recentRuns' => RunListResource::collection(
                $this->recentRuns($workflowIds)
            )->resolve(),
            'decisionGraph' => $this->decisionGraph($workflows, $runs, $topApproval),
            'governanceLedger' => $this->governanceLedger($workspace->id, $workflowIds),
        ]);
    }

    /**
     * Headline KPI row: volume, reliability, latency and recent spend.
     *
     * @param  array<int, int>  $workflowIds
     * @param  Collection<int, WorkflowRun>  $runs
     * @return array<string, mixed>
     */
    private function kpis(array $workflowIds, Collection $runs): array
    {
        $total = $runs->count();
        $completed = $runs->where('status', 'completed')->count();
        $failed = $runs->where('status', 'failed')->count();
        $needsReview = $runs->where('status', 'needs_review')->count();

        $successRate = $total > 0 ? ($completed / $total) * 100 : 0.0;
        $avgLatencySeconds = $total > 0 ? $runs->avg('total_duration_ms') / 1000 : 0.0;

        $since = now()->subDay();
        $recent = $runs->filter(fn (WorkflowRun $run): bool => $run->created_at?->greaterThanOrEqualTo($since) ?? false);

        return [
            'total_executions' => [
                'value' => $total,
                'display' => number_format($total),
                'caption' => $failed.' failed · '.$needsReview.' in review',
            ],
            'success_rate' => [
                'value' => round($successRate, 1),
                'display' => number_format($successRate, 1).'%',
                'caption' => $completed.' of '.$total.' runs clean',
            ],
            'avg_latency' => [
                'value' => round($avgLatencySeconds, 2),
                'display' => number_format($avgLatencySeconds, 2).'s',
                'caption' => 'Mean end-to-end duration',
            ],
            'cost_24h' => [
                'value' => round((float) $recent->sum('total_cost_usd'), 4),
                'display' => '$'.number_format((float) $recent->sum('total_cost_usd'), 2),
                'caption' => $recent->count().' run'.($recent->count() === 1 ? '' : 's').' in last 24h',
            ],
        ];
    }

    /**
     * Fleet Trust Index — a single 0–100 confidence score for the agent fleet.
     *
     * FORMULA
     *   trust = 100 × ( 0.55 × reliability
     *                 + 0.30 × (1 − override_rate)
     *                 + 0.15 × cost_efficiency )
     *
     *   reliability      = completed runs ÷ total runs
     *                      — how often the fleet finishes unaided.
     *   override_rate    = runs needing a human (needs_review or failed) ÷ total runs
     *                      — how often autonomy breaks down; inverted so low is good.
     *   cost_efficiency  = min(1, target_cost_per_run ÷ average_cost_per_run)
     *                      — spend discipline against the budgeted per-run target
     *                      (self::TARGET_COST_PER_RUN_USD), capped at 1 so coming
     *                      in under budget cannot mask reliability problems.
     *
     * Weights favour reliability because a fleet that fails is untrustworthy
     * regardless of price; cost is the lightest term because it is a business
     * concern rather than a safety one.
     *
     * @param  Collection<int, WorkflowRun>  $runs
     * @return array<string, mixed>
     */
    private function fleetTrust(Collection $runs): array
    {
        $total = $runs->count();

        if ($total === 0) {
            return [
                'score' => 0,
                'band' => 'unknown',
                'components' => [],
            ];
        }

        $reliability = $runs->where('status', 'completed')->count() / $total;

        $overrideRate = $runs
            ->whereIn('status', self::INTERVENTION_STATUSES)
            ->count() / $total;

        $averageCost = (float) $runs->avg('total_cost_usd');
        $costEfficiency = $averageCost > 0
            ? min(1.0, self::TARGET_COST_PER_RUN_USD / $averageCost)
            : 1.0;

        $score = 100 * (
            0.55 * $reliability
            + 0.30 * (1 - $overrideRate)
            + 0.15 * $costEfficiency
        );

        $score = (int) round(max(0, min(100, $score)));

        return [
            'score' => $score,
            'band' => match (true) {
                $score >= 85 => 'strong',
                $score >= 70 => 'steady',
                $score >= 50 => 'watch',
                default => 'critical',
            },
            'components' => [
                [
                    'label' => 'Reliability',
                    'display' => number_format($reliability * 100, 1).'%',
                    'weight' => '55%',
                ],
                [
                    'label' => 'Autonomy',
                    'display' => number_format((1 - $overrideRate) * 100, 1).'%',
                    'weight' => '30%',
                ],
                [
                    'label' => 'Cost efficiency',
                    'display' => number_format($costEfficiency * 100, 1).'%',
                    'weight' => '15%',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, int>  $workflowIds
     * @return Collection<int, ApprovalRequest>
     */
    private function pendingApprovals(array $workflowIds): Collection
    {
        return $this->pendingApprovalQuery($workflowIds)
            ->limit(self::HUMAN_ATTENTION_LIMIT)
            ->get();
    }

    /**
     * @param  array<int, int>  $workflowIds
     */
    private function mostUrgentPendingApproval(array $workflowIds): ?ApprovalRequest
    {
        return $this->pendingApprovalQuery($workflowIds)->first();
    }

    /**
     * Pending approvals, most severe first, then most recently raised.
     *
     * @param  array<int, int>  $workflowIds
     * @return \Illuminate\Database\Eloquent\Builder<ApprovalRequest>
     */
    private function pendingApprovalQuery(array $workflowIds)
    {
        return ApprovalRequest::query()
            ->where('status', 'pending')
            ->whereHas('workflowRun', fn ($query) => $query->whereIn('workflow_id', $workflowIds))
            ->with(['runStep', 'workflowRun.workflow'])
            ->orderByRaw("CASE risk_level WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');
    }

    /**
     * @param  array<int, int>  $workflowIds
     * @return Collection<int, WorkflowRun>
     */
    private function recentRuns(array $workflowIds): Collection
    {
        return WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->with(['workflow', 'steps', 'auditEvents'])
            ->orderByDesc('created_at')
            ->limit(self::RECENT_RUN_LIMIT)
            ->get();
    }

    /**
     * Decision graph: one cluster per workflow, one node per run.
     *
     * Layout is computed server-side so the Vue component stays a pure
     * renderer. Clusters sit on an ellipse; within a cluster, runs are packed
     * into concentric rings with at-risk runs (needs_review / failed) placed on
     * the innermost ring — so visual proximity to a cluster's core literally
     * encodes risk. The flagged cluster is the one holding the most urgent
     * pending approval.
     *
     * @param  Collection<int, Workflow>  $workflows
     * @param  Collection<int, WorkflowRun>  $runs
     * @return array<string, mixed>
     */
    private function decisionGraph(Collection $workflows, Collection $runs, ?ApprovalRequest $topApproval): array
    {
        $runsByWorkflow = $runs->groupBy('workflow_id');
        $populated = $workflows->filter(fn (Workflow $w): bool => $runsByWorkflow->has($w->id))->values();
        $count = $populated->count();

        if ($count === 0) {
            return [
                'width' => self::GRAPH_WIDTH,
                'height' => self::GRAPH_HEIGHT,
                'clusters' => [],
                'links' => [],
                'callout' => null,
                'legend' => $this->graphLegend($runs),
            ];
        }

        $centreX = self::GRAPH_WIDTH / 2;
        $centreY = self::GRAPH_HEIGHT / 2;
        $spreadX = $count > 1 ? 300 : 0;
        $spreadY = $count > 1 ? 205 : 0;

        $flaggedWorkflowId = $topApproval?->workflowRun?->workflow_id;
        $flaggedRunId = $topApproval?->workflow_run_id;

        $clusters = [];

        foreach ($populated as $index => $workflow) {
            // Even placement around an ellipse, rotated so no cluster sits on
            // the vertical axis (keeps the 4-cluster case as a tidy quad).
            $angle = deg2rad(($index * 360 / $count) - 45);
            $cx = $centreX + ($spreadX * cos($angle));
            $cy = $centreY + ($spreadY * sin($angle));

            /** @var Collection<int, WorkflowRun> $clusterRuns */
            $clusterRuns = $runsByWorkflow->get($workflow->id);

            // At-risk runs first => they land on the inner rings. A single
            // composite key keeps this a key-extractor sort (an array of
            // callables would be read as comparators instead).
            $ordered = $clusterRuns
                ->sortBy(fn (WorkflowRun $run): string => sprintf(
                    '%d-%s',
                    in_array($run->status, self::INTERVENTION_STATUSES, true) ? 0 : 1,
                    $run->created_at?->format('YmdHis') ?? '',
                ))
                ->values();

            // The run behind the most urgent approval becomes the cluster core,
            // so the flagged cluster reads outward from the run that caused it.
            $coreRun = $ordered->first(
                fn (WorkflowRun $run): bool => $flaggedRunId !== null && $run->id === $flaggedRunId
            );

            if ($coreRun !== null) {
                $ordered = $ordered
                    ->reject(fn (WorkflowRun $run): bool => $run->id === $coreRun->id)
                    ->values();
            }

            $nodes = [];
            $maxRingRadius = 0.0;

            foreach ($ordered as $position => $run) {
                [$ring, $slot, $slots] = $this->ringSlot($position);
                $ringRadius = 44 + ($ring * 34);
                $maxRingRadius = max($maxRingRadius, $ringRadius);

                // Offset each ring so spokes from different rings don't overlap.
                $nodeAngle = deg2rad(($slot * 360 / $slots) + ($ring * 20));
                $atRisk = in_array($run->status, self::INTERVENTION_STATUSES, true);
                $isFlagged = false;

                $nodes[] = [
                    'id' => $run->id,
                    'run_key' => $run->run_key,
                    'status' => $run->status,
                    'tone' => self::STATUS_TONES[$run->status] ?? 'info',
                    'at_risk' => $atRisk,
                    'flagged' => $isFlagged,
                    'cost_label' => $run->total_cost_usd === null
                        ? '—'
                        : '$'.number_format((float) $run->total_cost_usd, 4),
                    'x' => round($cx + ($ringRadius * cos($nodeAngle)), 2),
                    'y' => round($cy + ($ringRadius * sin($nodeAngle)), 2),
                    'r' => $isFlagged ? 10.0 : ($atRisk ? 7.0 : 5.0),
                ];
            }

            $core = $coreRun === null ? null : [
                'id' => $coreRun->id,
                'run_key' => $coreRun->run_key,
                'status' => $coreRun->status,
                'tone' => self::STATUS_TONES[$coreRun->status] ?? 'info',
                'at_risk' => in_array($coreRun->status, self::INTERVENTION_STATUSES, true),
                'flagged' => true,
                'cost_label' => $coreRun->total_cost_usd === null
                    ? '—'
                    : '$'.number_format((float) $coreRun->total_cost_usd, 4),
                'x' => round($cx, 2),
                'y' => round($cy, 2),
                'r' => 11.0,
            ];

            $atRiskCount = $clusterRuns->whereIn('status', self::INTERVENTION_STATUSES)->count();
            $total = $clusterRuns->count();

            $clusters[] = [
                'id' => $workflow->id,
                'label' => $workflow->name,
                'slug' => $workflow->slug,
                'x' => round($cx, 2),
                'y' => round($cy, 2),
                'radius' => round($maxRingRadius + 26, 2),
                'run_count' => $total,
                'at_risk_count' => $atRiskCount,
                'risk_ratio' => $total > 0 ? round($atRiskCount / $total, 4) : 0.0,
                'risk_label' => $atRiskCount.'/'.$total.' at risk',
                'flagged' => $flaggedWorkflowId !== null && $workflow->id === $flaggedWorkflowId,
                'core' => $core,
                'nodes' => $nodes,
            ];
        }

        return [
            'width' => self::GRAPH_WIDTH,
            'height' => self::GRAPH_HEIGHT,
            'clusters' => $clusters,
            'links' => $this->clusterLinks($clusters),
            'callout' => $this->graphCallout($clusters, $topApproval),
            'legend' => $this->graphLegend($runs),
        ];
    }

    /**
     * Ring packing for a node at `$position` within its cluster.
     * Ring k holds 6 + 6k slots, so rings stay visually uncrowded.
     *
     * @return array{0: int, 1: int, 2: int} [ring index, slot index, slots in ring]
     */
    private function ringSlot(int $position): array
    {
        $ring = 0;

        while (true) {
            $slots = 6 + ($ring * 6);

            if ($position < $slots) {
                return [$ring, $position, $slots];
            }

            $position -= $slots;
            $ring++;
        }
    }

    /**
     * Risk-proximity links: every cluster is tethered to the flagged cluster so
     * the blast radius of the flagged workflow is legible. With nothing
     * flagged, clusters are chained in a ring instead.
     *
     * @param  array<int, array<string, mixed>>  $clusters
     * @return array<int, array<string, mixed>>
     */
    private function clusterLinks(array $clusters): array
    {
        $count = count($clusters);

        if ($count < 2) {
            return [];
        }

        $flaggedIndex = null;

        foreach ($clusters as $index => $cluster) {
            if ($cluster['flagged'] === true) {
                $flaggedIndex = $index;
                break;
            }
        }

        $links = [];

        if ($flaggedIndex === null) {
            foreach ($clusters as $index => $cluster) {
                $next = $clusters[($index + 1) % $count];
                $links[] = [
                    'x1' => $cluster['x'], 'y1' => $cluster['y'],
                    'x2' => $next['x'], 'y2' => $next['y'],
                    'hot' => false,
                ];
            }

            return $links;
        }

        $origin = $clusters[$flaggedIndex];

        foreach ($clusters as $index => $cluster) {
            if ($index === $flaggedIndex) {
                continue;
            }

            $links[] = [
                'x1' => $origin['x'], 'y1' => $origin['y'],
                'x2' => $cluster['x'], 'y2' => $cluster['y'],
                'hot' => $cluster['at_risk_count'] > 0,
            ];
        }

        return $links;
    }

    /**
     * @param  array<int, array<string, mixed>>  $clusters
     * @return array<string, mixed>|null
     */
    private function graphCallout(array $clusters, ?ApprovalRequest $topApproval): ?array
    {
        foreach ($clusters as $cluster) {
            if ($cluster['flagged'] !== true) {
                continue;
            }

            return [
                'cluster_id' => $cluster['id'],
                'title' => 'Flagged high-risk cluster',
                'workflow' => $cluster['label'],
                'x' => $cluster['x'],
                'y' => $cluster['y'],
                'radius' => $cluster['radius'],
                'detail' => sprintf(
                    '%d of %d runs unresolved · %s approval open on #%s',
                    $cluster['at_risk_count'],
                    $cluster['run_count'],
                    $topApproval?->risk_level ?? 'pending',
                    $topApproval?->workflowRun?->run_key ?? '—',
                ),
                'run_key' => $topApproval?->workflowRun?->run_key,
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, WorkflowRun>  $runs
     * @return array<int, array<string, mixed>>
     */
    private function graphLegend(Collection $runs): array
    {
        $labels = [
            'completed' => 'Completed',
            'needs_review' => 'Needs review',
            'failed' => 'Failed',
        ];

        $legend = [];

        foreach ($labels as $status => $label) {
            $legend[] = [
                'status' => $status,
                'label' => $label,
                'tone' => self::STATUS_TONES[$status] ?? 'info',
                'count' => $runs->where('status', $status)->count(),
            ];
        }

        return $legend;
    }

    /**
     * Governance ledger: what the workspace can prove it decided.
     *
     * @param  array<int, int>  $workflowIds
     * @return array<string, mixed>
     */
    private function governanceLedger(int $workspaceId, array $workflowIds): array
    {
        $inWorkspace = fn ($query) => $query->whereHas(
            'workflowRun',
            fn ($runQuery) => $runQuery->whereIn('workflow_id', $workflowIds)
        );

        // A decision is "signed" when a human resolved an approval, or when an
        // approval gate cleared on its own (auto-approved).
        $resolvedApprovals = ApprovalRequest::query()
            ->where('status', '!=', 'pending')
            ->whereHas('workflowRun', fn ($query) => $query->whereIn('workflow_id', $workflowIds))
            ->count();

        $autoApproved = RunStep::query()
            ->where('step_type', 'approval_gate')
            ->where('status', 'completed')
            ->where($inWorkspace)
            ->count();

        // Every reasoning step and every gate is a policy evaluation.
        $policiesEvaluated = RunStep::query()
            ->whereIn('step_type', ['llm_reasoning', 'approval_gate'])
            ->where($inWorkspace)
            ->count();

        $auditEvents = AuditEvent::query()
            ->where('workspace_id', $workspaceId)
            ->count();

        return [
            'decisions_signed' => [
                'label' => 'Decisions signed',
                'value' => $resolvedApprovals + $autoApproved,
                'display' => number_format($resolvedApprovals + $autoApproved),
                'caption' => $resolvedApprovals.' human · '.$autoApproved.' auto-approved',
            ],
            'policies_evaluated' => [
                'label' => 'Policies evaluated',
                'value' => $policiesEvaluated,
                'display' => number_format($policiesEvaluated),
                'caption' => 'Reasoning steps + approval gates',
            ],
            'audit_export' => [
                'label' => 'Audit export',
                'value' => 'ready',
                'display' => 'Ready',
                'caption' => number_format($auditEvents).' events sealed',
            ],
        ];
    }
}
