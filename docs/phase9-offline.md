# Phase 9 — Offline PWA and foreground sales synchronization

Implemented in the existing PHP/MariaDB application, with the owner's approved seven-day, cash-only, single-device policy. Phase 10 printing/PDF work has not started.

## URLs

- Offline New Sale: **http://127.0.0.1:8088/offline/index.html#new**
- Sync Status / local stock: **http://127.0.0.1:8088/offline/index.html#status**
- Pending Sales / acknowledged history: **http://127.0.0.1:8088/offline/index.html#pending**
- Admin conflicts, provisional cash reconciliation and device management: **http://127.0.0.1:8088/admin/offline**

The representative dashboard links Offline Sales and Sync Status. Admin navigation links Offline Sales Review. Existing online invoice URLs and health API remain operational. The offline HTML is a public static shell with no embedded customer, identity, session or financial information; its data remains locked until the separate device passphrase is supplied and access policy permits opening.

## First use and normal workflow

1. Sign in online as the real representative. Ensure the admin has assigned real customers and product-level stock. Open the offline workspace in the browser profile/PWA you intend to use. Install first if using a mobile browser with a separate standalone storage context.
2. Set and confirm a **separate, unique device passphrase of at least 12 characters**. Prefer a strong multiword phrase; never reuse the account password. Click **Register / Refresh data** while authenticated. The server permits only one primary offline device for that representative. Wait for the registered/refreshed message and the last-sync timestamp. Storage persistence is requested but may be denied.
3. Open New Sale, select an assigned customer, search/select products and paid whole quantities, and enter actual cash received. Review paid/free quantities, discounts, exact LKR total, provisional payment, change and outstanding. **Save locally — Pending Sync** commits the encrypted sale before showing success. This creates no official number, server payment or stock movement yet.
4. When connected, click **Sync Now**. A fresh authenticated reachability/CSRF check occurs first. Submissions run in stored sequence, each with its original signed body and UUID. The server issues a number only after accepting a sale. Open the confirmed invoice through its history link.
5. Review Needs Attention / Rejected records with the admin. Export an encrypted recovery file regularly, especially before device replacement. Never manually recreate a pending sale as an online invoice to work around a conflict.

Cash-only is intentional. CREDIT, offline refunds and offline voids have no client workflow and are rejected by the server. CASH may be partially paid; the unpaid amount stays outstanding. Excess received is change, never revenue, using the existing Phase 8 rules.

## Storage, encryption and privacy policy

IndexedDB database `dharma-offline-v1` has a versioned `vault` store. Customer/product data, snapshots, original signed pending sales, provisional collections, status/errors and acknowledged history are stored inside an **AES-256-GCM authenticated encrypted document**, with fresh random 96-bit IVs. A key is derived locally from the device passphrase using **PBKDF2-HMAC-SHA-256, 600,000 iterations and a random 256-bit salt**, imported/derived as a non-extractable key. Its unwrapped key and passphrase are not persisted. Earlier development records with explicit/legacy derivation parameters remain readable; no IndexedDB reset occurs during upgrade.

The signing identity is a separate non-extractable P-256 Web Crypto private key, stored as an opaque CryptoKey in IndexedDB. Its public key is registered on the server; signed bodies bind operation/device/grant/sequence and financial claims. Copying a UUID does not prove device ownership. Every submission additionally requires a current authenticated representative session and CSRF token. Device proof is not an administrative token and does not replace authentication. Signing keys are deliberately not included in recovery exports.

No password, password hash, database credential, PHP session secret, CSRF token or long-lived administrative token is stored in IndexedDB. Live CSRF/session reachability results exist only in memory. The unencrypted database metadata consists of salt, IV/ciphertext, derivation parameters, revision counter and opaque signing keys. Customer and sale content is not placed in localStorage, notifications or Cache Storage.

