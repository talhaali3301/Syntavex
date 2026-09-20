<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_run_id',
        'run_step_id',
        'risk_level',
        'summary',
        'status',
        'resolved_by',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function runStep(): BelongsTo
    {
        return $this->belongsTo(RunStep::class);
    }
}
