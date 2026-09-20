<?php

namespace App\Http\Controllers;

use App\Http\Resources\RunInspectorResource;
use App\Models\RunStep;
use App\Models\WorkflowRun;
use App\Support\RunObjective;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Run Inspector (Deep Trace).
 *
 * The run itself is shaped by RunInspectorResource; this assembles the
 * cross-run context around it — the header benchmarks each figure is measured
 * against, and the runs a reviewer would want to compare this one to.
 */
class RunInspectorController extends Controller
{
    private const RELATED_LIMIT = 4;

    /** Window the run's spend is expressed as a share of. */
    private const SPEND_WINDOW_HOURS = 24;

    private const TONES = [
        'completed' => 'completed',
        'needs_review' => 'review',
        'failed' => 'critical',
        'running' => 'info',
    ];

    public function show(WorkflowRun $run): Response
    {
        $run->load(['workflow.workspace', 'steps', 'approvalRequests', 'auditEvents']);

        return Inertia::render('Runs/Inspector', [
            'run' => (new RunInspectorResource($run))->resolve(),
            'header' => $this->header($run),
            'related' => $this->related($run),
        ]);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function header(WorkflowRun $run): array
    {
        $ended = $run->total_duration_ms === null
            ? null
            : $run->created_at?->copy()->addMilliseconds($run->total_duration_ms);

        return [
            [
                'label' => 'STARTED',
                'value' => $run->created_at?->format('H:i:s') ?? '—',
                'caption' => $run->created_at?->format('M j').' · UTC',
                'tone' => 'ink',
            ],
            [
                'label' => 'DURATION',
                'value' => $run->total_duration_ms === null
                    ? '—'
                    : number_format($run->total_duration_ms / 1000, 2).'s',
                'caption' => $this->durationBenchmark($run),
                'tone' => 'ink',
            ],
            [
                'label' => 'TOKENS',
                'value' => $this->compactTokens($run->total_tokens),
                'caption' => $this->tokenSplit($run),
                'tone' => 'ink',
            ],
            [
                'label' => 'COST',
                'value' => $run->total_cost_usd === null
                    ? '—'
                    : '$'.number_format((float) $run->total_cost_usd, 2),
                'caption' => $this->spendShare($run, $ended),
                'tone' => 'accent',
            ],
        ];
    }

    /** Where this run's duration sits against the rest of its workflow. */
    private function durationBenchmark(WorkflowRun $run): ?string
    {
        if ($run->workflow === null) {
            return null;
        }

        $durations = WorkflowRun::query()
            ->where('workflow_id', $run->workflow_id)
            ->whereNotNull('total_duration_ms')
            ->pluck('total_duration_ms')
            ->sort()
            ->values();

        if ($durations->isEmpty()) {
            return null;
        }

        $index = (int) ceil($durations->count() * 0.95) - 1;

        return 'p95 '.number_format($durations[max(0, $index)] / 1000, 2).'s';
    }

    /**
     * The mockup's "in / out" split; the schema records tokens per step type
     * rather than per direction, so that is what it reports.
     */
    private function tokenSplit(WorkflowRun $run): ?string
    {
        $byType = $run->steps
            ->filter(fn (RunStep $step): bool => $step->tokens_used > 0)
            ->groupBy('step_type')
            ->map(fn (Collection $steps): int => (int) $steps->sum('tokens_used'))
            ->sortDesc();

        if ($byType->isEmpty()) {
            return null;
        }

        return $byType
            ->map(fn (int $tokens, string $type): string => str_replace('llm_', '', $type).' '.$this->compactTokens($tokens))
            ->values()
            ->implode(' · ');
    }

    private function spendShare(WorkflowRun $run, ?Carbon $ended): ?string
    {
        $cost = (float) $run->total_cost_usd;
        $window = $ended ?? $run->created_at;

        if ($cost <= 0 || $window === null) {
            return null;
        }

        $workspaceTotal = (float) WorkflowRun::query()
            ->whereHas('workflow', fn ($query) => $query->where('workspace_id', $run->workflow?->workspace_id))
            ->whereBetween('created_at', [$window->copy()->subHours(self::SPEND_WINDOW_HOURS), $window])
            ->sum('total_cost_usd');

        if ($workspaceTotal <= 0) {
            return null;
        }

        return sprintf('%d%% of %dh spend', round(($cost / $workspaceTotal) * 100), self::SPEND_WINDOW_HOURS);
    }

    /**
     * Runs a reviewer would compare this one against: the same workflow first,
     * topped up from the wider workspace so the rail is never half empty.
     *
     * @return array<int, array<string, mixed>>
     */
    private function related(WorkflowRun $run): array
    {
        $sameWorkflow = $this->neighbours($run, $run->workflow_id, self::RELATED_LIMIT)
            ->map(fn (WorkflowRun $other): array => $this->relatedRow($other, 'same workflow'));

        $remaining = self::RELATED_LIMIT - $sameWorkflow->count();

        $sameWorkspace = $remaining <= 0
            ? collect()
            : $this->neighbours($run, null, $remaining)
                ->map(fn (WorkflowRun $other): array => $this->relatedRow($other, 'same workspace'));

        return $sameWorkflow->concat($sameWorkspace)->values()->all();
    }

    /**
     * @return Collection<int, WorkflowRun>
     */
    private function neighbours(WorkflowRun $run, ?int $workflowId, int $limit): Collection
    {
        return WorkflowRun::query()
            ->with('steps')
            ->whereKeyNot($run->getKey())
            ->when(
                $workflowId !== null,
                fn ($query) => $query->where('workflow_id', $workflowId),
                fn ($query) => $query
                    ->where('workflow_id', '!=', $run->workflow_id)
                    ->whereHas('workflow', fn ($inner) => $inner->where('workspace_id', $run->workflow?->workspace_id)),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function relatedRow(WorkflowRun $run, string $relation): array
    {
        return [
            'id' => $run->id,
            'run_key' => $run->run_key,
            'label' => RunObjective::for($run),
            'relation' => $relation,
            'tone' => self::TONES[$run->status] ?? 'info',
        ];
    }

    private function compactTokens(?int $tokens): string
    {
        return match (true) {
            $tokens === null => '—',
            $tokens >= 1000 => number_format($tokens / 1000, 1).'k',
            default => (string) $tokens,
        };
    }
}