This is application encryption, **not a claim of hardware-backed encryption or protection from a compromised unlocked browser, origin XSS, malware, malicious extensions or a weak passphrase**. Use a private screen-locked device. Losing the passphrase can make unuploaded data unrecoverable. Browser/OS storage eviction cannot be ruled out; persistence permission and encrypted exports reduce risk but do not guarantee retention.

- Manual Lock and 15 minutes of inactivity hide the workspace, clear sensitive rendered DOM and discard the in-memory vault key. Reopening starts locked.
- Lock & sign out retains encrypted records and requires fresh online authentication before reopening. Normal login/logout landing pages broadcast a lock to other app tabs. An offline logout can lock locally immediately; the disconnected server session expires separately. Reauthentication is still required by the local policy when reopening after that logout.
- A valid already-provisioned vault can open offline within its seven-day snapshot window. Snapshot expiry or detected clock rollback prevents **new** sales and leaves pending records intact. Server receipt-time expiration remains authoritative; changing a device clock cannot extend server authorization.
- Unresolved sales, conflicts and provisional cash records are never automatically deleted. Only **Synced and coherently reconciled** records older than 30 days are pruned, using server acknowledgement/snapshot time. Rejected records are not automatically pruned.
- Clear local data is an explicit confirmed action and refuses unresolved records. It permits only coherent Synced records or explicitly admin-rejected/reconciled records. Export first. Clearing also removes the signing key; server device registration remains and requires admin replacement handling before registering a new identity. Server invoice/payment/submission/review history is untouched.

The encryption uses the browser's native [Web Crypto key-derivation API](https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto/deriveKey). IndexedDB alone does not encrypt application records.

## Local financial calculations and durable queue

`offline-domain.js` mirrors the existing Phase 7 rules with BigInt integer cents and whole-unit quantities. Percentage discounts round half-up once per paid line; fixed LKR discounts are per paid unit; same-product Buy X/Get Y consumes both paid and free units. Line/header amounts are bounded to DECIMAL(15,2), and quantities to signed 64-bit limits. Duplicate product lines, invalid/negative values, insufficient local stock and expired downloaded offers are rejected before saving.

Each sale has a cryptographically generated UUID, local reference, monotonic device sequence, original client timestamp, snapshot grant, frozen financial terms and signed request body. Official numbers are never allocated offline. Saved body/signature are reused exactly on retry. The pending queue is bounded at 1,000 unresolved/non-Synced records to avoid unbounded browser growth; reaching it requires synchronization/reconciliation before more sales can be saved.

Encrypted state writes use IndexedDB read/write transactions with optimistic revision comparison. Encryption occurs outside the transaction, and the final compare-and-swap retries against a newer revision, preventing concurrent tabs from overwriting sales or spending the same local snapshot independently. A failed/quota-limited save is not presented as success. Clear/import also checks the current revision before replacing data.

Local stock is last coherent server stock minus unresolved paid + free demand. Acknowledgement alone leaves the reservation in **Awaiting reconciliation**. The next authenticated snapshot includes the exact submitted UUID receipts captured under the same server inventory gate as stock. Applying snapshot + receipts is one encrypted local update: only represented, accepted/reconciled rejections release their local reservations. Missing acknowledgements, failed submissions and unresolved conflicts keep their reservation. A refreshed shortage displays zero available and a warning rather than negative selling capacity.

This implements the A20/B20 reconciliation case: snapshot 100 with pending A20/B20 gives 60; after server accepts A, snapshot 80 plus A's receipt still gives 60 because only B remains reserved. Refresh never silently erases pending sales.

## Server acceptance, conflicts and administrator review

Provisioning reuses existing `devices`, `rep_operating_state.primary_device_id` and immutable `offline_policy_grants`. The grant records authorized customers, user/customer/product versions, authoritative prices, eligible immutable promotion rules and representative stock. It expires at seven days. No fake batches, offline official number ranges or stock balances are created.

