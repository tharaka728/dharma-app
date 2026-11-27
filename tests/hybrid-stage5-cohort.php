<?php
declare(strict_types=1);
// Stage 5 fixture-cohort resolution. Pure functions only: no bootstrap, PDO, sessions or environment.
// A missing envelope marker never classifies a fixture by itself; the verified sealed calculation
// outcome selects the cohort, and unknown, mismatched or ambiguous provenance blocks with a diagnostic.
use App\Services\HybridInvoiceSnapshots;

function stage5ResolveCohort(?string $marker, string $interpretation): array
{
    if ($marker !== null && $marker !== 'UNPROMOTED_LINES_V1') {
        throw new RuntimeException('Stage 5 fixture provenance blocked: unknown invoice_discount_eligibility marker '.json_encode($marker).'; review provenance before testing.');
    }
    if ($interpretation === HybridInvoiceSnapshots::HISTORICAL) {
        if ($marker !== null) {
            throw new RuntimeException('Stage 5 fixture provenance blocked: envelope claims UNPROMOTED_LINES_V1 but the sealed calculation matches only the pinned legacy stacking revision.');
        }
        return ['cohort' => 'HISTORICAL', 'note' => 'Unmarked sealed snapshot matches only the pinned legacy stacking revision; issuance era is not independently established.'];
    }
    if ($interpretation === HybridInvoiceSnapshots::CURRENT) {
        return ['cohort' => 'CURRENT', 'note' => $marker === null
            ? 'Unmarked sealed snapshot matches only the current eligibility revision; no issuance-era claim.'
            : 'Marked UNPROMOTED_LINES_V1 artifact verified against the sealed current calculation.'];
    }
    throw new RuntimeException('Stage 5 fixture provenance blocked: sealed calculation is compatible with both revisions; cohort expectations diverge and no era may be invented.');
}

function stage5CohortExpectations(string $cohort): array
{
    if ($cohort === 'HISTORICAL') {
        return ['first_return_credit' => '225.00', 'first_refund_increment' => '225.00', 'total_credits' => '4308.75', 'residual_void_credit' => '4083.75'];
    }
    if ($cohort === 'CURRENT') {
        return ['first_return_credit' => '250.00', 'first_refund_increment' => '250.00', 'total_credits' => '4787.50', 'residual_void_credit' => '4537.50'];
    }
    throw new RuntimeException('Stage 5 fixture provenance blocked: no cohort expectations for '.$cohort.'.');
}
