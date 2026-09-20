<?php

namespace App\Http\Resources;

use App\Models\RunStep;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a WorkflowRun for the Command Centre "Recent Runs" table.
 *
 * Expects the caller to eager-load `workflow`, `steps` and `auditEvents`;
 * the derived agent handle and focus step are read from those relations.
 *
 * @mixin WorkflowRun
 */
class RunListResource extends JsonResource
{
    /** Statuses that mark the step a run is currently "sitting on". */
    private const UNSETTLED_STEP_STATUSES = ['blocked', 'failed', 'pending', 'running'];

    private const STATUS_LABELS = [
        'completed' => 'Completed',
        'needs_review' => 'Needs review',
        'failed' => 'Failed',
        'running' => 'Running',
    ];

    /** Maps a run status onto the Phase 1 status colour tokens. */
    private const STATUS_TONES = [
        'completed' => 'completed',
        'needs_review' => 'review',
        'failed' => 'critical',
        'running' => 'info',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $focusStep = $this->focusStep();

        return [
            'id' => $this->id,
            'run_key' => $this->run_key,
            'workflow' => $this->workflow?->name,
            'workflow_slug' => $this->workflow?->slug,
            'agent' => $this->agentHandle(),
            'step' => $focusStep?->step_name,
            'step_type' => $focusStep?->step_type,
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status)),
            'tone' => self::STATUS_TONES[$this->status] ?? 'info',
            'latency_ms' => $this->total_duration_ms,
            'latency_label' => $this->latencyLabel(),
            'tokens' => $this->total_tokens,
            'tokens_label' => $this->total_tokens === null ? '—' : number_format($this->total_tokens),
            'cost_usd' => $this->total_cost_usd === null ? null : (float) $this->total_cost_usd,
            'cost_label' => $this->total_cost_usd === null ? '—' : '$'.number_format((float) $this->total_cost_usd, 4),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_label' => $this->created_at?->format('M j · H:i'),
        ];
    }

    /**
     * The step the run is parked on (blocked / failed / pending), or the last
     * step it executed when the run settled cleanly.
     */
    private function focusStep(): ?RunStep
    {
        $steps = $this->steps;

        if ($steps === null || $steps->isEmpty()) {
            return null;
        }

        return $steps->first(
            fn (RunStep $step): bool => in_array($step->status, self::UNSETTLED_STEP_STATUSES, true)
        ) ?? $steps->last();
    }

    /**
     * The agent that last acted on the run, taken from the audit trail.
     * System-driven runs (e.g. infrastructure failures) have no agent actor.
     */
    private function agentHandle(): string
    {
        $actor = $this->auditEvents
            ?->sortByDesc('created_at')
            ->pluck('performed_by')
            ->first(fn (?string $performedBy): bool => str_starts_with((string) $performedBy, 'agent:'));

        return $actor === null ? 'system' : substr($actor, strlen('agent:'));
    }

    private function latencyLabel(): string
    {
        if ($this->total_duration_ms === null) {
            return '—';
        }

        return number_format($this->total_duration_ms / 1000, 2).'s';
    }
}
