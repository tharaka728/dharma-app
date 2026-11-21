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
    // Phase 3 decision C: controlled single-representative pilot. User IDs of
    // representatives authorized for PROMO_CHOICE_V1 financial checkout while the
    // master switch is on. MUST stay empty until the owner approves activation for
    // a specific representative; never reuse the historical-return pilot allowlist.
    'pilot_representatives'=>[],
    // Phase 3 decision A: new benefit modes are online only. Signed offline
    // payloads carrying benefit fields are rejected by OfflineSales with 409.
    'online_only'=>true,
    // Phase 4H: temporary first-posting hold. While true, pilot representatives
    // can select benefit modes and obtain server-authoritative reviewed totals,
    // but NO PROMO_CHOICE_V1 invoice may post (server-side, covers direct API
    // submissions too). The owner lifts this only with separate first-posting
    // approval. Keep true until then.
    'posting_hold'=>true,
];
