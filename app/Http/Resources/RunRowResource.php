<?php

namespace App\Http\Resources;

use App\Models\RunStep;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a WorkflowRun for the Runs Explorer table, including the inline
 * expansion payload (trace preview, risk, decision).
 *
 * Everything here is derived from seeded columns and step payloads — the
 * objective line, agent handle, tool-call and policy-hit counts and the risk
 * score are all computed from real rows, never invented.
 *
 * Expects `workflow`, `steps`, `auditEvents` and `approvalRequests` to be
 * eager-loaded.
 *
 * @mixin WorkflowRun
 */
class RunRowResource extends JsonResource
{
    /** Step types that represent an outbound call to a real system. */
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

    /** Step status -> trace-dot tone. */
    private const STEP_TONES = [
        'completed' => 'completed',
        'blocked' => 'review',
        'failed' => 'critical',
        'pending' => 'idle',
        'running' => 'info',
    ];

    /**
     * Base weight per risk level. The remaining 0.25 of the score comes from
     * how badly the policy was breached and how unsure the model was.
     */
    private const RISK_BASE = ['critical' => 0.75, 'high' => 0.60, 'medium' => 0.40, 'low' => 0.20];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_key' => $this->run_key,
            'workflow' => $this->workflow?->name,
            'objective' => $this->objective(),
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

    /**
     * A human objective for the run, read out of the trigger step's payload.
     * Key-driven rather than keyed on workflow slug, so a new workflow with a
     * familiar payload shape still renders something useful.
     */
    private function objective(): string
    {
        $trigger = $this->steps?->first();
        $payload = is_array($trigger?->output_payload) ? $trigger->output_payload : [];

        if (isset($payload['requested_amount'], $payload['ticket_id'])) {
            return sprintf(
                'Refund $%s · ticket %s',
                number_format((float) $payload['requested_amount'], 2),
                $payload['ticket_id'],
            );
        }

        if (isset($payload['ticket_id'], $payload['initial_priority'])) {
            return sprintf('Triage ticket %s · %s', $payload['ticket_id'], $payload['initial_priority']);
        }

        if (isset($payload['accounts_in_scope'])) {
            return sprintf(
                'Health brief · %d accounts · %dd',
                $payload['accounts_in_scope'],
                $payload['window_days'] ?? 7,
            );
        }

        if (isset($payload['service'], $payload['deviation_sigma'])) {
            return sprintf('Anomaly · %s · %sσ', $payload['service'], $payload['deviation_sigma']);
        }

        $reasoning = $this->steps?->firstWhere('step_type', 'llm_reasoning');

        return $reasoning?->step_name ?? $trigger?->step_name ?? 'Run '.$this->run_key;
    }

    /** The agent that last acted on the run, from the audit trail. */
    private function agentHandle(): string
    {
        $actor = $this->auditEvents
            ?->sortByDesc('created_at')
            ->pluck('performed_by')
            ->first(fn (?string $performedBy): bool => str_starts_with((string) $performedBy, 'agent:'));

        return $actor === null ? 'system' : substr($actor, strlen('agent:'));
    }

    /** "18.2k" style token counts, matching the mockup's column. */
    private function compactTokens(): string
    {
        if ($this->total_tokens === null) {
            return '—';
        }

        return $this->total_tokens >= 1000
            ? number_format($this->total_tokens / 1000, 1).'k'
            : (string) $this->total_tokens;
    }

    /**
     * Inline expansion: trace preview, risk and decision.
     *
     * @return array<string, mixed>|null
     */
    private function expansion(): ?array
    {
        $steps = $this->steps;

        if ($steps === null || $steps->isEmpty()) {
            return null;
        }

        $toolCalls = $steps->whereIn('step_type', self::TOOL_CALL_TYPES)->count();

        // A "policy hit" is an approval gate that did NOT clear on its own.
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

    /**
     * Risk level and a 0–1 score.
     *
     * FORMULA
     *   score = base(risk_level)
     *         + 0.15 × min(1, policy_overshoot ÷ policy_limit)
     *         + 0.10 × (1 − model_confidence)
     *
     * The level itself carries most of the weight (it is the seeded
     * classification); the remainder rewards precision — how far past the
     * policy limit the run went, and how unsure the model was when it decided.
     * Runs with no approval request carry no risk score.
     *
     * @return array<string, mixed>|null
     */
    private function risk(mixed $approval, ?RunStep $gate, ?float $confidence): ?array
    {
        if ($approval === null) {
            return null;
        }

        $gateIn = is_array($gate?->input_payload) ? $gate->input_payload : [];
        $gateOut = is_array($gate?->output_payload) ? $gate->output_payload : [];

        $limit = (float) ($gateOut['policy_limit'] ?? $gateIn['policy_limit'] ?? 0);
        $overBy = (float) ($gateOut['over_by'] ?? 0);
        $breach = $limit > 0 ? min(1.0, $overBy / $limit) : 0.0;

        $score = (self::RISK_BASE[$approval->risk_level] ?? 0.2)
            + (0.15 * $breach)
            + (0.10 * (1 - ($confidence ?? 1.0)));

        $score = round(min(1.0, max(0.0, $score)), 2);

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

    /**
     * The decision the run reached, plus the seeded approval summary verbatim.
     *
     * @return array<string, mixed>|null
     */
    private function decision(mixed $approval, ?RunStep $gate, ?RunStep $reasoning, ?float $confidence): ?array
    {
        $out = is_array($reasoning?->output_payload) ? $reasoning->output_payload : [];
        $gateIn = is_array($gate?->input_payload) ? $gate->input_payload : [];

        $verdict = $out['decision'] ?? null;
        $amount = $gateIn['requested_amount'] ?? null;

        $headline = match (true) {
            $verdict !== null && $amount !== null => sprintf(
                'Refund $%s %sd',
                number_format((float) $amount, 2),
                $verdict,
            ),
            $verdict !== null => ucfirst($verdict).'d by agent',
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
            // The seeded approval text, verbatim — never paraphrased.
            'summary' => $approval?->summary,
        ];
    }
}
