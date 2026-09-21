<?php

namespace App\Http\Resources;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    private const RISK_TONES = [
        'critical' => 'critical',
        'high' => 'critical',
        'medium' => 'review',
        'low' => 'info',
    ];

    public function toArray(Request $request): array
    {
        $run = $this->workflowRun;

        return [
            'id' => $this->id,
            'risk_level' => $this->risk_level,
            'risk_label' => strtoupper($this->risk_level),
            'tone' => self::RISK_TONES[$this->risk_level] ?? 'info',
            'summary' => $this->summary,
            'status' => $this->status,
            'step_name' => $this->runStep?->step_name,
            'step_type' => $this->runStep?->step_type,
            'step_order' => $this->runStep?->step_order,
            'run_id' => $run?->id,
            'run_key' => $run?->run_key,
            'run_status' => $run?->status,
            'workflow' => $run?->workflow?->name,
            'cost_usd' => $run?->total_cost_usd === null ? null : (float) $run->total_cost_usd,
            'cost_label' => $run?->total_cost_usd === null ? '—' : '$'.number_format((float) $run->total_cost_usd, 4),
            'waiting_since' => $this->created_at?->toIso8601String(),
            'waiting_label' => $this->created_at?->diffForHumans(short: true),
        ];
    }
}
