<?php

namespace App\Support;

use App\Models\WorkflowRun;

final class RunObjective
{
    public static function for(WorkflowRun $run): string
    {
        $steps = $run->steps ?? collect();
        $trigger = $steps->first();
        $payload = is_array($trigger?->output_payload) ? $trigger->output_payload : [];

        if (isset($payload['requested_amount'])) {
            return sprintf(
                'Refund $%s · %s',
                number_format((float) $payload['requested_amount'], 2),
                isset($payload['ticket_id']) ? 'ticket '.$payload['ticket_id'] : 'no ticket',
            );
        }

        if (isset($payload['ticket_id'], $payload['initial_priority'])) {
            return sprintf('Triage ticket %s · %s', $payload['ticket_id'], $payload['initial_priority']);
        }

        if (isset($payload['accounts_in_scope'])) {
            return sprintf(
                'Health brief · %d accounts · %dd',
                $payload['accounts_in_scope'],
                $payload['window_days'] ?? 7,
            );
        }

        if (isset($payload['service'], $payload['deviation_sigma'])) {
            return sprintf('Anomaly · %s · %sσ', $payload['service'], $payload['deviation_sigma']);
        }

        return $steps->firstWhere('step_type', 'llm_reasoning')?->step_name
            ?? $trigger?->step_name
            ?? 'Run '.$run->run_key;
    }
}
