<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cover — the platform's entry surface at the root path.
 *
 * Public: it is the first thing a signed-out visitor lands on, so every figure
 * is a workspace-wide aggregate rather than anything user-scoped, and the whole
 * screen has to render against an empty database.
 */
class CoverController extends Controller
{
    /** Run statuses that mean autonomy broke down and a human was pulled in. */
    private const INTERVENTION_STATUSES = ['needs_review', 'failed'];

    /** Constellation canvas is a 100x100 viewBox centred on the core. */
    private const ORBIT_CENTRE = 50.0;

    /** Busiest workflow sits closest to the core, quietest on the outer ring. */
    private const ORBIT_INNER = 27.0;

    private const ORBIT_OUTER = 43.0;

    private const NODE_MIN_RADIUS = 3.0;

    private const NODE_MAX_RADIUS = 5.4;

    /** Share of a workflow's runs needing a human before its node turns amber/red. */
    private const NODE_CRITICAL_SHARE = 0.25;

    public function index(): Response
    {
        $workspace = Workspace::query()->orderBy('id')->first();

        $workflows = $workspace === null
            ? new EloquentCollection
            : $workspace->workflows()->orderBy('id')->get();

        $workflowIds = $workflows->pluck('id')->all();
        $tallies = $this->talliesByWorkflow($workflowIds);

        $total = (int) $tallies->sum('runs');
        $completed = (int) $tallies->where('status', 'completed')->sum('runs');
        $interventions = (int) $tallies->whereIn('status', self::INTERVENTION_STATUSES)->sum('runs');
        $tokens = (int) $tallies->sum('tokens');
        $spend = (float) $tallies->sum('cost');

        $pending = $this->pendingApprovalCount($workflowIds);
        $decisions = $workspace === null
            ? 0
            : AuditEvent::query()->where('workspace_id', $workspace->id)->count();

        $latest = $this->latestRun($workflowIds);

        return Inertia::render('Cover/Index', [
            'workspace' => $workspace === null ? null : [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'tier' => $workspace->tier,
                'workflow_count' => $workflows->count(),
                'active_workflow_count' => $workflows->where('is_active', true)->count(),
            ],
            'signals' => $this->signals($total, $completed, $interventions, $decisions, $pending),
            'pulse' => [
                'live' => $latest !== null,
                'latest_run_key' => $latest?->run_key,
                'latest_run_label' => $latest === null
                    ? 'No runs recorded'
                    : Carbon::parse($latest->created_at)->format('M j · H:i'),
                'tokens_display' => $this->compactNumber($tokens),
                'spend_display' => '$'.number_format($spend, 2),
                'autonomy_display' => $total === 0
                    ? '—'
                    : number_format((1 - ($interventions / $total)) * 100, 1).'%',
            ],
            'constellation' => $this->constellation($workflows, $tallies, $total),
            'entries' => $this->entries($total, $workflows->count(), $pending),
        ]);
    }

    /**
     * One row per (workflow, status) with its run count, tokens and spend —
     * the single aggregate every figure on the screen is folded out of.
     *
     * @param  array<int, int>  $workflowIds
     * @return Collection<int, object>
     */
    private function talliesByWorkflow(array $workflowIds): Collection
    {
        if ($workflowIds === []) {
            return collect();
        }

        return WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->selectRaw('workflow_id, status, COUNT(*) as runs')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as tokens')
            ->selectRaw('COALESCE(SUM(total_cost_usd), 0) as cost')
            ->groupBy('workflow_id', 'status')
            ->get()
            ->map(fn ($row) => (object) [
                'workflow_id' => (int) $row->workflow_id,
                'status' => (string) $row->status,
                'runs' => (int) $row->runs,
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ]);
    }

    /**
     * @param  array<int, int>  $workflowIds
     */
    private function pendingApprovalCount(array $workflowIds): int
    {
        if ($workflowIds === []) {
            return 0;
        }

        return ApprovalRequest::query()
            ->where('status', 'pending')
            ->whereIn(
                'workflow_run_id',
                WorkflowRun::query()->select('id')->whereIn('workflow_id', $workflowIds)
            )
            ->count();
    }

