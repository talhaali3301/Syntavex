<?php

namespace App\Http\Controllers;

use App\Http\Resources\RunRowResource;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs Explorer.
 *
 * Filtering, the status distribution, the 14-day volume chart and pagination
 * are all driven by the workflow_runs table. The date window is anchored to
 * the most recent run in the database rather than wall-clock now(), so the
 * screen stays meaningful whenever the app is demoed.
 */
class RunsController extends Controller
{
    private const PER_PAGE = 11;

    /** Width of both the default date filter and the volume chart, in days. */
    private const WINDOW_DAYS = 14;

    private const STATUSES = ['completed', 'needs_review', 'failed'];

    private const STATUS_META = [
        'completed' => ['label' => 'COMPLETED', 'tone' => 'completed'],
        'needs_review' => ['label' => 'NEEDS REVIEW', 'tone' => 'review'],
        'failed' => ['label' => 'FAILED', 'tone' => 'critical'],
    ];

    public function index(Request $request): Response
    {
        $workspace = Workspace::query()->orderBy('id')->firstOrFail();
        $workflowIds = $workspace->workflows()->orderBy('id')->pluck('id')->all();

        $filters = $this->filters($request, $workflowIds);

        // Scope = every filter except status. Chip counts read from here, so
        // each chip shows exactly how many rows clicking it would return.
        $scope = $this->baseQuery($workflowIds, $filters)
            ->whereBetween('workflow_runs.created_at', [$filters['from'], $filters['to']]);

        $scopeTotal = (clone $scope)->count();
        $statusCounts = $this->statusCounts(clone $scope);

        // Everything in the date window, ignoring workflow/search/status, so
        // "Showing X of Y" shows how much the filters actually narrowed things.
        $rangeTotal = WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->count();

        $filtered = (clone $scope);

        if ($filters['status'] !== 'all') {
            $filtered->where('workflow_runs.status', $filters['status']);
        }

        $stats = (clone $filtered)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(total_duration_ms) as avg_duration_ms')
            ->first();

        // Spend across the 24h ending at the anchor, matching the mockup's
        // "$x / 24h" framing. Anchored like every other window on this screen
        // so it stays meaningful on seeded data instead of reading $0.00.
        $cost24h = (float) (clone $filtered)
            ->where('workflow_runs.created_at', '>=', $filters['anchor']->copy()->subDay())
            ->sum('total_cost_usd');

        $runs = (clone $filtered)
            ->with(['workflow', 'steps', 'auditEvents', 'approvalRequests'])
            ->orderByDesc('workflow_runs.created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rows = RunRowResource::collection($runs->getCollection())->resolve();

        return Inertia::render('Runs/Index', [
            'workspace' => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'tier' => $workspace->tier,
            ],
            'filters' => [
                'status' => $filters['status'],
                'workflow' => $filters['workflow'],
                'search' => $filters['search'],
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'range_label' => $filters['from']->format('M d').' → '.$filters['to']->format('M d'),
                'is_default' => $filters['is_default'],
            ],
            'statusChips' => $this->statusChips($scopeTotal, $statusCounts),
            'distribution' => $this->distribution($scopeTotal, $statusCounts, $stats, $cost24h),
            'volume' => $this->volume($workflowIds, $filters),
            'runs' => $rows,
            'pagination' => $this->pagination($runs),
            'workflowOptions' => $this->workflowOptions($workflowIds),
            'expandedRunId' => $this->flagshipRunId($rows),
            'showing' => ['filtered' => $runs->total(), 'total' => $rangeTotal],
        ]);
    }

    /**
     * Placeholder target for "Open Run Inspector". The real inspector is
     * Phase 5; this exists so the link and route resolve today.
     */
    public function show(WorkflowRun $run): Response
    {
        $run->load('workflow');

        return Inertia::render('Runs/Show', [
            'run' => [
                'id' => $run->id,
                'run_key' => $run->run_key,
                'workflow' => $run->workflow?->name,
                'status' => $run->status,
            ],
        ]);
    }

    /** CSV of the currently filtered runs (every page, not just the visible one). */
    public function export(Request $request): StreamedResponse
    {
        $workspace = Workspace::query()->orderBy('id')->firstOrFail();
        $workflowIds = $workspace->workflows()->orderBy('id')->pluck('id')->all();
        $filters = $this->filters($request, $workflowIds);

        $query = $this->baseQuery($workflowIds, $filters)
            ->whereBetween('workflow_runs.created_at', [$filters['from'], $filters['to']]);

        if ($filters['status'] !== 'all') {
            $query->where('workflow_runs.status', $filters['status']);
        }

        $filename = sprintf('syntavex-runs-%s.csv', now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'run_key', 'workflow', 'status', 'started_at',
                'duration_ms', 'tokens', 'cost_usd', 'error_message',
            ]);

