# Private mobile access — prepared, not enabled

9 November 2026. Owner approval checkpoint: Step 5 of the attached Mobile Access, PWA Installation & Real-Phone Testing request explicitly requires stopping before new network access or third-party service use.

**No mobile HTTPS URL exists yet.** No tunnel, VPN client connection, firewall/router rule, public hosting, new listener or database migration has been enabled. The existing app remains http://127.0.0.1:8088/ with MariaDB on 127.0.0.1:3309. Physical Android/iPhone/printer tests are NOT TESTED.

## Verified application and small installation fixes

The actual application has one manifest, one service worker, a public offline shell, encrypted IndexedDB vault, signed device registration, foreground Sync Now, role-scoped snapshots, server idempotency and admin reconciliation. Prior full verification is in [Phase 12 completion verification](phase12-completion-verification.md); those tests were executed, not inferred from phase labels.

Manifest name Dharma Chemical Sales, short name Dharma Sales, standalone mode and actual 192/512 PNG dimensions verified. The opaque green D icon has ample maskable safe-area margin. No replacement brand artwork was invented.

Changes for mobile installation:

- Shared installation metadata on login and both dashboards; Apple touch icon/title/capable metadata also on the existing offline shell.
- Removed stale login text claiming billing and offline sync were unavailable.
- Existing manifest **id remains ./dharma-offline**; start_url is now ./ so an online launch reaches login or the existing role-specific dashboard, rather than sending admins straight into a representative-only shell.
- A small public registration script on login/dashboard registers the **existing** service worker. Root navigation falls back to the existing offline shell on fetch failure; it does not cache private dashboards or add an offline admin engine.
- Static cache version v12-3 refreshes metadata; same eleven-file public allowlist, no skipWaiting, no local database deletion, no identifier or encrypted-vault changes. Save pending drafts and close all app tabs to activate a waiting worker. Never clear browser data to update.

## Verification results for this preparation

- PASS: 21 read-only public installation/manifest/icon/metadata/asset checks (`tests/mobile-installation.php`).
- PASS: foundation 12; database constraints 16; complete isolated workflow 32; final browser layout/navigation 573 (eight widths, including the new installed root-to-admin redirect assertion).
- PASS: actual encrypted IndexedDB/offline restart/retry browser 25; authentication/session/authorization 305; documents 31 plus real document browser 33; focused JavaScript domain/navigation 12. Test transactions/fixtures use only the dedicated test database. The final layout and PWA runs were repeated after the last launch/worker changes.
- PASS: Apache review candidate `httpd -t` and gateway PowerShell parser. Syntax only: the gateway has not been started or HTTPS-tested. The initial sandboxed syntax-check process stalled; only that identified `httpd -t` process was terminated, and the same candidate passed with runtime access. The preparation script now bounds its syntax check to 15 seconds.
- PASS: existing backup checksum and actual icon dimensions; live health ready. Previous complete Phase 1–11 regression/reconciliation evidence remains in the linked Phase 12 report; no business service or schema changed in this preparation.
- PASS: final PHP lint 116 files, JavaScript syntax 28 files, and final live financial/stock/offline reconciliation. Final listener inspection shows only the existing 127.0.0.1:8088 and 127.0.0.1:3309; no 8090 listener.
- NOT TESTED: real HTTPS mobile URL, proxy/cookie runtime behavior, Android/iPhone installation and physical usage, native share/printing. No user-reported phone results have been received.

Changed project files: `app/Views/pwa-head.php`, `login.php`, `dashboard.php`; `public/manifest.webmanifest`, `public/sw.js`, `public/offline/index.html`, `public/assets/js/pwa-install.js`; `bin/Prepare-MobileGateway.ps1`; `tests/mobile-installation.php`, `tests/phase12-browser.js`; this plan and the secret-free review candidates under `storage/mobile-access-review/`. No migration, account provisioning, live business transaction or global configuration edit.

## Recommended first test: Tailscale Serve, private HTTPS