    /**
     * @param  array<int, int>  $workflowIds
     */
    private function latestRun(array $workflowIds): ?WorkflowRun
    {
        if ($workflowIds === []) {
            return null;
        }

        return WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function signals(
        int $total,
        int $completed,
        int $interventions,
        int $decisions,
        int $pending,
    ): array {
        return [
            [
                'key' => 'runs',
                'label' => 'Runs Governed',
                'display' => number_format($total),
                'caption' => $total === 0
                    ? 'Awaiting first execution'
                    : $interventions.' needed a human',
                'accent' => 'cyan',
            ],
            [
                'key' => 'success',
                'label' => 'Clean Completion',
                'display' => $total === 0 ? '—' : number_format(($completed / $total) * 100, 1).'%',
                'caption' => $completed.' of '.$total.' runs clean',
                'accent' => 'emerald',
            ],
            [
                'key' => 'decisions',
                'label' => 'Decisions Logged',
                'display' => number_format($decisions),
                'caption' => 'Immutable audit trail',
                'accent' => 'violet',
            ],
            [
                'key' => 'pending',
                'label' => 'Awaiting Review',
                'display' => number_format($pending),
                'caption' => $pending === 0 ? 'Queue clear' : 'Blocking autonomous execution',
                'accent' => 'amber',
            ],
        ];
    }

    /**
     * Decision-graph motif: each workflow orbits the workspace core, placed by
     * volume (busiest innermost) and toned by how often it needs a human.
     *
     * @param  EloquentCollection<int, Workflow>  $workflows
     * @param  Collection<int, object>  $tallies
     * @return array<string, mixed>
     */
    private function constellation(
        EloquentCollection $workflows,
        Collection $tallies,
        int $total,
    ): array {
        $ranked = $workflows
            ->map(function (Workflow $workflow) use ($tallies): array {
                $rows = $tallies->where('workflow_id', $workflow->id);
                $runs = (int) $rows->sum('runs');
                $flagged = (int) $rows->whereIn('status', self::INTERVENTION_STATUSES)->sum('runs');

                return [
                    'id' => $workflow->id,
                    'label' => $workflow->name,
                    'slug' => $workflow->slug,
                    'active' => (bool) $workflow->is_active,
                    'runs' => $runs,
                    'flagged' => $flagged,
                ];
            })
            ->sortByDesc('runs')
            ->values();

        $count = $ranked->count();
        $busiest = max(1, (int) $ranked->max('runs'));

        $nodes = $ranked
            ->map(function (array $entry, int $index) use ($count, $busiest): array {
                $orbit = $count === 1
                    ? self::ORBIT_INNER
                    : self::ORBIT_INNER + (($index / ($count - 1)) * (self::ORBIT_OUTER - self::ORBIT_INNER));

                // Start at 12 o'clock and step clockwise so the busiest workflow
                // always reads first, whatever the workspace holds.
                $angle = deg2rad(-90 + ($index * (360 / $count)));
                $share = $entry['runs'] / $busiest;
                $x = self::ORBIT_CENTRE + ($orbit * cos($angle));
                $flaggedShare = $entry['runs'] > 0 ? $entry['flagged'] / $entry['runs'] : 0.0;

                return [
                    'id' => $entry['id'],
                    'label' => $entry['label'],
                    'short_label' => Str::limit($entry['label'], 18),
                    'slug' => $entry['slug'],
                    'runs' => $entry['runs'],
                    'runs_label' => $entry['runs'].' run'.($entry['runs'] === 1 ? '' : 's'),
                    'orbit' => round($orbit, 2),
                    'x' => round($x, 2),
                    'y' => round(self::ORBIT_CENTRE + ($orbit * sin($angle)), 2),
                    // Labels read outwards from the core so they never cross it
                    // or the neighbouring orbit.
                    'anchor' => match (true) {
                        $x > self::ORBIT_CENTRE + 1 => 'start',
                        $x < self::ORBIT_CENTRE - 1 => 'end',
                        default => 'middle',
                    },
                    'radius' => round(
                        self::NODE_MIN_RADIUS + ($share * (self::NODE_MAX_RADIUS - self::NODE_MIN_RADIUS)),
                        2
                    ),
                    'tone' => match (true) {
                        ! $entry['active'] => 'info',
                        $flaggedShare >= self::NODE_CRITICAL_SHARE => 'critical',
                        $flaggedShare > 0 => 'review',
                        default => 'completed',
                    },
                ];
            })
            ->all();

        return [
            'nodes' => $nodes,
            'orbits' => collect($nodes)->pluck('orbit')->unique()->values()->all(),
            'core_display' => number_format($total),
            'caption' => $nodes === []
                ? 'No workflows seeded — the graph fills as runs arrive'
                : $count.' workflow'.($count === 1 ? '' : 's').' orbiting · sized by run volume',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function entries(int $total, int $workflowCount, int $pending): array
    {
        return [
            [
                'key' => 'command-centre',
                'label' => 'Command Centre',
                'description' => 'Fleet trust, decision graph and live execution pulse.',
                'href' => '/dashboard',
                'meta' => $workflowCount.' workflow'.($workflowCount === 1 ? '' : 's').' monitored',
            ],
            [
                'key' => 'runs',
                'label' => 'Runs Explorer',
                'description' => 'Every execution, filterable down to the tool call.',
                'href' => '/runs',
                'meta' => number_format($total).' trace'.($total === 1 ? '' : 's').' indexed',
            ],
            [
                'key' => 'reviews',
                'label' => 'Review Queue',
                'description' => 'Human-in-the-loop desk for intercepted actions.',
                'href' => '/reviews',
                'meta' => $pending === 0 ? 'Queue clear' : $pending.' awaiting decision',
            ],
        ];
    }

    private function compactNumber(int $value): string
    {
        return match (true) {
            $value >= 1_000_000 => number_format($value / 1_000_000, 1).'M',
            $value >= 1_000 => number_format($value / 1_000, 1).'K',
            default => number_format($value),
        };
    }
}
