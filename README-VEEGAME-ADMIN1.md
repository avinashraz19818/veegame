# Veegame admin integration — build 1

**Status: tested integration foundation, NOT full ShreeWin operational parity.**
Full admin/payment/WinGo integration remains the requested end state. This build does
not claim that live betting, deposits, payouts or all donor modules are enabled.

## Included and functional
- ShreeWin `digitaladmin` core/theme/icon assets, pinned to commit
  `86e5048e8b07a04e5fa4e3825db77dfa917ac1bd`; its login styling adapted for Veegame.
- Rewritten admin authentication with a separate, secure session, CSRF protection,
  password hashing, rate limiting, inactivity/absolute expiry and password change.
- One-time key-protected owner setup; **no default admin password** and no donor login.
- Dashboard reading existing database record counts (not invented statistics).
- User search, pagination, profile viewing, nickname/status changes with owner password
  confirmation, compare-and-set protection and audit intent/receipt. Status changes
  clear the existing user account token; they do not cancel bets or touch money.
- Wallet/deposit/withdrawal record views preserving raw amounts. No rounding or zeroing.
- Existing new-engine WinGo bet and wallet-ledger views. No relabelled legacy bets.
- Live schema compatibility view and authenticated structure-only report download.
- Admin audit history. Customer passwords and session tokens are never selected for UI.

## Explicitly NOT enabled in this build
- Wallet credits/debits/reset, deposit approval/rejection/bonuses, withdrawal refunds,
  manual paid-status changes or automatic payouts; gateway credentials not copied.
- Frontend site settings/banner/promotion/agent/bonus/VIP management. Several Veegame
  endpoints contain static values and cannot be made functional by copying donor forms.
- New WinGo activation, result/period changes, cron installation, migrations or wallet
  schema conversion. The existing migration CLI remains CLI-only and unchanged.
- Outcome manipulation, known-result wagers, result overrides and backdated timestamps.
- Importing donor users, passwords, payment keys, financial history or wallet-reset tools.

The live read-only preflight previously reported wallet MyISAM, `motta` varchar(500),
no unique single-column user index, 958 wallet rows and two balance strings needing
review. No financial cutover is safe merely because an admin interface renders.

## Install (manual ZIP workflow)
1. Keep a verified site/database backup. Extract `veegame-admin-v1.zip` into the same
   Veegame document root used for earlier ZIPs. The only runtime directory added is
   `digitaladmin/`. Existing frontend, wallet APIs, WinGo and database connection are
   NOT replaced. Do not extract into a second nested `veegame/` folder.
2. Open `https://veergame.club9.eu.cc/digitaladmin/setup.php` using HTTPS.
3. Obtain your **private one-time setup key** from the separately supplied
   `veegame-admin-setup-key.txt`. NEVER upload that text file to the web root, share it
   publicly or include it in a public ZIP. Only its SHA-256 verifier is in the package.
4. Enter the key, choose a 3–64 character username and a unique 12–72 byte password.
   Setup creates only these three new InnoDB tables:
   `veegame_admin_accounts`, `veegame_admin_audit`, `veegame_admin_rate`.
   It never changes existing user/wallet/payment/game tables or settings.
5. Sign in at `/digitaladmin/login.php`. Existing accounts prevent setup from creating
   another owner or replacing credentials, even with the old setup key.
6. For remaining full integration, open **Integration checks → Download structure
   report**, confirm your admin password, and share that report. It contains table,
   column and index metadata only, not data rows, defaults, passwords or secrets.
   No phpMyAdmin SQL copy-pasting is needed for this report.

Do not send your chosen password in chat. Keep it in your password manager. This build
has no public password-reset bypass. An authenticated password change signs out all
sessions. If setup is interrupted after table creation but before owner creation,
retry with the same private setup key; existing tables are never truncated.

## Hosting requirements / failure behavior
- PHP 8.1+ recommended; mysqli and mbstring. `mysqli::get_result`/mysqlnd is not required.
- Uses the existing `evenvessis/conn.php` and its already-installed database config.
  No database username/password is included in this patch.
- HTTPS, sessions and permission to create the three separate admin tables.
- Apache/LiteSpeed with `.htaccess` honored. Private helpers additionally have a PHP
  entry guard. Nginx requires equivalent deny rules for `digitaladmin/lib/`, backup
  files and all PHP entries except index/login/setup/logout/action.
- No external browser CDN/fonts/scripts. Modern browsers use the bundled WOFF2 font.
- Missing optional native tables render an explicit unavailable module, not synthetic
  data and not automatic donor schema creation. Actual live compatibility is still
  subject to the first login/report; no production DB was connected during development.
- User table mutations can be nontransactional on legacy MyISAM: an audit intent is
  persisted before the compare-and-set update, followed by an applied/conflict receipt.
  An intent without a receipt must be reconciled; it does not prove no change occurred.
- Financial cutover still needs the two raw balance values reviewed, verified backup,
  maintenance/legacy-cron handling, schema migration, reward/payout reconciliation and
  controlled end-to-end tests. Structure export alone does not authorize or perform it.

## Verification
Synthetic isolated tests (not live acceptance):
- 79 HTTP/database assertions including concurrent single-owner setup, authentication,
  CSRF, role restrictions, user editing/replay, raw balance preservation, schema export,
  missing-table behavior, password/session revocation and login rate limiting.
- 36 responsive page checks at 360/390/430/1440 widths, no document overflow, no JS
  errors, no external browser requests; mobile navigation open/Escape verified.
- 17 real Apache/TLS checks: Secure/HttpOnly/SameSite cookies, HTTPS enforcement,
  `.htaccess` denials, login, assets and redacted DB failures.
- Existing UI2 and UI3 regression suites: six presets and 255 wallet-toast cases pass.
- Fixture financial rows unchanged through the entire admin suite.
- Existing Veegame runtime files unchanged byte-for-byte. Prior release ZIPs untouched.

No production admin, financial action, wager, backup, deployment or cron was executed
while preparing this build. After upload, public file verification and authenticated
owner acceptance still remain.

## Rollback
Remove only the newly added `digitaladmin/` directory to disable this admin interface.
Keep the three admin tables for audit/recovery; do not drop them casually. Removing
files does not undo any profile edit explicitly made by an owner. Existing WinGo/client
files were not changed and need no rollback for this build.
