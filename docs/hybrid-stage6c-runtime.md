# Explicit fresh installation and isolated HTTP runtime

These entry points are integrated source for Stage 6C isolated acceptance. They are not operational activation or deployment instructions.

## Selected fresh-install workflow

`bin/migrate.php` and `App\Support\Migrator` are unchanged. They do not discover the preparation SQL in `database/fresh-install/`. Existing installations therefore receive no new automatic compatibility DDL.

The opt-in command is:

```powershell
C:\xampp\php\php.exe bin\hybrid-fresh-install.php --new-isolated --manifest="<verified disposable run>\manifest.json" --contract-sha256=a9e8f370fc2719d6e4476467d9e218e6edead003f96ce5399927bb959c9eb251
```

Run only from this integration worktree, on a separately provisioned disposable manifest. It never loads `.env` or the normal connection factory. Ports 3306/3309 are rejected before PDO; exact server port/data directory/schema, one run identity and schema-only migration grants are verified. Supported metadata is pinned to MariaDB 10.11.16 and the two tested schema-default collations. Other versions/shapes fail closed.

The command accepts only an empty new application installation (the single bootstrap identity table is permitted). Existing, populated and partially installed schemas are refused, including a completed earlier install. It preserves raw checksums for all 001–027 files, inserts the separately checksummed 006a preparation between completed 006 and original 007, executes original 007 normally, and marks each migration applied only after completion. The exact full metadata and seed-data pre/postconditions are checked around preparation. No migration is skipped or silently marked complete.

The library retains the explicitly tested recovery primitive for a precisely journaled preparation interruption. The new command does not blindly resume a partial installation. Preserve failure evidence; use a newly provisioned installation/restoration or a separately reviewed recovery operation. Never reset an existing schema or remove an `applying` journal entry to force a retry.

Raw 007 bytes still differ between checkouts:

```text
integration: f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a
local main:  161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c
```

This existing line-ending difference was not normalized. Before any future operational release, an authorized preflight must compare the actual deployed raw file with its existing journal checksum. No deployed journal was inspected during Stage 6C. The original unassisted chain still reproduces 1267; only the explicitly selected prepared installation passes.

## Integrated isolated HTTP application

Application entry: `public/hybrid-isolated.php`; worker: `bin/hybrid-worker.php`.
Normal `public/index.php`, Apache/XAMPP configuration and the owner's preview router remain unchanged.

The entry point is unavailable unless `HYBRID_ISOLATED_PROFILE` explicitly selects a valid private profile under a physically verified disposable run. It rejects operational endpoints, mismatched identity/grants, non-loopback clients, missing private files and unexpected policy configuration. Source activation remains false and the source financial hold remains true. A profile can override those values only inside the verified synthetic context; it cannot target an operational database. The original CLI physical writer guard is still mandatory.

The isolated test harness prepares a dedicated `stage6c-runtime-<24 hex>/` directory. Before writing any secret, its Windows DACL is protected from inherited broad Users/Authenticated Users access and limited to the service identity, owner and system administrators. The ACL verification record is a deployment precondition, not client input. Child files inherit that private ACL. POSIX deployments would need equivalent verified owner-only permissions and separate platform acceptance; no such deployment is claimed here.

Private files:

- `profile.json`: fixed mode, manifest, worker deadline and slot limit; no browser-supplied paths.
- `credentials.json`: runtime schema-only DML credentials, never DDL/admin credentials.
- `key.txt`: per-run random IPC signing key; not passed in a URL, command argument or client response.
- `policy.json`: server-owned configuration/bindings/holds for this disposable context.
- `ipc/` and `sessions/`: bounded private job files/slot locks and real authenticated PHP sessions.

The real application Authentication, Session, CSRF, authorization and invoice/return controllers execute. The ingress revalidates the session and CSRF, restricts routes to the intended Hybrid online actions, and signs a server-derived request context. The CLI worker verifies MAC, job identity and expiry, independently proves database identity/grants and reloads the current actor/password stamp/permissions. Product/offer resolution, fingerprints, immutable evidence, posting holds, atomic transactions and command idempotency remain the existing authoritative implementation.

Worker execution has 1–4 configured slots, 100–15,000ms parent deadlines, a fixed executable/script, bounded request/response files, a 15-second PHP execution limit, a five-second lock wait and ten-second statement timeout. The test context defaults to two slots and 8,000ms. No worker accepts an HTTP executable or database target. Job files are removed after worker termination; secrets and errors remain private.

Timeout, lost/malformed acknowledgement and unexpected errors after possible financial dispatch return a safe 503/uncertain outcome. They must not claim “nothing posted.” Retry the same command UUID and payload; accepted command replay handles an already committed outcome. Explicit controller validation/hold rejections retain their structured status codes. Tests cover a committed invoice whose reply is deliberately delayed until the parent times out, followed by an accepted replay with no duplicate invoice.

The separate runtime uses server-rendered login and actual Hybrid UI. It has no synthetic session-setting endpoint and no default fallback into legacy/offline/stock/payment writers. The owner's existing preview remains a different server with its default-deny write boundary intact.

## Remaining release conditions

The integrated runtime remains deliberately restricted to disposable identity manifests. It is not an approved operational identity policy or a production service deployment. Release review still needs approved operational identity/grants, service-account ACLs and supervision on the target host, deployed raw-checksum preflight, backup/recovery acceptance, native Sinhala approval and physical printing evidence. Do not remove the physical guard, source holds or preview boundary to make a deployment appear ready.
