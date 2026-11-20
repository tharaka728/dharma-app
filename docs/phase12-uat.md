# Phase 12 — manual acceptance checklist

Automated tests use only `dharma_sales_phase9_test`. **Do not type fictional sales, stock or customers into the live localhost application.** Use existing records for read-only acceptance; perform write acceptance only with approved real business entries or in a separately prepared disposable UAT instance. The automated disposable workflow command below covers the complete write sequence without touching real records.

```powershell
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php phase12
```

This creates isolated fixtures, verifies two concurrent automatic-code races and the financial/stock workflow, runs the responsive browser checks on an ephemeral loopback server, then stops its test processes. It does not print fixture passwords or create live users.

## Local acceptance URLs

| Screen | URL / action | What the owner should verify |
| --- | --- | --- |
| Login | http://127.0.0.1:8088/login | Generic failure for a wrong password; correct role workspace on login; keyboard focus visible. |
| Admin dashboard | http://127.0.0.1:8088/admin | Full-card links, distinct SVG icons, no Available badges; menu toggles, Escape closes, clicking outside closes on mobile. |
| Representative dashboard | http://127.0.0.1:8088/rep | Own stock/history and rep-only links; direct admin URL is forbidden for a representative. |
| Product create | http://127.0.0.1:8088/admin/products/new | Code is optional; leave blank for generated PRD code, or use an authorized explicit SKU. On an approved save, details show the actual code. Failed validation retains values. |
| Customer create | http://127.0.0.1:8088/admin/customers/new | Optional generated CUS code, searchable district/town, persistent admin-added towns, incompatible town cleared, duplicate-phone review retained. |
| Inventory | http://127.0.0.1:8088/admin/inventory | + Add Stock is visible; no batch/supplier/expiry entry. Swipe inside the stock table without sideways page movement. |
| Add Stock | http://127.0.0.1:8088/admin/inventory/add | Product, quantity and current selling price; no manual stock UUID; approved receipt increments stock once. |
| Adjustment | http://127.0.0.1:8088/admin/inventory/adjustments | Mandatory reason, no negative result, audit history preserved. |
| Representatives | http://127.0.0.1:8088/admin/representatives | Usernames remain meaningful login identities; internal IDs are automatic. Password creation/reset is masked and admin-only. |
| Allocate / return | http://127.0.0.1:8088/admin/stock/allocate and http://127.0.0.1:8088/admin/stock/returns | Choose representative/product/quantity; no typed transaction reference. Warehouse and rep quantities change equally/oppositely. |
| Promotions | http://127.0.0.1:8088/admin/promotions | Existing price/promotion controls, mandatory fields and review remain usable on mobile. |
| Invoice | http://127.0.0.1:8088/admin/invoices/new and http://127.0.0.1:8088/rep/invoices/new | Review paid/free units, discount, change and outstanding before any approved issue. No manual final number. |
| History | http://127.0.0.1:8088/admin/invoices | Search/filter/pagination, detail, payment and admin void controls remain available. Use disposable automated workflow for reversals unless a real business void is intended. |
| Offline | http://127.0.0.1:8088/offline/index.html#status | Menu works while shell is available; sensitive workspace stays locked until authorized unlock. Do not clear actual browser data for testing. |
| Offline drafts | http://127.0.0.1:8088/offline/index.html#new and `#pending` | Drafts remain PROVISIONAL; no official number before acceptance. Wrong passphrase shows no customer data. Real-device offline/restart tests remain pending. |
| Reports | http://127.0.0.1:8088/admin/reports and http://127.0.0.1:8088/rep/reports | Definitions collapsed under How reports are calculated. Current-balance/cohort/stock-estimate warnings remain clear. Unavailable refunds are never zero. CSV and filters work. |
| Printing | Existing invoice detail → Print Receipt / Print A4 / Download PDF | Correct original snapshots and VOID/payment status, 80 mm or A4 selected in printer dialog, no header/menu on paper. Actual printer, Android print-service and sharing acceptance pending. |

## Device and screen matrix

Repeat visual acceptance at 320, 360, 375, 390, 414, 768, 1024 and desktop widths. Confirm that forms/buttons remain readable, page width fits the screen, long references wrap, and wide tables scroll only inside their own region. Keyboard users should reach the menu, close button and links; Tab stays within the mobile drawer and Escape restores focus to the menu. Verify desktop content resizes beside an open drawer.

Actual Android/iPhone installation, touchscreen gestures, on-screen keyboards, VoiceOver/TalkBack and physical printer output were **not** tested on hardware. These require owner sign-off before representative rollout. Once an authorized HTTPS staging deployment exists: Android Chrome/Edge uses its Install/Add to Home Screen option when offered; iPhone/iPad Safari uses Share → Add to Home Screen. Browser wording/capabilities vary; consult [installation guidance](https://web.dev/learn/pwa/installation). This computer's `127.0.0.1` URL is not a phone-accessible endpoint. Do not expose local XAMPP to make it reachable.

## Operational acceptance

- Owner reviews one complete isolated automated cash/credit/partial-payment/void workflow and reconciliations.
- Owner confirms real users, product prices, stock opening quantities, customer assignments and credit permissions; the agent did not invent them.
- Verify a secure off-device backup copy and identify its custodian; this phase verified local backup/restore only.
- Rehearse a lost-device case: revoke, reconcile known and unknown provisional cash/stock, retain unresolved records, then approve replacement. Never assume unsubmitted drafts are visible on the server.
- Confirm how an administrator recovers a lost password under a secure, audited procedure. No email/self-service recovery was added.
- Record actual printer model, paper/margins, phone/browser versions and pass/fail results. Generic Bluetooth ESC/POS support is not claimed.

No public deployment is authorized by this checklist.
