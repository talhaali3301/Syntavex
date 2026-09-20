<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'workflow_run_id',
        'action',
        'performed_by',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }
}
