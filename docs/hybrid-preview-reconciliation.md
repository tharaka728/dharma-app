# Owner-preview reconciliation for the Stage 6C Git checkpoint

26 November 2026. Additive review record; historical Stage 6A/6B/6C reports and inventories are unchanged. This checkpoint is source-only, not deployment, activation or financial-posting approval.

## Reviewed files and exact current bytes

| File | Current SHA-256 |
| --- | --- |
| `tests/hybrid-ui-bridge.php` | `8edcb478c8c31a73bd46cf15e42060cc85e0b15c924f635bd437e956c6a686fa` |
| `tests/hybrid-ui-router.php` | `2d4b521d834660904988f70d9fc96d42b0d82eaabddb7b7b51846f8154d1055e` |
| `tests/hybrid-ui-preview.ps1` | `0b82ae09c318609128c42166c8db5ecba76abf45d60e38d5430eb56ec9aa446f` |

The requested PowerShell path is the existing file `tests/hybrid-ui-preview.ps1`; there is no separate `hybrid-ui-preview/` directory.

Stage 6A recorded these earlier PHP hashes:

```text
bridge: f5a5d90d19e08f0d0f383f66a1a849d41801a641d5275cc76ae1e594909d8980
router: 7393a8de5d89d20e1186b7a593790799871470e4ab4167a8620371dc8a98ac92
```

Both original Stage 1 helper copies still match Stage 6A's transferred-file hashes. Reviewed no-index diffs separate the documented Stage 6A combined Dashboard/Profile routing and authorization from the later preview changes. Removing only the identified preview additions from the current files reconstructs **both exact Stage 6A integrated hashes**, without normalizing or editing the historical files. Thus all differences from the Stage 6A snapshots are accounted for.

All three current hashes also match the preflight source hashes captured before Stage 6C in its ignored `source-before.json`. The Stage 6B/6C reports explicitly describe preserving these preview corrections. The PowerShell launcher belongs to the separately authorized minimal isolated preview workflow and was omitted from the older inventories; it is not unexplained new application behavior.

## Reasons for each difference

**Router:** the authorized owner demo adds an early loopback/method/path allowlist, safe public-static extension/path checks, a fixed synthetic representative session and posting-held mode. Only the root redirect, Hybrid form/options, eligibility and preview are allowed. All other application routes are rejected before bridge dispatch. Existing broader synthetic test routes remain available only outside preview-only mode and are unreachable in the owner demo. There is no fallback to `public/index.php`.

**Bridge:** preview-only validation now occurs before manifest/PDO work, accepts only the intended methods/routes and explicit Hybrid policy, rejects mutation actions, forces the synthetic representative/posting-held mode, and disables Stage 4 return/custody setup in preview mode. The subsequent stable-ID correction filters the displayed picker/options to the current fixture's stable IDs. It neither broadens approved offer bindings nor changes the authoritative resolver, calculation or stock logic. Forged older-fixture IDs still fail.

**PowerShell:** the launcher uses the existing disposable fixture, binds HTTP only to `127.0.0.1:63825`, rejects forbidden database ports, verifies physical database scope and source holds, and sets the synthetic controls to preview enabled with sale/return/custody posting disabled. Normal disposable MariaDB mode permits the existing SELECT FOR UPDATE preview path; the two default-deny boundaries provide demo write protection. Stop verifies the owned HTTP process and uses existing physical-scope shutdown helpers. Generated keys/accounts are read/generated at runtime; none are hardcoded in the script. Its start/stop actions were reviewed but not run during this checkpoint, preserving the running demo and its controls.

## Targeted safety evidence

Only the already running owner demo and its proved disposable database at port **63824** were used. No operational port 3306/3309, migration or financial posting suite was accessed/executed.

- PHP lint passed for both reviewed PHP files; PowerShell parser reported no errors.
- Existing real Edge preview smoke: **51 assertions passed**. Glucose 6/8/12 and Suwada Kudu 12 displayed expected qualified/remainder/free quantities and discounts. Glucose 8 at 100.00 with 25% remainder discount and no invoice discount showed P=50.00 and payable=750.00. Wrong stable-ID binding remained 409; confirmation remained disabled.
- Additional HTTP boundary checks: **17 probes passed**, including actual invoice issue/payment/void routes, return posting/receipt/manager routes, stock/offline/test endpoints, direct entry files and method-confusion attempts. Every response was the router's explicit preview-only 403.
- Direct CLI bridge checks: **6 passed**, including forbidden issue/payment, mutation action, legacy payload and incorrect method cases. They were rejected before a sentinel manifest could be opened and before any PDO connection.
- Before/after read-only snapshots: **all 90 table row counts and deterministic data digests were identical**. Sale posting, return posting and custody controls remained zero. No stock, payment, invoice, command, snapshot, audit or other database row changed.
- Source review found no literal credentials, stored tokens, private keys, operational DSNs or default-deny bypass in these files. Operational port numbers occur as exclusions. General synthetic test mutation branches cannot be reached while preview-only mode is active.
- The 12 protected baseline files, including approved Dashboard/Profile designs and shared CSS/translations, still match. No application source, financial gate, posting hold, runtime configuration or original worktree was changed during this reconciliation.

