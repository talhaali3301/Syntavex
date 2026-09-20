<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tier',
    ];

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function workflowRuns(): HasManyThrough
    {
        return $this->hasManyThrough(WorkflowRun::class, Workflow::class);
    }
}