Use Tailscale on the Windows computer and owner phone, restricted to the owner's identity and this app's HTTPS port. Serve is private to the tailnet; **do not use Funnel**, public tunnels, subnet routing, exit nodes or directory/file serving. See [Serve reference](https://tailscale.com/docs/reference/tailscale-cli/serve).

Proposed route:

```text
Owner phone + Tailscale
  -> private HTTPS :443, valid certificate, owner-only tailnet policy
  -> Tailscale Serve on Windows (TLS termination on this computer)
  -> 127.0.0.1:8090, NEW separate app-only Apache gateway
  -> existing public/ and restricted runtime DB connection to 127.0.0.1:3309

Existing 127.0.0.1:8088 remains unchanged.
```

Why a separate gateway: PHP currently sets Secure session cookies from server HTTPS state, not arbitrary forwarded headers. Directly proxying HTTP 8088 would leave cookies without Secure. The reviewed gateway sets HTTPS=on **only on its dedicated loopback endpoint**, rejects the wrong Host and non-HTTPS proxy protocol, serves only public/, disables indexes, denies private directories and retains application auth/CSRF. No generic forwarded-header trust was added to PHP. Local software running under the Windows account is within this trust boundary; this does not defend against a compromised PC. Existing source-IP login limits remain conservative and shared behind the loopback proxy; arbitrary forwarded client IPs are not trusted. The installed unsupported PHP/MariaDB versions still restrict this to supervised owner testing, not regular representative rollout.

| Situation | Expected behavior after configuration; not yet phone-tested |
| --- | --- |
| Same Wi-Fi | Works with both devices connected to the approved tailnet and server services running. |
| Phone mobile data | Works with internet, Tailscale and the Windows computer online. |
| Computer off/asleep | Backend, login, refresh, sync, reports and final PDFs unavailable. |
| Internet unavailable | Already provisioned representative offline shell/drafts may work within valid seven-day snapshots. No first-time login/provisioning or server confirmation. Do not rely on a new VPN connection forming offline. |

Trust/cost: Tailscale handles account/device/network metadata and coordinates connections; encrypted relay packets do not expose plaintext application data to its relay. The client software and identity provider are trusted dependencies. HTTPS terminates on the PC; databases are not uploaded. Certificate transparency publishes the certificate hostname, so use a neutral node name. Protect the owner account with MFA/device approval and do not invite representatives during owner testing. [Encryption](https://tailscale.com/docs/concepts/tailscale-encryption), [HTTPS/certificates](https://tailscale.com/docs/how-to/set-up-https-certificates).

Pricing checked 9 November 2026: Personal is non-commercial; do not assume this business test qualifies. Standard is advertised at USD 8/user/month; confirm current terms, billing and any trial before subscribing. No plan/account/purchase has been created. [Pricing](https://tailscale.com/pricing). Dedicated hosting/domain/backup costs are separate and have not been quoted or purchased.

## Exact proposed configuration and execution order AFTER approval

The following is a review/runbook, **not commands already executed**. Actual account, device DNS and access policy must be inspected before activation.

1. Install signed Tailscale clients from the official Windows/Android/iOS sources. This installs networking software and may create its own adapter/firewall rules: review these changes explicitly; never disable Windows Firewall. Sign in interactively on PC and phone with the approved identity. Do not paste auth keys/passwords into commands, code or logs. No router port forwarding.
2. Use a new owner-only tailnet or review all existing grants/ACLs. Remove broad allow-all access for this test; adding a narrow grant alongside a broad grant does not restrict it. Enable MagicDNS and HTTPS certificates after accepting hostname publication. Disable/unconfigure Funnel, device sharing, subnet routes and exit nodes for this test. Do not overwrite an existing unrelated tailnet policy automatically.
3. Proposed policy below requires substituting the actual approved identity and Windows Tailscale IPv4 /32. It is intentionally not directly applicable with placeholders. Also verify there are no IPv6/broad/legacy ACL grants allowing extra access. Validate policy tests: owner phone can reach PC 443; other identities cannot; 3309/8088/8090 are denied through the tailnet. [Grants syntax](https://tailscale.com/docs/reference/syntax/grants).

```json
{
  "grants": [
    {"src": ["OWNER_LOGIN_EMAIL"], "dst": ["WINDOWS_TAILSCALE_IPV4/32"], "ip": ["tcp:443"]}
  ]
}
```

4. Reverify the application backup and pending-device status before persistent gateway configuration. Existing verified backup: `C:\mariadb-clean\ayurveda-phase1-local\private\phase12-20261109115701\application.sql`, SHA-256 `fc4471042e6a9478f9459e82d4b6ae053f3368e5689687d85af45cb072499011`. Existing isolated restore matched 53 tables, 141 rows, 49 triggers and 14 migration checksums; backup checksum reverified for this request. Source invoices/products/customers/payments remain untouched. No off-device copy destination has been approved or configured; this remains a recovery requirement.
5. Obtain actual DNSName from the logged-in local Tailscale status. The following PowerShell is to be run only after approval, in the project directory. It prepares a new private gateway candidate, reusing restricted runtime credentials from the existing private JSON; it never prints them, starts servers or modifies the 8088 config. Existing candidates are refused, never overwritten.

```powershell
$ts = 'C:\Program Files\Tailscale\tailscale.exe'
$tailnetStatus = (& $ts status --json | ConvertFrom-Json)
if ($LASTEXITCODE -ne 0 -or $tailnetStatus.BackendState -ne 'Running') { throw 'Tailscale is not connected' }
$mobileHost = $tailnetStatus.Self.DNSName.TrimEnd('.')
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\bin\Prepare-MobileGateway.ps1 -MobileHost $mobileHost -PreparePrivateConfiguration
```

The process-only ExecutionPolicy option does not change machine policy. Candidate files go outside the served tree into the already restricted private folder. Review their ACLs and syntax. The secret-free example at `storage/mobile-access-review/httpd-mobile.conf` has passed `httpd -t`; it is an example hostname with no credentials, not a runnable mobile installation.

6. Confirm 8090 is unused and existing services still bind only loopback. Start only the new app-specific Apache process after review:

```powershell
$mobileConfig = 'C:\mariadb-clean\ayurveda-phase1-local\private\httpd-mobile.conf'
if (Get-NetTCPConnection -State Listen -LocalPort 8090 -ErrorAction SilentlyContinue) { throw 'Port 8090 is already in use' }
& 'C:\xampp\apache\bin\httpd.exe' -t -f $mobileConfig
if ($LASTEXITCODE -ne 0) { throw 'Gateway syntax failed' }
$mobileProcess = Start-Process 'C:\xampp\apache\bin\httpd.exe' -ArgumentList ('-f "'+$mobileConfig+'"') -WindowStyle Hidden -PassThru
```

7. In an Administrator PowerShell, first inspect `tailscale serve status` and `tailscale funnel status`; stop if there is an existing unrelated configuration. Start a **foreground**, HTTPS-only private proxy (no --bg, no Funnel):

```powershell
& 'C:\Program Files\Tailscale\tailscale.exe' serve --https=443 http://127.0.0.1:8090
```

Accept Serve/certificate setup only for the reviewed private tailnet. Use the actual URL printed by this command; no example hostname is a verified URL. Do not configure a path prefix or serve project folders.

8. Before phone login, verify valid HTTPS without certificate bypass, login/manifest/icons/CSS/JS/health, same-origin API URLs, no mixed content, root launch, Secure+HttpOnly+SameSite=Lax session cookie, session rotation/logout, CSRF failures and admin/rep boundaries. Wrong-host/non-HTTPS backend requests must fail; private paths must remain inaccessible. Check DB stays loopback. Check the HTTPS URL cannot be reached when phone Tailscale is disconnected (use a fresh uncached network fetch, not a cached offline shell). Check no Funnel/public exposure. A failed cookie/header test blocks use; do not weaken cookies to work around it.

Only then record the exact HTTPS URL and authorize owner login/read-only browsing of live data. Creating synthetic invoices/offline sales against the live schema is forbidden. Full mobile posting tests require a separately approved disposable staging configuration/schema and restricted test account; never point the owner phone at the accumulated automated test fixtures or expose their credentials.

## Installation once the URL is verified

**Android Chrome:** connect Tailscale; open the verified HTTPS URL in Chrome (not an embedded social-app browser); sign in; menu → Add to Home screen → Install app, or the Install app option offered by the browser; confirm; launch Dharma Chemical Sales from the icon and verify the correct role dashboard. [Google instructions](https://support.google.com/chrome/answer/9658361?co=GENIE.Platform%3DAndroid&hl=en).

**iPhone Safari:** connect Tailscale; open the verified URL in Safari; Share → Add to Home Screen; enable Open as Web App where offered; Add; launch from the icon and sign in in that installed context. [Apple instructions](https://support.apple.com/guide/iphone/iphea86e5236/ios). Older iOS versions may use different wording. Apple metadata supports older home-screen behavior; actual iOS support is not claimed without testing.

For a representative, sign in and **register/provision in the installed app**, not first in Safari/Chrome and again as a second device. One active device per representative is still enforced. An admin uses online dashboards; an offline admin dashboard has not been implemented. First online login and authorized refresh are required. Do not reuse a production representative identity for a disposable staging test.

Browser/OS storage remains subject to eviction and user deletion, even with encryption or a persistence request. Keep encrypted recovery exports and the passphrase separately. Do not assume Safari and the installed app share cookies/storage. No Background Sync dependency exists: reopen, reconnect, unlock and press Sync Now in the foreground. iOS may suspend background pages. [WebKit storage policy](https://webkit.org/blog/14403/updates-to-storage-policy/).

## Real-phone acceptance — pending owner interaction

Record phone model, Android/iOS/browser version, installed mode, date, environment URL and actual results separately from automated checks.

- On the approved live read-only test: login/logout, both available role screens using authorized accounts, mobile drawer, forms without saving, table swipes, Products/Customers/stock/promotions/history/reports, and existing invoice A4/receipt/PDF. Native download/share/printing must not post any transaction. No direct Bluetooth claim.
- On disposable staging only: provision an authorized test representative, set a separate passphrase, refresh, verify last-sync time and stock, disable Wi-Fi/mobile data, restart installed app, unlock, save a provisional cash draft, restart and confirm persistence, export encrypted recovery, reconnect and Sync Now; retry after response loss and prove one server invoice/payment/stock effect. Check free quantities, insufficient stock, stale snapshots and changed-price conflicts.
- Verify wrong passphrase rejection, 15-minute lock with no customer details visible, seven-day expiry stopping new sales while retaining pending records, revoked devices never auto-posting, and unresolved drafts never pruned. Only reconciled acknowledged history follows the existing 30-day cleanup rule. Offline credit remains disabled; no automatic refunds.
- For PDF/receipt acceptance, use actual intended printers and Android/iOS print services. Rendering/layout is automated-tested; device share chooser, printer margins/cutting and physical output are not.

## Rollback and future business deployment

Foreground Serve can be stopped with Ctrl+C. Verify `tailscale serve status` afterwards; if this was the only approved Serve mapping and it remains, disable only that mapping with `tailscale serve --https=443 off`. Never reset unrelated Serve settings. Stop only the app-specific gateway via:

```powershell
& 'C:\xampp\apache\bin\httpd.exe' -k shutdown -f 'C:\mariadb-clean\ayurveda-phase1-local\private\httpd-mobile.conf'
```

Confirm 8090/Serve are no longer available and 8088/3309 remain unchanged. Disconnect owner testing devices/remove only the test grant with review. Preserve gateway files, backups, device registrations, pending sales and recovery exports; do not clear vaults, reset the database or revoke a device with unreconciled sales just to undo access. Do not roll the manifest/service worker back by clearing storage.

For regular company use, prefer a dedicated managed HTTPS host with supported PHP/MariaDB, private DB, monitored encrypted off-device backups, deliberate trigger-definer/grant restoration, patching and reliable availability. It must operate independently of this personal PC. A private-access layer can remain useful for a small team; business hosting/provider selection and cost need separate approval. Installed offline selling continues only within its existing snapshot/auth/stock constraints; invoices become authoritative only on accepted synchronization. No hosting/domain purchase or live migration has occurred.

## Approval requested

Approve installing/connecting Tailscale on the owner PC and phone, owner-only tailnet HTTPS/certificate setup (including possible client adapter/firewall changes), and the separate loopback gateway on 8090 for supervised private testing? This does **not** approve public Funnel, broad network access, production deployment, purchases or synthetic live transactions. If approved, configure and verify the real URL next, then conduct physical installation with the owner.
