<?php

namespace App\Http\Resources;

use App\Models\ApprovalRequest;
use App\Models\RunStep;
use App\Models\WorkflowRun;
use App\Support\RiskScore;
use App\Support\RunObjective;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Shapes one pending ApprovalRequest for the Review Queue desk.
 *
 * The row-level fields are derived from the gate step's payload rather than the
 * workflow slug, so a workflow nobody has seen before still renders a headline,
 * an impact figure and a readout. `detail` is the Run Inspector payload
 * verbatim — the expansion renders it with the Inspector's own components.
 *
 * Expects the run's `workflow.workspace`, `steps`, `approvalRequests` and
 * `auditEvents` to be eager-loaded.
 *
 * @mixin ApprovalRequest
 */
class ReviewQueueItemResource extends JsonResource
{
    /** Critical severity is what earns the Freeze Frame treatment. */
    private const FROZEN_LEVEL = 'critical';

    private const LEVEL_RANK = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    private const LEVEL_TONES = [
        'critical' => 'critical',
        'high' => 'critical',
        'medium' => 'review',
        'low' => 'info',
    ];

    /** Hours a pending item may wait before it is past due. */
    public const SLA_HOURS = 4;

    public static function rank(ApprovalRequest $approval): int
    {
        return self::LEVEL_RANK[$approval->risk_level] ?? count(self::LEVEL_RANK);
    }

