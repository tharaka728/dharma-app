<?php
declare(strict_types=1);
// Stage 2B source foundations only. No environment-variable activation shortcut.
return [
    'enabled' => false,
    'sale_posting' => false,
    'return_posting' => false,
    'custody' => false,
    'financial_posting_hold' => true,
    'databases' => [],
    'configuration_version' => 'HYBRID_CONFIG_V1',
    // Stable product => promotion identities, immutable policy expectations.
    // Operational IDs have deliberately not been inspected or guessed.
    'bindings' => [],
];