            $query->with('workflow')
                ->orderByDesc('workflow_runs.created_at')
                ->chunk(200, function (Collection $chunk) use ($handle): void {
                    foreach ($chunk as $run) {
                        fputcsv($handle, [
                            $run->run_key,
                            $run->workflow?->name,
                            $run->status,
                            $run->created_at?->toIso8601String(),
                            $run->total_duration_ms,
                            $run->total_tokens,
                            $run->total_cost_usd,
                            $run->error_message,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolves request filters, defaulting the date window to the last
     * self::WINDOW_DAYS days *relative to the newest seeded run*.
     *
     * @param  array<int, int>  $workflowIds
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $workflowIds): array
    {
        $anchor = $this->anchorDate($workflowIds);
        $defaultFrom = $anchor->copy()->subDays(self::WINDOW_DAYS - 1)->startOfDay();
        $defaultTo = $anchor->copy()->endOfDay();

        $from = $this->parseDate($request->query('from'))?->startOfDay() ?? $defaultFrom;
        $to = $this->parseDate($request->query('to'))?->endOfDay() ?? $defaultTo;

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $status = (string) $request->query('status', 'all');

        if (! in_array($status, [...self::STATUSES, 'all'], true)) {
            $status = 'all';
        }

        $workflow = $request->query('workflow');
        $workflow = is_numeric($workflow) && in_array((int) $workflow, $workflowIds, true)
            ? (int) $workflow
            : null;

        $search = trim((string) $request->query('search', ''));

        return [
            'status' => $status,
            'workflow' => $workflow,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'anchor' => $anchor,
            'is_default' => $status === 'all'
                && $workflow === null
                && $search === ''
                && $from->equalTo($defaultFrom)
                && $to->equalTo($defaultTo),
        ];
    }

    /** Newest run in the workspace — the anchor every date window hangs off. */
    private function anchorDate(array $workflowIds): Carbon
    {
        $latest = WorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->max('created_at');

        return $latest === null ? now() : Carbon::parse($latest);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Workspace runs narrowed by workflow and search, but not by status or
     * date — those are layered on by the caller.
     *
     * @param  array<int, int>  $workflowIds
     * @param  array<string, mixed>  $filters
     * @return Builder<WorkflowRun>
     */
    private function baseQuery(array $workflowIds, array $filters): Builder
    {
        $query = WorkflowRun::query()->whereIn('workflow_id', $workflowIds);

        if ($filters['workflow'] !== null) {
            $query->where('workflow_id', $filters['workflow']);
        }

        // Live search across trace (run key) and workflow name.
        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';

            $query->where(function ($inner) use ($term): void {
                $inner->where('run_key', 'like', $term)
                    ->orWhereHas('workflow', fn ($w) => $w->where('name', 'like', $term));
            });
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(Builder $scope): array
    {
        $rows = $scope->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];

        foreach (self::STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function statusChips(int $scopeTotal, array $counts): array
    {
        $chips = [[
            'key' => 'all',
            'label' => 'ALL',
            'count' => $scopeTotal,
            'tone' => 'accent',
        ]];

        foreach (self::STATUSES as $status) {
            $chips[] = [
                'key' => $status,
                'label' => self::STATUS_META[$status]['label'],
                'count' => $counts[$status],
                'tone' => self::STATUS_META[$status]['tone'],
            ];
        }

        return $chips;
    }

    /**
     * Status split across the filtered date range, plus the averages that sit
     * on the right of the strip.
     *
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function distribution(int $scopeTotal, array $counts, mixed $stats, float $cost24h): array
    {
        $segments = [];

        foreach (self::STATUSES as $status) {
            $count = $counts[$status];
            $segments[] = [
                'status' => $status,
                'label' => strtolower(str_replace('_', ' ', $status)),
                'tone' => self::STATUS_META[$status]['tone'],
                'count' => $count,
                'percent' => $scopeTotal > 0 ? round(($count / $scopeTotal) * 100, 1) : 0.0,
            ];
        }

        $successRate = $scopeTotal > 0 ? ($counts['completed'] / $scopeTotal) * 100 : 0.0;
        $filteredTotal = (int) ($stats->total ?? 0);
        $avgDuration = (float) ($stats->avg_duration_ms ?? 0);

        return [
            'total' => $scopeTotal,
            'window_days' => self::WINDOW_DAYS,
            'success_rate' => round($successRate, 1),
            'success_label' => number_format($successRate, 1).'% success',
            'segments' => $segments,
            // Both figures describe the filtered set; with nothing in view they
            // are meaningless, so show a dash rather than a confident zero.
            // Two decimals on each, so they read as one pair.
            'avg_duration_label' => $filteredTotal === 0
                ? 'avg —'
                : 'avg '.number_format($avgDuration / 1000, 2).'s',
            'cost_window_label' => $filteredTotal === 0
                ? '— / 24h'
                : '$'.number_format($cost24h, 2).' / 24h',
            'filtered_total' => $filteredTotal,
        ];
    }

    /**
     * Daily run counts across a fixed 14-day window ending on the anchor date.
     * Bars are normalised against the busiest day so the tallest is always
     * full height.
     *
     * @param  array<int, int>  $workflowIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function volume(array $workflowIds, array $filters): array
    {
        $end = $filters['anchor']->copy()->endOfDay();
        $start = $filters['anchor']->copy()->subDays(self::WINDOW_DAYS - 1)->startOfDay();

        $rows = $this->baseQuery($workflowIds, $filters)
            ->whereBetween('workflow_runs.created_at', [$start, $end])
            ->selectRaw("strftime('%Y-%m-%d', workflow_runs.created_at) as day")
            ->selectRaw('COUNT(*) as aggregate')
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) as needs_review")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $bars = [];
        $peak = max(1, (int) $rows->max('aggregate'));

        for ($offset = self::WINDOW_DAYS - 1; $offset >= 0; $offset--) {
            $day = $filters['anchor']->copy()->subDays($offset);
            $key = $day->toDateString();
            $row = $rows->get($key);

            $count = (int) ($row->aggregate ?? 0);
            $failed = (int) ($row->failed ?? 0);
            $review = (int) ($row->needs_review ?? 0);

            // The bar takes the colour of the worst thing that happened that day.
            $tone = match (true) {
                $failed > 0 => 'critical',
                $review > 0 => 'review',
                $offset === 0 => 'accent',
                default => 'info',
            };

            $bars[] = [
                'day' => $key,
                'label' => $day->format('M j'),
                'count' => $count,
                'percent' => $count > 0 ? max(8, (int) round(($count / $peak) * 100)) : 4,
                'tone' => $tone,
                'is_latest' => $offset === 0,
            ];
        }

        return [
            'window_days' => self::WINDOW_DAYS,
            'peak' => $peak,
            'anchor_label' => $filters['anchor']->format('M j'),
            'bars' => $bars,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pagination(mixed $paginator): array
    {
        $from = $paginator->total() === 0 ? 0 : $paginator->firstItem();
        $to = $paginator->total() === 0 ? 0 : $paginator->lastItem();

        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $from,
            'to' => $to,
            'range_label' => sprintf('Rows %d–%d of %d filtered', $from, $to, $paginator->total()),
            'prev_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
            'links' => $this->pageLinks($paginator),
        ];
    }

    /**
     * Compact page list: first pages, an ellipsis, then the last page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pageLinks(mixed $paginator): array
    {
        $last = $paginator->lastPage();
        $current = $paginator->currentPage();

        if ($last <= 1) {
            return [];
        }

        $window = collect(range(1, $last))
            ->filter(fn (int $page): bool => $page === 1
                || $page === $last
                || abs($page - $current) <= 1
                || $page <= 3)
            ->values();

        $links = [];
        $previous = 0;

        foreach ($window as $page) {
            if ($previous !== 0 && $page - $previous > 1) {
                $links[] = ['type' => 'gap'];
            }

            $links[] = [
                'type' => 'page',
                'page' => $page,
                'url' => $paginator->url($page),
                'active' => $page === $current,
            ];

            $previous = $page;
        }

        return $links;
    }

    /**
     * @param  array<int, int>  $workflowIds
     * @return array<int, array<string, mixed>>
     */
    private function workflowOptions(array $workflowIds): array
    {
        return Workflow::query()
            ->whereIn('id', $workflowIds)
            ->withCount('runs')
            ->orderBy('name')
            ->get()
            ->map(fn (Workflow $workflow): array => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'runs_count' => $workflow->runs_count,
            ])
            ->all();
    }

    /**
     * The run to open inline on load: the highest-severity pending approval on
     * this page. With the seeded data that is the flagship run #8421.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function flagshipRunId(array $rows): ?int
    {
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($rows as $row) {
            $level = $row['expansion']['risk']['level'] ?? null;

            if ($level === null) {
                continue;
            }

            $rank = $order[$level] ?? PHP_INT_MAX;

            if ($rank < $bestRank) {
                $bestRank = $rank;
                $best = $row['id'];
            }
        }

        return $best;
    }
}
