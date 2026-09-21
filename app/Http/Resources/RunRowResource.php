<?php

namespace App\Http\Resources;

use App\Models\RunStep;
use App\Models\WorkflowRun;
use App\Support\RiskScore;
use App\Support\RunObjective;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunRowResource extends JsonResource
{
    private const TOOL_CALL_TYPES = ['retrieval', 'mutation'];

    private const STATUS_LABELS = [
        'completed' => 'COMPLETED',
        'needs_review' => 'NEEDS REVIEW',
        'failed' => 'FAILED',
        'running' => 'RUNNING',
    ];

    private const STATUS_TONES = [
        'completed' => 'completed',
        'needs_review' => 'review',
        'failed' => 'critical',
        'running' => 'info',
    ];

    private const STEP_TONES = [
        'completed' => 'completed',
        'blocked' => 'review',
        'failed' => 'critical',
        'pending' => 'idle',
        'running' => 'info',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_key' => $this->run_key,
            'workflow' => $this->workflow?->name,
            'objective' => RunObjective::for($this->resource),
            'agent' => $this->agentHandle(),
            'started_at' => $this->created_at?->toIso8601String(),
            'started_label' => $this->created_at?->format('M j · H:i:s'),
            'duration_ms' => $this->total_duration_ms,
            'duration_label' => $this->total_duration_ms === null
                ? '—'
                : number_format($this->total_duration_ms / 1000, 2).'s',
            'tokens' => $this->total_tokens,
            'tokens_label' => $this->compactTokens(),
            'cost_usd' => $this->total_cost_usd === null ? null : (float) $this->total_cost_usd,
            'cost_label' => $this->total_cost_usd === null
                ? '—'
                : '$'.number_format((float) $this->total_cost_usd, 2),
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? strtoupper(str_replace('_', ' ', $this->status)),
            'tone' => self::STATUS_TONES[$this->status] ?? 'info',
            'error_message' => $this->error_message,
            'expansion' => $this->expansion(),
        ];
    }

    private function agentHandle(): string
    {
        $actor = $this->auditEvents
            ?->sortByDesc('created_at')
            ->pluck('performed_by')
            ->first(fn (?string $performedBy): bool => str_starts_with((string) $performedBy, 'agent:'));

        return $actor === null ? 'system' : substr($actor, strlen('agent:'));
    }

    private function compactTokens(): string
    {
        if ($this->total_tokens === null) {
            return '—';
        }

        return $this->total_tokens >= 1000
            ? number_format($this->total_tokens / 1000, 1).'k'
            : (string) $this->total_tokens;
    }

    private function expansion(): ?array
    {
        $steps = $this->steps;

        if ($steps === null || $steps->isEmpty()) {
            return null;
        }

        $toolCalls = $steps->whereIn('step_type', self::TOOL_CALL_TYPES)->count();

        $policyHits = $steps
            ->where('step_type', 'approval_gate')
            ->filter(fn (RunStep $step): bool => $step->status !== 'completed')
            ->count();

        $gate = $steps->firstWhere('step_type', 'approval_gate');
        $reasoning = $steps->firstWhere('step_type', 'llm_reasoning');
        $approval = $this->approvalRequests?->sortByDesc('created_at')->first();

        $confidence = is_array($reasoning?->output_payload)
            ? ($reasoning->output_payload['confidence'] ?? null)
            : null;

        return [
            'steps' => $steps->count(),
            'tool_calls' => $toolCalls,
            'policy_hits' => $policyHits,
            'trace' => $steps->map(fn (RunStep $step): array => [
                'id' => $step->id,
                'name' => $step->step_name,
                'type' => $step->step_type,
                'status' => $step->status,
                'tone' => self::STEP_TONES[$step->status] ?? 'idle',
            ])->values()->all(),
            'risk' => $this->risk($approval, $gate, $confidence),
            'decision' => $this->decision($approval, $gate, $reasoning, $confidence),
            'model' => is_array($reasoning?->input_payload)
                ? ($reasoning->input_payload['model'] ?? null)
                : null,
        ];
    }

    private function risk(mixed $approval, ?RunStep $gate, ?float $confidence): ?array
    {
        if ($approval === null) {
            return null;
        }

        $gateIn = is_array($gate?->input_payload) ? $gate->input_payload : [];
        $gateOut = is_array($gate?->output_payload) ? $gate->output_payload : [];

        $score = RiskScore::for(
            $approval->risk_level,
            (float) ($gateOut['policy_limit'] ?? $gateIn['policy_limit'] ?? 0),
            (float) ($gateOut['over_by'] ?? 0),
            $confidence,
        );

        return [
            'level' => $approval->risk_level,
            'level_label' => strtoupper($approval->risk_level),
            'score' => $score,
            'score_label' => number_format($score, 2),
            'tone' => match ($approval->risk_level) {
                'critical', 'high' => 'critical',
                'medium' => 'review',
                default => 'info',
            },
            'rule' => isset($gateIn['rule'])
                ? $gateIn['rule'].' breached'
                : ($gateOut['reason'] ?? 'policy gate tripped'),
        ];
    }

    private function pastTense(string $verdict): string
    {
        $words = explode('_', $verdict);
        $verb = array_shift($words);
        $verb = str_ends_with($verb, 'e') ? $verb.'d' : $verb.'ed';

        return ucfirst(trim($verb.' '.implode(' ', $words)));
    }

    private function decision(mixed $approval, ?RunStep $gate, ?RunStep $reasoning, ?float $confidence): ?array
    {
        $out = is_array($reasoning?->output_payload) ? $reasoning->output_payload : [];
        $gateIn = is_array($gate?->input_payload) ? $gate->input_payload : [];

        $verdict = $out['decision'] ?? null;
        $amount = $gateIn['requested_amount'] ?? null;
        $outcome = $verdict === null ? null : $this->pastTense($verdict);

        $headline = match (true) {
            $outcome !== null && $amount !== null => sprintf(
                'Refund $%s %s',
                number_format((float) $amount, 2),
                lcfirst($outcome),
            ),
            $outcome !== null => $outcome.' by agent',
            isset($out['original_priority'], $out['proposed_priority']) => sprintf(
                'Priority %s → %s proposed',
                $out['original_priority'],
                $out['proposed_priority'],
            ),
            default => $reasoning?->step_name ?? 'No reasoning step recorded',
        };

        return [
            'headline' => $headline,
            'confidence' => $confidence,
            'confidence_label' => $confidence === null
                ? 'confidence n/a'
                : 'confidence '.number_format((float) $confidence, 2),
            'signed' => $approval !== null && $approval->status !== 'pending',
            'signed_label' => $approval === null
                ? 'no approval required'
                : ($approval->status === 'pending' ? 'unsigned' : 'signed by '.$approval->resolved_by),
            'summary' => $approval?->summary,
        ];
    }
}