Ignored supporting evidence is under the integration worktree's `storage/checkpoint-review-*` directory: reconstructed baseline copies, source-scan findings, targeted smoke/boundary results and before/after digests. None of that generated evidence, private runtime data or credentials is a source checkpoint candidate.

## Consolidated checkpoint review

The original 131 candidate files were reconciled against the latest Stage 6A/6B/6C overlays. The only preview gaps were the three files above; the three historical inventory files exclude their own digests by design. All other latest recorded hashes match. All original migration working-file hashes match the prepared contract. The normal migrator, source activation defaults, financial posting holds and existing pilot settings remain unchanged.

Every candidate was read for source/security review, with tracked semantic diffs and high-risk connection/dispatch/credential code inspected separately. Automated scanning covered **131 files / 10,183 lines** and reported no potential private-key, credential/token or forbidden hardcoded-DSN matches. Fixtures are synthetic generators; no real customer records, SQL dumps, databases or generated evidence were identified among the candidates. Runtime credential references/generation are source code, not credential files.

The final checkpoint adds this reconciliation and `docs/hybrid-checkpoint-inventory.json`: **133 explicit files**, comprising reviewed source/tests/documentation/migration source. The consolidated inventory records exact current bytes for every selected file except itself, including the unchanged historical inventory files. Historical hashes are not rewritten to conceal preview differences.

Git's system configuration enables `core.autocrlf`. Explicit staging uses a per-command `core.autocrlf=false` override so reviewed file bytes can be compared directly with index blobs; no persistent Git configuration or working-file normalization is performed. Existing original migrations 001–024 are not restaged, and the documented pre-existing raw 007 difference remains intact. Future checkout/deployment raw-byte checks remain a release-preflight requirement.

Excluded categories: `.env` and private configuration, account/credential/key files, runtime manifests and IPC/session files, database/data directories, backups and SQL dumps, browser profiles/screenshots/PDF output, logs and temporary/generated evidence. Source references to ignored fixture filenames are not the files themselves.

The reviewed target is only `origin` → `refs/heads/hybrid-promotion-v1-stage6a-integration` in `https://github.com/tharaka728/dharma-app.git`. The matching feature branch was absent at preflight. No main merge/push, tags, force-push, PR, deployment, activation or genuine posting is part of this checkpoint. Commit and remote verification are reported after the reviewed Git operations succeed.

## Checkpoint authorization blocker

The feature-branch-only push dry run was rejected by GitHub with HTTP 403: permission to `tharaka728/dharma-app.git` was denied to the authenticated account `Hypeinsight`. The dry run changed no remote ref. Git operations stopped before staging or committing; HEAD remains `1f6385c35eb664251322b5176a8256fa6efe40ba`. No feature-branch commit or remote checkpoint was created.

Source/provenance and targeted preview checks passed. The consolidated 133-file selection is prepared for review, not staged or committed. Resume only after Git is authenticated with an account authorized to write this repository, or the repository owner grants the current account write access. Do not paste credentials or tokens into source, documentation or chat.

## Authorization verified on approved resume

After the owner added both accounts to Git Credential Manager, the repository credential was explicitly selected as `tharaka728`. GitHub's authenticated-user endpoint confirmed login `tharaka728`; the repository endpoint confirmed write permission to `tharaka728/dharma-app`. A dry run restricted to `HEAD:refs/heads/hybrid-promotion-v1-stage6a-integration` succeeded. The branch was still absent, and the dry run changed no remote ref. The earlier 403 is retained above as historical evidence; its authorization blocker is resolved.

On this resume, all 133 approved paths and their recorded hashes, all original migration hashes, the protected Dashboard/Profile baseline and secret/artifact exclusions were rechecked successfully. Only this authorization note and its consolidated-inventory metadata/hash are updated. Application source, the three preview files and historical Stage 6A/6B/6C inventories are unchanged. Commit and remote SHA are reported after the approved feature-branch checkpoint succeeds; this file records the review immediately before staging.
