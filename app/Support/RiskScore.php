<?php

namespace App\Support;

/**
 * Risk score for a run that tripped a policy gate.
 *
 *   score = base(risk_level)
 *         + 0.15 × min(1, policy_overshoot ÷ policy_limit)
 *         + 0.10 × (1 − model_confidence)
 *
 * The seeded level carries most of the weight; the remainder rewards
 * precision — how far past the limit the run went, and how unsure the model
 * was when it decided.
 */
final class RiskScore
{
    private const BASE = ['critical' => 0.75, 'high' => 0.60, 'medium' => 0.40, 'low' => 0.20];

    public static function for(string $level, float $policyLimit, float $overBy, ?float $confidence): float
    {
        $breach = $policyLimit > 0 ? min(1.0, $overBy / $policyLimit) : 0.0;

        $score = (self::BASE[$level] ?? 0.20)
            + (0.15 * $breach)
            + (0.10 * (1 - ($confidence ?? 1.0)));

        return round(min(1.0, max(0.0, $score)), 2);
    }
}
