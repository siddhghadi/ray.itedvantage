# Insurance & Investment CRM — local build

This module follows RAY's PHP stack and uses a separate SQLite database with transactions, foreign keys, tenant scoping and integer-paise amounts. **Not deployed to production.** Existing RAY businesses and their data are untouched. The Insurance business route is wired locally; the standalone entry is `/insurance/`.

## Run with PHP 8.2+

Required extensions: PDO SQLite, fileinfo, session, JSON. Put private storage **outside the web root**. Example PowerShell:

```powershell
$env:INSURANCE_DATA_DIR = 'D:\Sid\insurance-private-local'
$env:INSURANCE_ADMIN_EMAIL = 'your-admin@example.com'
$env:INSURANCE_ADMIN_PASSWORD = Read-Host 'Choose an initial password (12+ characters)'
php insurance/bin/console.php init
Remove-Item Env:INSURANCE_ADMIN_PASSWORD
php -S 127.0.0.1:8766 insurance/router.php
```

Open `http://127.0.0.1:8766/insurance/`. Sign in as platform admin, create a **Demo agency**, then sign out and sign in as its agency admin. Credentials are supplied by you, not hard-coded. RAY owner single sign-on enters platform administration only if a matching platform account has been provisioned. It never bypasses agency support consent.

Password recovery: `php insurance/bin/console.php reset agent@example.com` prints a one-use 30-minute token. Share it securely with the verified account owner, who uses the reset form. Automated reset email is not connected.

## Scheduler (no browser/session required)

Run every 15 minutes, with the same private-storage environment:

```text
php /absolute/path/insurance/bin/console.php reminders
```

The job respects agency timezone and delivery time. Offsets are **days**, not calendar months; negative values mean overdue. Missed runs create the most recent applicable milestone, rather than flooding all old milestones. A uniqueness constraint prevents duplicates. Paused/archived/disabled/cancelled records are excluded. Only in-app notifications are implemented; no external messages are sent.

## Implemented

- Separate platform, agency admin and assigned-client staff access; server permissions; agency creation/suspension; one-hour read-only support consent and access audit.
- Agency module/category preferences, timezone, reminder offsets, widget visibility/order (comma-separated keys), light/dark UI, desktop sidebar and mobile menu.
- Persistent client records, family notes, policies, investments, leads, claims and commissions; edit/version checks; archive/restore; duplicate email/phone checks; lead-to-client conversion.
- Separate expiry, premium, contribution, maturity and follow-up events; dues filters; partial/full manual payments; preserved month-end recurrence; renewal creates a new period and keeps prior records.
- Background in-app reminders; consent-gated email/WhatsApp compose links (not delivery receipts).
- CSV mapping, preview, validation and duplicate skip; permission-scoped CSV exports with formula injection protection.
- Private validated PDF/JPEG/PNG uploads and authorized downloads; client-linked files.
- Product catalogue, manual official quote snapshots, print/Save as PDF, and assumed-return SIP/step-up/lump-sum/goal/FD/RD/retirement-accumulation projections.
- Collections/outstanding ledger, searchable/paginated entity lists, audit records, login/write throttles, CSRF and output escaping.

## Not yet complete (do not call this production-ready)

- Structured family entities, configurable category field/document/event schemas, insurance-vs-investment category filtering, agency logo upload and currency selection.
- All client-profile tabs, complete activity/history UI, full recurring-event materialization for multiple unpaid instalments, category reminder defaults and assigned-staff filters.
- Native XLSX import, side-by-side comparable plan views, shared platform catalogue, verified rate-table import/validation/test/publish/rollback workflow.
- Provider-specific insurer/messaging/payment adapters, encrypted credential settings, retry/delivery webhook processing, email password recovery, staff account editing/deactivation UI.
- Granular claims/commission workflows, advanced report exports, drag-and-drop dashboard ordering and every metric's exact corresponding list filter.
- Malware scanning for files, production load review and production browser/device acceptance testing.

No invented premiums, tax rates, live prices, payment confirmations or actual investment valuations. Insurance pricing remains **Quote required**. FD/RD use disclosed monthly-compounding mathematical estimates, not bank-specific conventions or contractual promises.

## Verification

`insurance/tests/domain.php` must use a fresh disposable `INSURANCE_DATA_DIR`; never a real database. Covers cross-agency/staff access, module disable preservation, duplicate prevention, partial payments, independent dates, February/leap-year recurrence, reminder reruns, paused SIPs, support permissions and calculator references. Additional local HTTP checks are in `insurance/tests/run.mjs` (uses the workspace PHP-WASM development runtime).

## Deployment and backup — separate approval required

1. Do not push these changes to the auto-deploy branch until approved and remaining production requirements are resolved.
2. Check hosting PHP extensions and configure `INSURANCE_DATA_DIR` outside public files. Apache `.htaccess` denies internal directories; equivalent deny rules are required on other servers.
3. Run CLI initialization once; create agency accounts without sample data; schedule the reminder CLI.
4. Restrict storage to the PHP service account. Serve HTTPS only. Keep credentials out of git and browser storage.
5. Take a consistent SQLite backup using SQLite's backup facility (or stop all writers, then copy the database) together with the private `documents` directory. Encrypt backups and restrict access. Back up before schema changes; `migrations` tracks installed schema version.
6. Restore into a separate private directory first; validate counts, documents and tenant boundaries, then switch configuration while writers are stopped. Never restore over other RAY business storage.

The current scheduler creates the next recurring payment event after full payment. It does **not** yet automatically materialize each missed unpaid monthly instalment; amounts must not be represented as a complete arrears statement until that extension is implemented.