    /** Severity band alone ties too often; the score separates within it. */
    public static function score(ApprovalRequest $approval): float
    {
        $gate = $approval->workflowRun->steps->firstWhere('step_type', 'approval_gate');
        $reasoning = $approval->workflowRun->steps->firstWhere('step_type', 'llm_reasoning');
        $in = is_array($gate?->input_payload) ? $gate->input_payload : [];
        $out = is_array($gate?->output_payload) ? $gate->output_payload : [];
        $confidence = is_array($reasoning?->output_payload) ? ($reasoning->output_payload['confidence'] ?? null) : null;

        return RiskScore::for(
            $approval->risk_level,
            (float) ($out['policy_limit'] ?? $in['policy_limit'] ?? 0),
            (float) ($out['over_by'] ?? 0),
            $confidence === null ? null : (float) $confidence,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $run = $this->workflowRun;
        $steps = $run->steps;
        $gate = $steps->firstWhere('step_type', 'approval_gate');
        $reasoning = $steps->firstWhere('step_type', 'llm_reasoning');
        $held = $steps->firstWhere('status', 'pending');
        $in = $this->payload($gate, 'input_payload');
        $out = $this->payload($gate, 'output_payload');
        $confidence = $this->confidence($reasoning);
        $waited = $this->waitedSeconds();

        return [
            'id' => $this->id,
            'run_id' => $run->id,
            'run_key' => $run->run_key,
            'workflow' => $run->workflow?->name,
            'agent' => $this->agentHandle($run),
            'objective' => RunObjective::for($run),
            'headline' => $this->headline($out),
            'summary' => $this->summary,
            'risk' => $this->risk($in, $out, $confidence),
            'category' => $this->category($out),
            'impact_label' => $this->impactLabel($out),
            'waiting_seconds' => $waited,
            'waiting_label' => $this->elapsedLabel($waited),
            'sla_label' => $this->slaLabel($waited),
            'frozen' => $this->risk_level === self::FROZEN_LEVEL,
            'intercept' => $this->intercept($in, $out, $gate),
            'readout' => $this->readout($reasoning, $out, $in),
            'impact_rows' => $this->impactRows($out, $held),
            'detail' => (new RunInspectorResource($run))->resolve(),
        ];
    }

    // -----------------------------------------------------------------
    // Headline & impact
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $out
     */
    private function headline(array $out): string
    {
        return match (true) {
            isset($out['rows_affected']) => sprintf(
                '%s %s %s flagged %s',
                ucfirst((string) ($out['operation'] ?? 'modify')),
                number_format((float) $out['rows_affected']),
                $out['entity'] ?? 'records',
                $out['qualifier'] ?? 'in scope',
            ),
            isset($out['requested_amount'], $out['policy_limit']) => sprintf(
                'Refund $%s above the $%s ceiling',
                number_format((float) $out['requested_amount'], 2),
                number_format((float) $out['policy_limit'], 2),
            ),
            isset($out['observed_confidence']) => sprintf(
                '%s at confidence %s, below the %s threshold',
                $out['change'] ?? 'Decision',
                number_format((float) $out['observed_confidence'], 2),
                number_format((float) ($out['threshold'] ?? 0), 2),
            ),
            isset($out['amount']) => sprintf(
                '$%s credit · %s',
                number_format((float) $out['amount'], 2),
                $out['reason'] ?? 'held for review',
            ),
            default => $this->summary,
        };
    }

    /** What kind of gate stopped this — the chip the queue row leads with. */
    private function category(array $out): string
    {
        return match (true) {
            (bool) ($out['irreversible'] ?? false) => 'DESTRUCTIVE',
            isset($out['observed_confidence']) => 'LOW CONF',
            default => 'POLICY',
        };
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function impactLabel(array $out): ?string
    {
        return match (true) {
            isset($out['rows_affected']) => number_format((float) $out['rows_affected']).' rows',
            isset($out['requested_amount']) => '$'.number_format((float) $out['requested_amount'], 2),
            isset($out['amount']) => '$'.number_format((float) $out['amount'], 2),
            default => null,
        };
    }

    // -----------------------------------------------------------------
    // Risk
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $in
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function risk(array $in, array $out, ?float $confidence): array
    {
        $score = RiskScore::for(
            $this->risk_level,
            (float) ($out['policy_limit'] ?? $in['policy_limit'] ?? 0),
            (float) ($out['over_by'] ?? 0),
            $confidence,
        );

        return [
            'level' => $this->risk_level,
            'level_label' => strtoupper($this->risk_level),
            'score' => $score,
            'score_label' => number_format($score, 2),
            'percent' => round($score * 100),
            'tone' => self::LEVEL_TONES[$this->risk_level] ?? 'info',
            'confidence_label' => $confidence === null ? null : 'conf '.number_format($confidence, 2),
        ];
    }

    // -----------------------------------------------------------------
    // Freeze Frame evidence
    // -----------------------------------------------------------------

    /**
     * The blocked rule, stated the way the gate recorded it.
     *
     * @param  array<string, mixed>  $in
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function intercept(array $in, array $out, ?RunStep $gate): array
    {
        $limit = (float) ($out['policy_limit'] ?? $in['policy_limit'] ?? 0);
        $rows = isset($out['rows_affected']);
        $attempted = (float) ($rows ? $out['rows_affected'] : ($out['requested_amount'] ?? 0));
        $figure = fn (float $value): string => $rows
            ? number_format($value).' rows'
            : '$'.number_format($value, 2);

        return [
            'rule' => $in['rule'] ?? $out['policy_rule'] ?? 'policy gate',
            'status_label' => strtoupper($gate?->status ?? 'blocked'),
            'measure' => $limit > 0 && $attempted > 0
                ? sprintf(
                    'threshold %s · attempted %s · %s× over',
                    $figure($limit),
                    $figure($attempted),
                    number_format($attempted / $limit, 1),
                )
                : ($out['reason'] ?? null),
            'irreversible' => (bool) ($out['irreversible'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $out
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function readout(?RunStep $reasoning, array $out, array $in): array
    {
        $reasonOut = $this->payload($reasoning, 'output_payload');
        $threshold = $this->payload($reasoning, 'input_payload')['confidence_threshold'] ?? null;
        $confidence = $this->confidence($reasoning);

        $lines = array_map(
            fn (string $finding): array => ['text' => $finding, 'tone' => 'ink'],
            array_values((array) ($reasonOut['findings'] ?? array_filter([$reasonOut['rationale'] ?? null]))),
        );

        if ($out !== []) {
            $lines[] = [
                'text' => sprintf(
                    'policy %s → HARD BLOCK · execution halted pre-commit.',
                    $in['rule'] ?? $out['policy_rule'] ?? 'gate',
                ),
                'tone' => 'critical',
            ];
        }

        return [
            'title' => $reasoning === null
                ? 'REASONING READOUT'
                : sprintf('REASONING READOUT · STEP %02d', $reasoning->step_order),
            'meta' => trim(implode(' · ', array_filter([
                $confidence === null ? null : 'confidence '.number_format($confidence, 2),
                $threshold === null ? null : 'threshold '.number_format((float) $threshold, 2),
            ]))),
            'lines' => $lines,
        ];
    }

    /**
     * What the held action would have touched, drawn from the gate's own tally.
     *
     * @param  array<string, mixed>  $out
     * @return array<int, array<string, string>>
     */
    private function impactRows(array $out, ?RunStep $held): array
    {
        $rows = [];

        if (isset($out['rows_affected'])) {
            $rows[] = [
                'label' => $out['entity'] ?? 'records',
                'value' => number_format((float) $out['rows_affected']).' rows',
            ];
        }

        foreach ((array) ($out['cascade'] ?? []) as $entity => $count) {
            $rows[] = ['label' => $entity.' (cascade)', 'value' => number_format((float) $count).' rows'];
        }

        if (isset($out['requested_amount'], $out['policy_limit'])) {
            $rows[] = ['label' => 'requested', 'value' => '$'.number_format((float) $out['requested_amount'], 2)];
            $rows[] = ['label' => 'automated ceiling', 'value' => '$'.number_format((float) $out['policy_limit'], 2)];
        }

        if ($held !== null) {
            $rows[] = ['label' => 'tool call', 'value' => $this->handle($held->step_name)];
        }

        if (array_key_exists('snapshot', $out)) {
            $rows[] = ['label' => 'snapshot', 'value' => $out['snapshot'] ?? 'none referenced'];
        }

        return $rows;
    }

    // -----------------------------------------------------------------
    // Timing
    // -----------------------------------------------------------------

    private function waitedSeconds(): int
    {
        return max(0, (int) ($this->created_at ?? Carbon::now())->diffInSeconds(Carbon::now()));
    }

    private function elapsedLabel(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $minutes)
            : sprintf('%dm %02ds', $minutes, $seconds % 60);
    }

    private function slaLabel(int $waited): string
    {
        $remaining = self::SLA_HOURS * 3600 - $waited;

        return $remaining <= 0
            ? 'SLA breached '.$this->elapsedLabel(-$remaining).' ago'
            : 'SLA in '.$this->elapsedLabel($remaining);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function agentHandle(WorkflowRun $run): string
    {
        $actor = $run->auditEvents
            ->sortByDesc('created_at')
            ->pluck('performed_by')
            ->first(fn (?string $by): bool => str_starts_with((string) $by, 'agent:'));

        return $actor === null ? 'system' : substr($actor, strlen('agent:'));
    }

    private function confidence(?RunStep $reasoning): ?float
    {
        $value = $this->payload($reasoning, 'output_payload')['confidence'] ?? null;

        return $value === null ? null : (float) $value;
    }

    private function handle(string $name): string
    {
        return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? $name), '_');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?RunStep $step, string $attribute): array
    {
        $value = $step?->{$attribute};

        return is_array($value) ? $value : [];
    }
}
