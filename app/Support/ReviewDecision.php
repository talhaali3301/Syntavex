<?php

namespace App\Support;

enum ReviewDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case RequestChanges = 'request_changes';

    public function approvalStatus(): string
    {
        return match ($this) {
            self::Approve => 'approved',
            self::Reject => 'rejected',
            self::RequestChanges => 'changes_requested',
        };
    }

    public function runStatus(): string
    {
        return match ($this) {
            self::Approve => 'completed',
            self::Reject => 'failed',
            self::RequestChanges => 'needs_review',
        };
    }

    public function heldStepStatus(): string
    {
        return match ($this) {
            self::Approve => 'completed',
            self::Reject => 'blocked',
            self::RequestChanges => 'pending',
        };
    }

    public function auditAction(): string
    {
        return 'approval_'.$this->approvalStatus();
    }

    public function isTerminal(): bool
    {
        return $this !== self::RequestChanges;
    }
}