Each foreground submission verifies fresh user role/activity, CSRF, device ownership signature, primary/revoked state, grant ownership/expiry, creation-time bounds, deterministic sequence, customer assignment/activity/version, active products, exact current prices/product versions and current promotion revision/eligibility. The existing Phase 8 review/issue services then recompute totals and verify current stock. Claims must match both the stored grant and current authoritative calculation. No agreed price, discount, free quantity or total is silently changed.

- A successful operation atomically posts the existing immutable invoice/items, cash receipt/payment allocation, paid + free stock deduction, balances, audit/stream updates and durable acceptance receipt. The shared inventory gate and existing lock discipline prevent overselling against concurrent online transfers/sales. Official numbering uses the existing lifetime server-side counters.
- Invoice channel `SYNC` preserves the real registered device and grant but has no device-generated official number allocation. The official business date is the **Asia/Colombo server acceptance date**; the original provisional creation timestamp remains in the invoice and immutable submission for audit.
- UUID/body hash, unique device sequence and original result receipts prevent duplicate invoices, cash postings or movements, including a lost response after commit. The online invoice route cannot reuse an identity reserved by the offline queue.
- Interrupted requests retain the local sale. A retry of an already accepted operation returns its minimal original receipt even if the customer was subsequently reassigned or its device revoked; this does not authorize a new posting or disclose new customer details.
- Stale/changed prices or promotions, expiry, reassignment/deactivation, insufficient stock, disabled credit, changed accounts and revoked devices become **Needs Attention** with the original signed payload and provisional cash retained. Unsafe submissions do not create invoices/payments/movements. Invalid proof or authentication fails before accepting a new command.

Admin review is paginated, with unresolved cases first. It displays original declared amounts, preserved signed data and previous immutable review/reconciliation notes. An admin may:

1. **Retry strict unchanged-term validation**, for example after a genuine stock discrepancy has been resolved. This cannot override expiry, device revocation, credit prohibition or changed pricing/rules.
2. **Reject after explicit external reconciliation**, with a mandatory reason, stock/cash reconciliation reference and attestation that stock was not delivered or has returned and provisional cash was reconciled externally. This records an audit fact; it does **not** execute a cash refund, fabricate a payment, rewrite an invoice or silently forgive collected cash. Original received amount and agreed total remain available for investigating applied cash/change. A coherent later snapshot can release that resolved local reservation; the rejected record remains visible.

There is no arbitrary repricing/force-accept button. Changed-term cases require external/customer resolution rather than a silent override. Do not create a second invoice manually while an unresolved sale might still post.

## Lost devices, replacement and recovery

Admins first **Revoke device now** with a reason. That device can never automatically post another sale or obtain a new grant. Existing signed but unaccepted submissions can still be uploaded by their authenticated owner for review; their signature proves origin, and revocation forces Needs Attention. Already accepted receipts remain idempotently retrievable.

Before **Permit replacement**, all known server-side unresolved cases must be resolved and the admin must explicitly attest investigation/reconciliation of physical stock, provisional cash and **unuploaded/unknown** lost-device activity. The server cannot discover a phone's unuploaded records. Only then is the primary device slot released. A new device gets a new key/UUID and fresh authoritative stock; old records are not rebound to it or automatically reposted.

Encrypted recovery exports preserve sale bodies, signatures, statuses and local snapshots. Restore requires an empty vault and the original export passphrase; existing records are never overwritten. Recovery does not export or clone a device's non-extractable signing key. On a replacement browser/device, restored data can be inspected and already-signed pending records can be uploaded for reconciliation, but new provisioning/refresh as the old identity is intentionally denied. Keep the retired vault/export for audit, reconcile it with the admin, then provision a clean replacement profile/device. Forgetting the passphrase, browser eviction without an export or losing an unsigned unsaved form cannot be recovered by the server.

## Service worker, installation and updates

