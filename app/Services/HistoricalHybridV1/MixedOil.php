<?php
declare(strict_types=1);
namespace App\Services\HistoricalHybridV1;

// Only the pinned constant referenced by PromotionCalculator; Hybrid rejects mixed rules.
final class MixedOil
{
    public const POLICY = 'MIXED_OIL_V1';
}
