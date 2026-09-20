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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Command Centre (Overview).
 *
 * Every figure on this screen is derived from the workspace's own runs —
 * nothing on the page is hardcoded.
 *
 * Every window is measured back from the newest run in the workspace rather
 * than wall-clock now(), so the screen keeps its shape whenever the seeded
 * dataset is demoed instead of silently decaying to zero.
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

    /** Room the cluster name and the flagged run key need outside a ring. */
    private const GRAPH_LABEL_ABOVE = 28;

    private const GRAPH_LABEL_BELOW = 24;

    private const STATUS_TONES = [
        'completed' => 'completed',
        'needs_review' => 'review',
        'failed' => 'critical',
        'running' => 'info',
    ];

    /**
     * Selectable observation windows, measured back from the newest run.
     * `cost_label` is the heading the spend KPI carries for that window.
     *
     * @var array<string, array{label: string, days: int, cost_label: string}>
     */
    private const RANGES = [
        '24h' => ['label' => 'Last 24 hours', 'days' => 1, 'cost_label' => '24h AI Cost'],
        '7d' => ['label' => 'Last 7 days', 'days' => 7, 'cost_label' => '7d AI Cost'],
        '14d' => ['label' => 'Last 14 days', 'days' => 14, 'cost_label' => '14d AI Cost'],
        '30d' => ['label' => 'Last 30 days', 'days' => 30, 'cost_label' => '30d AI Cost'],
    ];

    private const DEFAULT_RANGE = '14d';

    public function index(Request $request): Response
    {
        $workspace = Workspace::query()->orderBy('id')->firstOrFail();

        /** @var Collection<int, Workflow> $workflows */
        $workflows = $workspace->workflows()->orderBy('id')->get();
        $workflowIds = $workflows->pluck('id')->all();

        $range = $this->range($request);
        $anchor = $this->anchorDate($workflowIds);
        $since = $anchor->copy()->subDays($range['days']);

        /** @var Collection<int, WorkflowRun> $runs */
        $runs = WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->whereBetween('created_at', [$since, $anchor])
            ->orderByDesc('created_at')
            ->get();

        // Pending approvals are current state, not history: they stay in view
        // however far back the window reaches. The graph may only *flag* one
        // whose run is actually on screen, though.
        $topApproval = $this->mostUrgentPendingApproval($workflowIds);
        $graphApproval = $topApproval !== null && $runs->contains('id', $topApproval->workflow_run_id)
            ? $topApproval
            : null;

        return Inertia::render('Dashboard/Index', [
            'workspace' => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'tier' => $workspace->tier,
                'workflow_count' => $workflows->count(),
                'active_workflow_count' => $workflows->where('is_active', true)->count(),
            ],
            'range' => [
                'key' => $range['key'],
                'label' => $range['label'],
                'days' => $range['days'],
                'anchor_label' => $anchor->format('M j · H:i'),
                'options' => $this->rangeOptions(),
            ],
            'kpis' => $this->kpis($runs, $range),
            'fleetTrust' => $this->fleetTrust($runs),
            'humanAttention' => ApprovalRequestResource::collection(
                $this->pendingApprovals($workflowIds)
            )->resolve(),
            'recentRuns' => RunListResource::collection(
                $this->recentRuns($runs)
            )->resolve(),
            'decisionGraph' => $this->decisionGraph($workflows, $runs, $graphApproval),
            'governanceLedger' => $this->governanceLedger($workspace->id, $workflowIds),
        ]);
    }

    /**
     * The selected observation window, falling back to the default when the
     * query string names one that does not exist.
     *
     * @return array{key: string, label: string, days: int, cost_label: string}
     */
    private function range(Request $request): array
    {
        $key = $request->query('range');
        $key = is_string($key) && isset(self::RANGES[$key]) ? $key : self::DEFAULT_RANGE;

        return [...self::RANGES[$key], 'key' => $key];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function rangeOptions(): array
    {
        $options = [];

        foreach (self::RANGES as $key => $range) {
            $options[] = ['key' => $key, 'label' => $range['label']];
        }

        return $options;
    }

    /**
     * Newest run in the workspace — the point every window is measured back
     * from. Falls back to now() only when the workspace has no runs at all.
     *
     * @param  array<int, int>  $workflowIds
     */
    private function anchorDate(array $workflowIds): Carbon
    {
        $latest = WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->max('created_at');

        return $latest === null ? now() : Carbon::parse($latest);
    }

    /**
     * Headline KPI row: volume, reliability, latency and spend across the
     * selected window.
     *
     * @param  Collection<int, WorkflowRun>  $runs
     * @param  array{key: string, label: string, days: int, cost_label: string}  $range
     * @return array<string, mixed>
     */
    private function kpis(Collection $runs, array $range): array
    {
        $total = $runs->count();
        $completed = $runs->where('status', 'completed')->count();
        $failed = $runs->where('status', 'failed')->count();
        $needsReview = $runs->where('status', 'needs_review')->count();

        $successRate = $total > 0 ? ($completed / $total) * 100 : 0.0;
        $avgLatencySeconds = $total > 0 ? $runs->avg('total_duration_ms') / 1000 : 0.0;
        $spend = (float) $runs->sum('total_cost_usd');

        $windowCaption = strtolower($range['label']);

        return [
            'total_executions' => [
                'label' => 'Total Executions',
                'value' => $total,
                'display' => number_format($total),
                'caption' => $failed.' failed · '.$needsReview.' in review',
            ],
            'success_rate' => [
                'label' => 'Success Rate',
                'value' => round($successRate, 1),
                'display' => $total === 0 ? '—' : number_format($successRate, 1).'%',
                'caption' => $completed.' of '.$total.' runs clean',
            ],
            'avg_latency' => [
                'label' => 'Avg Latency',
                'value' => round($avgLatencySeconds, 2),
                'display' => $total === 0 ? '—' : number_format($avgLatencySeconds, 2).'s',
                'caption' => 'Mean end-to-end duration',
            ],
            'cost_window' => [
                'label' => $range['cost_label'],
                'value' => round($spend, 4),
                'display' => '$'.number_format($spend, 2),
                'caption' => $total.' run'.($total === 1 ? '' : 's').' · '.$windowCaption,
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
     * The newest runs inside the window, with the relations the list resource
     * reads from eager-loaded.
     *
     * @param  Collection<int, WorkflowRun>  $runs
     * @return Collection<int, WorkflowRun>
     */
    private function recentRuns(Collection $runs): Collection
    {
        $recent = $runs->take(self::RECENT_RUN_LIMIT);
        $recent->loadMissing(['workflow', 'steps', 'auditEvents']);

        return $recent->values();
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
                'bounds' => $this->graphBounds([]),
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

                $nodes[] = [
                    'id' => $run->id,
                    'run_key' => $run->run_key,
                    'status' => $run->status,
                    'tone' => self::STATUS_TONES[$run->status] ?? 'info',
                    'at_risk' => $atRisk,
                    // Only the cluster core is ever flagged; ring nodes are not.
                    'flagged' => false,
                    'cost_label' => $run->total_cost_usd === null
                        ? '—'
                        : '$'.number_format((float) $run->total_cost_usd, 4),
                    'x' => round($cx + ($ringRadius * cos($nodeAngle)), 2),
                    'y' => round($cy + ($ringRadius * sin($nodeAngle)), 2),
                    'r' => $atRisk ? 7.0 : 5.0,
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
            'bounds' => $this->graphBounds($clusters),
            'clusters' => $clusters,
            'links' => $this->clusterLinks($clusters),
            'callout' => $this->graphCallout($clusters, $topApproval),
            'legend' => $this->graphLegend($runs),
        ];
    }

    /**
     * The box the drawing actually occupies, labels included.
     *
     * The canvas is a fixed 1000×700, but a panel is whatever height its
     * neighbours make it — fitting the whole canvas into that box letterboxed
     * the graph. The client fits *this* box instead and grows it to the
     * panel's own aspect ratio, so the drawing fills the panel without being
     * stretched or cropped.
     *
     * @param  array<int, array<string, mixed>>  $clusters
     * @return array{x: float, y: float, width: float, height: float}
     */
    private function graphBounds(array $clusters): array
    {
        if ($clusters === []) {
            return ['x' => 0.0, 'y' => 0.0, 'width' => (float) self::GRAPH_WIDTH, 'height' => (float) self::GRAPH_HEIGHT];
        }

        $left = $right = $clusters[0]['x'];
        $top = $bottom = $clusters[0]['y'];

        foreach ($clusters as $cluster) {
            $left = min($left, $cluster['x'] - $cluster['radius']);
            $right = max($right, $cluster['x'] + $cluster['radius']);
            // The workflow name sits above the ring, the risk ratio just inside it.
            $top = min($top, $cluster['y'] - $cluster['radius'] - self::GRAPH_LABEL_ABOVE);
            $bottom = max($bottom, $cluster['y'] + $cluster['radius']);

            if ($cluster['core'] !== null) {
                // ...and a flagged core carries its run key underneath.
                $bottom = max($bottom, $cluster['core']['y'] + $cluster['core']['r'] + self::GRAPH_LABEL_BELOW);
            }
        }

        $pad = 12.0;

        return [
            'x' => round($left - $pad, 2),
            'y' => round($top - $pad, 2),
            'width' => round(($right - $left) + (2 * $pad), 2),
            'height' => round(($bottom - $top) + (2 * $pad), 2),
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
     * Cumulative across the workspace's whole history — an audit trail does not
     * shrink when you narrow the view — so it is deliberately not windowed.
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
