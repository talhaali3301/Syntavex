<?php

namespace App\Support;

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
