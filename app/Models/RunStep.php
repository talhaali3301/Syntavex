<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RunStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_run_id',
        'step_order',
        'step_name',
        'step_type',
        'status',
        'duration_ms',
        'tokens_used',
        'cost_usd',
        'input_payload',
        'output_payload',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'duration_ms' => 'integer',
        'tokens_used' => 'integer',
        'cost_usd' => 'decimal:4',
        'input_payload' => 'array',
        'output_payload' => 'array',
    ];

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }
}
