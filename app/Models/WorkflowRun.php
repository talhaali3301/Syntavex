<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'run_key',
        'status',
        'total_duration_ms',
        'total_tokens',
        'total_cost_usd',
        'error_message',
    ];

    protected $casts = [
        'total_duration_ms' => 'integer',
        'total_tokens' => 'integer',
        'total_cost_usd' => 'decimal:4',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RunStep::class)->orderBy('step_order');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }
}