The initial inspection found **no existing manifest, worker, icons or offline shell**. This phase adds a local manifest, 192/512 PNG icons and an explicit nine-file versioned static allowlist. The worker caches only the public offline HTML, its CSS/JS, manifest and icons. It never caches authenticated PHP pages, APIs, authentication responses, sessions, customer data or financial records. Other navigations remain network-only; on network failure they redirect to the public offline shell, keeping relative asset paths correct.

No background synchronization is registered. Online/offline events update reachability; **Sync Now** is an explicit foreground action and repeated clicks cannot start a second local run. Browser `navigator.onLine` alone is not treated as authenticated server connectivity.

Updates install a new static worker and **wait for existing tabs to close**. There is no forced skipWaiting/reload, automatic IndexedDB deletion or automatic pending-queue reset. Save before closing; export when appropriate. The app data schema remains versioned and compatible. The worker follows a static-only [fetch interception policy](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/fetch_event).

## Database migration and backups

Only migration **014_offline_sales.sql** was added/applied. Migrations 001–013 remain checksum-verified and unchanged.

- Adds the `SYNC` invoice channel and a CHECK distinguishing online, legacy offline and server-synchronized device/grant references.
- Adds `offline_device_controls` for public-key binding and sequence/replacement metadata.
- Adds immutable `offline_sale_payloads` and `offline_sale_reviews`.
- Reuses existing devices, policy grants, sync operations, invoice/payment tables, inventory balances/movements and audit records. It does not introduce another stock system.
- Live schema: **53 tables, 49 triggers**, all 14 migration checksums verified. All **49 pre-existing non-journal table counts and content hashes** were identical before/after migration. Existing real invoices, payments, customers, products and stock were preserved. No synthetic record was added to the live application.

