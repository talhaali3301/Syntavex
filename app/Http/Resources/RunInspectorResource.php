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
use Illuminate\Support\Collection;

class RunInspectorResource extends JsonResource
{
    private const TOOL_CALL_TYPES = ['retrieval', 'mutation'];

    private const SIGN_THRESHOLD = 0.75;

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
        'failed' => 'critical',
        'blocked' => 'review',
        'pending' => 'idle',
    ];

    private const TYPE_TONES = [
        'llm_reasoning' => 'reasoning',
        'retrieval' => 'tool',
        'mutation' => 'tool',
        'approval_gate' => 'policy',
        'webhook' => 'reasoning',
    ];

    public function toArray(Request $request): array
    {
        $steps = $this->steps ?? collect();
        $approval = $this->approvalRequests?->sortByDesc('created_at')->first();
        $gate = $steps->firstWhere('step_type', 'approval_gate');
        $reasoning = $steps->firstWhere('step_type', 'llm_reasoning');
        $confidence = $this->confidence($reasoning);

        return [
            'id' => $this->id,
            'run_key' => $this->run_key,
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? strtoupper(str_replace('_', ' ', $this->status)),
            'tone' => self::STATUS_TONES[$this->status] ?? 'info',
            'objective' => RunObjective::for($this->resource),
            'agent' => $this->agentHandle(),
            'model' => $this->model($reasoning),
            'error_message' => $this->error_message,
            'workflow' => [
                'id' => $this->workflow?->id,
                'name' => $this->workflow?->name,
                'slug' => $this->workflow?->slug,
                'trigger_type' => $this->workflow?->trigger_type,
            ],
            'workspace' => [
                'name' => $this->workflow?->workspace?->name,
                'slug' => $this->workflow?->workspace?->slug,
                'tier' => $this->workflow?->workspace?->tier,
            ],
            'trace' => $this->trace($steps, $gate),
            'reasoning' => $this->reasoningTimeline($steps),
            'toolCalls' => $this->toolCalls($steps),
            'policy' => $this->policy($steps, $approval, $gate, $confidence),
            'decision' => $this->decision($approval, $gate, $reasoning, $confidence),
            'metadata' => $this->metadata($steps),
            'audit' => $this->audit(),
        ];
    }

    private function agentHandle(): string
    {
        $actor = $this->auditEvents
            ?->sortByDesc('created_at')
            ->pluck('performed_by')
            ->first(fn (?string $by): bool => str_starts_with((string) $by, 'agent:'));

        return $actor === null ? 'system' : substr($actor, strlen('agent:'));
    }

    private function model(?RunStep $reasoning): ?string
    {
        return $this->payload($reasoning, 'input_payload')['model'] ?? null;
    }

    private function confidence(?RunStep $reasoning): ?float
    {
        $value = $this->payload($reasoning, 'output_payload')['confidence'] ?? null;

        return $value === null ? null : (float) $value;
    }

    private function trace(Collection $steps, ?RunStep $gate): array
    {
        $toolCalls = $steps->whereIn('step_type', self::TOOL_CALL_TYPES)->count();
        $policyHits = $steps->where('step_type', 'approval_gate')
            ->filter(fn (RunStep $step): bool => $step->status !== 'completed')
            ->count();

        $selected = $gate?->status !== 'completed' ? $gate : null;
        $selected ??= $steps->firstWhere('status', 'failed')
            ?? $steps->firstWhere('status', 'pending')
            ?? $steps->last();

        $nodes = $steps->map(fn (RunStep $step): array => [
            'id' => $step->id,
            'order' => $step->step_order,
            'order_label' => str_pad((string) $step->step_order, 2, '0', STR_PAD_LEFT),
            'name' => $step->step_name,
            'type' => $step->step_type,
            'status' => $step->status,
            'tone' => $this->stepTone($step),
            'duration_label' => $this->durationLabel($step->duration_ms),
            'selected' => $step->id === $selected?->id,
        ])->values()->all();

        return [
            'summary' => sprintf(
                '%d step%s · %d tool call%s · %d policy hit%s%s',
                $steps->count(),
                $steps->count() === 1 ? '' : 's',
                $toolCalls,
                $toolCalls === 1 ? '' : 's',
                $policyHits,
                $policyHits === 1 ? '' : 's',
                $selected === null ? '' : sprintf(' · step %02d selected', $selected->step_order),
            ),
            'nodes' => $nodes,
        ];
    }

    private function stepTone(RunStep $step): string
    {
        return self::STEP_TONES[$step->status]
            ?? self::TYPE_TONES[$step->step_type]
            ?? 'reasoning';
    }

    private function reasoningTimeline(Collection $steps): array
    {
        return [
            'title' => sprintf('REASONING · %d STEP%s', $steps->count(), $steps->count() === 1 ? '' : 'S'),
            'meta' => trim(sprintf(
                '%s · %s',
                $this->durationLabel($this->total_duration_ms),
                $this->compactTokens($this->total_tokens),
            ), ' ·'),
            'entries' => $steps->map(fn (RunStep $step): array => [
                'id' => $step->id,
                'order_label' => str_pad((string) $step->step_order, 2, '0', STR_PAD_LEFT),
                'time' => $step->created_at?->format('H:i:s.v'),
                'type' => $step->step_type,
                'name' => $step->step_name,
                'tone' => $this->stepTone($step),
                'lines' => $this->entryLines($step),
            ])->values()->all(),
            'open' => $steps->contains(fn (RunStep $step): bool => $step->status === 'pending'),
        ];
    }

    private function entryLines(RunStep $step): array
    {
        $in = $this->payload($step, 'input_payload');
        $out = $this->payload($step, 'output_payload');
        $lines = [];

        if ($in !== []) {
            $lines[] = ['label' => 'in', 'text' => $this->inline($in), 'tone' => 'ink'];
        }

        $raw = array_diff_key($out, array_flip(['rationale']));

        if ($raw !== []) {
            $lines[] = ['label' => 'out', 'text' => $this->inline($raw), 'tone' => 'ink'];
        }

        if (isset($out['rationale'])) {
            $lines[] = ['label' => '›', 'text' => (string) $out['rationale'], 'tone' => 'ink'];
        }

        if (isset($out['decision'])) {
            $lines[] = [
                'label' => '›',
                'text' => sprintf(
                    'decision %s%s%s',
                    $out['decision'],
                    isset($out['confidence']) ? ' · confidence '.number_format((float) $out['confidence'], 2) : '',
                    isset($out['policy_references']) ? ' · '.implode(', ', (array) $out['policy_references']) : '',
                ),
                'tone' => 'ok',
            ];
        }

        if ($step->step_type === 'approval_gate' && $step->status !== 'completed') {
            $lines[] = [
                'label' => '›',
                'text' => sprintf(
                    'policy %s evaluated → BREACH%s',
                    $in['rule'] ?? 'gate',
                    isset($out['over_by'])
                        ? sprintf(
                            ' · $%s requested against a $%s ceiling, over by $%s',
                            number_format((float) ($out['requested_amount'] ?? $in['requested_amount'] ?? 0), 2),
                            number_format((float) ($out['policy_limit'] ?? $in['policy_limit'] ?? 0), 2),
                            number_format((float) $out['over_by'], 2),
                        )
                        : '',
                ),
                'tone' => 'critical',
            ];
        }

        if (isset($out['escalated_to'])) {
            $lines[] = ['label' => '›', 'text' => 'escalated to '.$out['escalated_to'], 'tone' => 'warn'];
        }

        if (isset($out['error'])) {
            $lines[] = ['label' => '›', 'text' => (string) $out['error'], 'tone' => 'critical'];
        }

        if ($step->status === 'pending') {
            $lines[] = ['label' => '›', 'text' => 'held, not executed', 'tone' => 'warn'];
        }

        return $lines;
    }

    private function toolCalls(Collection $steps): array
    {
        $calls = $steps->whereIn('step_type', self::TOOL_CALL_TYPES)->values();
        $cost = (float) $calls->sum(fn (RunStep $step): float => (float) $step->cost_usd);

        $byStatus = $calls->countBy('status');
        $note = $byStatus
            ->map(fn (int $count, string $status): string => $count.' '.$status)
            ->values()
            ->implode(' · ');

        return [
            'summary' => sprintf(
                '%d invocation%s · $%s',
                $calls->count(),
                $calls->count() === 1 ? '' : 's',
                number_format($cost, 2),
            ),
            'note' => $note === '' ? null : $note,
            'items' => $calls->map(fn (RunStep $step): array => [
                'id' => $step->id,
                'order_label' => sprintf('step %02d', $step->step_order),
                'name' => $this->handle($step->step_name),
                'kind' => $step->step_type,
                'status' => $step->status,
                'status_label' => strtoupper($step->status),
                'tone' => $this->stepTone($step),
                'input' => $this->inline($this->payload($step, 'input_payload')),
                'output' => $step->output_payload === null ? null : $this->inline($this->payload($step, 'output_payload')),
                'duration_label' => $this->durationLabel($step->duration_ms),
                'cost_label' => $step->cost_usd === null ? '—' : '$'.number_format((float) $step->cost_usd, 2),
            ])->all(),
        ];
    }

    private function handle(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? $name);

        return trim($slug, '_');
    }

    private function policy(Collection $steps, ?ApprovalRequest $approval, ?RunStep $gate, ?float $confidence): array
    {
        $gates = $steps->where('step_type', 'approval_gate');
        $in = $this->payload($gate, 'input_payload');
        $out = $this->payload($gate, 'output_payload');

        return [
            'tier' => $this->workflow?->workspace?->tier,
            'risk' => $approval === null ? null : $this->risk($approval, $in, $out, $confidence),
            'confidence' => $confidence === null ? null : [
                'value' => $confidence,
                'label' => number_format($confidence, 2),
                'percent' => round($confidence * 100),
                'threshold_label' => 'sign threshold '.number_format(self::SIGN_THRESHOLD, 2),
                'verdict' => $confidence >= self::SIGN_THRESHOLD ? 'clear' : 'below',
            ],
            'breach' => $approval === null ? null : [
                'rule' => $in['rule'] ?? $out['reason'] ?? 'policy gate',
                'summary' => $approval->summary,
            ],
            'counts' => [
                'passed' => $gates->where('status', 'completed')->count(),
                'breached' => $gates->whereIn('status', ['blocked', 'failed'])->count(),
                'skipped' => $gates->where('status', 'pending')->count(),
            ],
            'clear_label' => $gates->isEmpty()
                ? 'No policy gate ran on this run'
                : 'Every policy gate on this run cleared',
        ];
    }

    private function risk(ApprovalRequest $approval, array $in, array $out, ?float $confidence): array
    {
        $score = RiskScore::for(
            $approval->risk_level,
            (float) ($out['policy_limit'] ?? $in['policy_limit'] ?? 0),
            (float) ($out['over_by'] ?? 0),
            $confidence,
        );

        return [
            'level' => $approval->risk_level,
            'level_label' => strtoupper($approval->risk_level).' RISK',
            'score' => $score,
            'score_label' => number_format($score, 2),
            'percent' => round($score * 100),
            'tone' => match ($approval->risk_level) {
                'critical', 'high' => 'critical',
                'medium' => 'review',
                default => 'info',
            },
        ];
    }

    private function decision(?ApprovalRequest $approval, ?RunStep $gate, ?RunStep $reasoning, ?float $confidence): array
    {
        $out = $this->payload($reasoning, 'output_payload');
        $in = $this->payload($gate, 'input_payload');
        $pending = $approval !== null && $approval->status === 'pending';

        $verdict = $out['decision'] ?? null;
        $amount = $in['requested_amount'] ?? $out['requested_amount'] ?? null;
        $outcome = $verdict === null ? null : $this->pastTense($verdict);

        $headline = match (true) {
            $outcome !== null && $amount !== null => sprintf(
                'Refund $%s %s',
                number_format((float) $amount, 2),
                lcfirst($outcome),
            ),
            $outcome !== null => $outcome.' by agent',
            isset($out['proposed_priority']) => 'Priority '.$out['proposed_priority'].' proposed',
            $this->status === 'failed' => 'Run failed before a decision was recorded',
            default => $reasoning?->step_name ?? 'No decision recorded',
        };

        return [
            'eyebrow' => 'DECISION RECORD · '.match (true) {
                $approval === null => 'NO APPROVAL REQUIRED',
                $pending => 'UNSIGNED',
                default => strtoupper($approval->status),
            },
            'headline' => $headline,
            'detail' => $this->decisionDetail($approval, $in, $confidence),
            'pending' => $pending,
            'resolution' => $approval === null || $pending ? null : [
                'status' => $approval->status,
                'by' => $approval->resolved_by,
                'at' => $approval->resolved_at?->format('Y-m-d H:i:s').' UTC',
                'notes' => $approval->resolution_notes,
            ],
        ];
    }

    private function decisionDetail(?ApprovalRequest $approval, array $gateIn, ?float $confidence): string
    {
        if ($approval === null) {
            return $this->error_message
                ?? 'No policy gate was tripped. This run completed under automation.';
        }

        $parts = [];

        if (isset($gateIn['rule'])) {
            $parts[] = 'Policy exception recorded against '.$gateIn['rule'];
        }

        if ($confidence !== null) {
            $parts[] = 'confidence '.number_format($confidence, 2);
        }

        $parts[] = $approval->status === 'pending'
            ? 'awaiting countersignature from a '.($this->workflow?->workspace?->tier ?? 'workspace').' approver'
            : 'signed by '.($approval->resolved_by ?? 'an approver');

        return implode(' · ', $parts);
    }

    private function pastTense(string $verdict): string
    {
        $words = explode('_', $verdict);
        $verb = array_shift($words);
        $verb = str_ends_with($verb, 'e') ? $verb.'d' : $verb.'ed';

        return ucfirst(trim($verb.' '.implode(' ', $words)));
    }

    private function metadata(Collection $steps): array
    {
        $workspace = $this->workflow?->workspace;
        $trigger = $this->payload($steps->first(), 'input_payload');
        $source = $trigger['source'] ?? $trigger['trigger'] ?? null;

        return array_values(array_filter([
            [
                'label' => 'Workspace',
                'value' => $workspace?->slug,
                'href' => '/dashboard',
                'tone' => 'accent',
            ],
            [
                'label' => 'Workflow',
                'value' => $this->workflow?->slug,
                'href' => $this->workflow === null ? null : '/runs?workflow='.$this->workflow->id,
                'tone' => 'accent',
            ],
            ['label' => 'Run ID', 'value' => 'run_'.$this->id, 'tone' => 'ink'],
            $this->model($steps->firstWhere('step_type', 'llm_reasoning')) === null ? null : [
                'label' => 'Model',
                'value' => $this->model($steps->firstWhere('step_type', 'llm_reasoning')),
                'tone' => 'ink',
            ],
            [
                'label' => 'Started (UTC)',
                'value' => $this->created_at?->format('Y-m-d H:i:s'),
                'tone' => 'ink',
            ],
            [
                'label' => 'Ended (UTC)',
                'value' => $this->endedAt()?->format('Y-m-d H:i:s') ?? 'still running',
                'tone' => 'ink',
            ],
            [
                'label' => 'Triggered by',
                'value' => trim($this->workflow?->trigger_type.($source === null ? '' : ' · '.$source)),
                'tone' => 'ink',
            ],
        ]));
    }

    private function endedAt(): ?Carbon
    {
        if ($this->created_at === null || $this->total_duration_ms === null) {
            return null;
        }

        return $this->created_at->copy()->addMilliseconds($this->total_duration_ms);
    }

    private function audit(): array
    {
        $count = $this->auditEvents?->count() ?? 0;

        return [
            'count' => $count,
            'label' => sprintf('%d ledger event%s', $count, $count === 1 ? '' : 's'),
        ];
    }

    private function payload(?RunStep $step, string $attribute): array
    {
        $value = $step?->{$attribute};

        return is_array($value) ? $value : [];
    }

    private function inline(array $payload): string
    {
        $pairs = [];

        foreach ($payload as $key => $value) {
            $pairs[] = $key.': '.$this->literal($value);
        }

        return '{ '.implode(', ', $pairs).' }';
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_array($value) => '['.implode(', ', array_map($this->literal(...), $value)).']',
            is_string($value) => '"'.$this->truncate($value).'"',
            default => (string) $value,
        };
    }

    private function truncate(string $value, int $limit = 88): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1).'…';
    }

    private function durationLabel(?int $ms): string
    {
        return $ms === null ? '—' : number_format($ms / 1000, 2).'s';
    }

    private function compactTokens(?int $tokens): string
    {
        return match (true) {
            $tokens === null => '—',
            $tokens >= 1000 => number_format($tokens / 1000, 1).'k tok',
            default => $tokens.' tok',
        };
    }
}
