<?php
declare(strict_types=1);
// Phase 2 promotion benefit modes (policy PROMO_CHOICE_V1).
// Financial checkout with benefit modes stays disabled until the owner gives
// explicit release authorization after isolated end-to-end verification.
return [
    // Master switch. Keep false until the owner authorizes release.
    'enabled'=>false,
    // Databases on which an enabled master switch may run. Deliberately empty:
    // release requires listing the operational database here explicitly.
    'databases'=>[],
    // Isolated verification only: the gate also opens when environment variable
    // DHARMA_BENEFIT_TEST=1 and the connected database is exactly this name.
    'isolated_test_database'=>'dharma_sales_phase9_test',
];