Fresh deployment backup, taken and verified immediately before migration:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase9-deployment-20261109-130438\before-migration014.sql`

**139,710 bytes**; SHA-256 **3DE8EAF6A7E8FBB0FB0563C7682B71EFACC237B13C9FE4F7909548AF0447DFAA**. Verified exact database target, 50 table definitions, completion marker and file checksum. Metadata is beside the dump. The earlier Phase 9 backup is separately preserved at `phase9-backup-20261109-123237\before-phase9.sql`, SHA-256 **A03AEB1C97AEF9E1129176A61B0E0BB4F71E56D8C941FD91F4836EF6FEA4980E**. No destructive restore drill was performed.

A **new isolated `dharma_sales_phase9_test` database** and separate schema-scoped test account were created on port 3309 for synthetic records and successful committed race/browser tests. Credentials are only in the external private directory. No existing database was reset. Replaying the old location migrations reproduced their documented partial-007 collation issue; the existing verified 007/008 reconciliation procedure was explicitly scoped to the new empty test schema and completed without modifying old SQL. Live migrations 007/008 were already correct and untouched.

## Executed tests

| Verification | Passed |
|---|---:|
| Foundation / database constraints | 12 / 16 |
| Products / Customers / location lookups | 69 / 91 / 91 |
| Archived batch / current product stock regression | 124 / 59 |
| Representative regression (isolated fixture audit checks included) | 106 |
| Promotions / online invoices | 121 / 167 |
| HTTP authentication, roles, sessions, CSRF and screens | 303 |
| Existing inventory/representative/promotion contention | 7 / 11 / 11 |
| Existing invoice contention and preservation | 56 |
| **Regression/HTTP total for the recorded run** | **1,244** |
| New signed offline service and preservation checks | **97** |
| Successful multi-process offline shortage/duplicate financial posting checks | **7** |
| JavaScript unit/UI/crypto tests (including prior modules) | **23** |
| Real headless Edge browser checks | **25** |

All financial/synthetic Phase 9 tests ran in the isolated database. Actual browser tests use a temporary PHP server bound to an ephemeral loopback port and a fresh headless browser profile, not the live app database. Test processes are stopped after each run. Synthetic database records/profiles remain isolated for inspection; no existing database is erased.

The browser checks exercised offline shell reload, assigned-customer selection, multiple local cash sales, paid/free reservations, encrypted recovery export/restore, incorrect passphrases, actual synchronization, response loss after server commit, idempotent retry, exact stock reconciliation, static-only Cache Storage, a waiting worker update, concurrent local CAS writes, logout/reauthentication and 15-minute lock. Domain tests cover the A20/B20 proof, stale clocks, seven-day expiry, Colombo promotion-date boundaries, AES-GCM tampering and 30-day retention. Server tests cover second-device denial, signatures/ownership, stale prices and changed promotions, deactivated products, customer reassignment, offline credit rejection, expired authorization, revoked-device submissions, admin reconciliation, replacement and database immutability. Existing online regression suites now explicitly support the isolated application test target.

**90 PHP files plus both private CLI wrappers passed lint**, and JavaScript syntax checks pass. Read-only live checks confirm health ready, restricted runtime credentials, financial/stock reconciliation, offline acceptance invariants and unchanged **127.0.0.1:8088 / 127.0.0.1:3309** bindings.

## Browser developer-tools test instructions

1. For synthetic sales, use the isolated automated test environment below. Do not create fictional purchases in the live application. The live shell may be provisioned with real authorized data for read-only launch/locking checks; only save a live sale for a genuine transaction.
2. While online, register/refresh and wait for the service worker to activate. In DevTools Application, confirm only public static resources appear in Cache Storage. IndexedDB customer/sale content should be ciphertext, not names or cash JSON.
3. Use DevTools Network **Offline**. Reload the offline URL and unlock. Check assigned customer/product selection, last-sync/stale status and local stock. In the isolated test environment, save two sales and verify paid + free reservations and Pending Sync.
4. Reload while offline; unlock and verify records survived. Incorrect passphrases must reveal no customer/sale data. Manual lock must hide and clear sensitive rendered content. Restore online before Sync Now.
5. Synchronize. Confirm official numbers, one invoice/payment/stock effect per UUID, coherent available stock and preserved conflicts. The automated tests simulate a lost response after commit and repeat the request; do not deliberately create duplicate live transactions for testing.
6. Never click DevTools **Clear site data**, delete IndexedDB or unregister/erase application storage with unresolved real sales. Export a recovery file first and follow admin reconciliation.

Repeatable isolated commands:

```powershell
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php offline
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php concurrency
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php browser
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php auth
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php suite invoices
node --test tests/offline-domain.js
```

Do not rerun `prepare`: it deliberately refuses an existing test database/configuration. Read-only live integrity: `command.php offline-verify`, `command.php invoice-verify`, `command.php verify-runtime` and `command.php verify` from the existing private CLI wrapper.

## Real-phone / installed-PWA deployment limit

**No reachable HTTPS endpoint, real Android/iPhone installation or physical-phone test was verified.** Localhost on a phone refers to that phone, not this Windows computer. The verified loopback site does not establish mobile reachability. Service workers require a secure context ([platform requirements](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)); ordinary LAN HTTP is not a production mobile deployment.

After a separately authorized secure HTTPS deployment with trusted TLS, authenticated private access and correctly Secure session cookies:

1. Verify the actual HTTPS URL from the phone. Keep MariaDB/phpMyAdmin private and do not expose the current localhost Apache directly to the internet.
2. Install through Android browser **Install app** / iPhone Safari **Add to Home Screen**, then use that installed context for account sign-in and device provisioning. Do not register two browser contexts as separate active devices.
3. Refresh online, check assigned data and storage/recovery behavior, then use airplane mode and reopen. Verify unlock, safe provisional entry and restart retention with isolated test data first.
4. Reconnect, authenticate and manually sync. Verify official invoices and stock centrally, then test lock/logout and admin device revocation/reconciliation.

Until that endpoint and phone testing are completed, this phase is verified locally and in a real desktop headless browser, not certified as a deployed mobile service. No global XAMPP settings, WordPress, recovery backups or unrelated databases were changed.
