<?php
declare(strict_types=1);

namespace App\Support;

/** A rejected pure calculation has findings, never a usable partial invoice. */
final class HybridCalculationException extends \DomainException
{
    public function __construct(public array $findings)
    {
        parent::__construct($findings[0]['message'] ?? 'Hybrid calculation rejected.');
    }
}
