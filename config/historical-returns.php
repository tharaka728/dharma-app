<?php
declare(strict_types=1);
// Explicit private opt-in after operational readiness verification.
// Set false to stop new online return submissions without changing history.
return [
    // Financial posting remains separately controlled and default-off.
    'enabled'=>false,
    // Financial pilot is independent of the read-only preview; revoke by setting enabled false.
    'pilot'=>['enabled'=>true,'representative_id'=>'156','username'=>'nissanka'],
    // Read-only preview: explicitly bind to one active representative and database.
    'preview'=>['enabled'=>true,'database'=>'dharma_sales_phase1','representative_id'=>'156'],
];
