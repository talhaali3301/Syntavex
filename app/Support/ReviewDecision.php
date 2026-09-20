<?php

namespace App\Support;

/**
 * A reviewer's verdict on a pending approval, and everything it moves.
 *
 * Request-changes is deliberately not terminal: it clears the desk without
 * resolving the approval, so the run stays in review until someone signs it.
 */
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

    /** What becomes of the step the gate held back. */
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
