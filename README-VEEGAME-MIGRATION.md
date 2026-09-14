# Veegame WinGo — staged SaaS migration v1

**Status: integration code tested locally; NOT deployed or activated on the live site.**

Target: `https://veergame.club9.eu.cc`
Base repository: `avinashraz19818/veegame`, `ac01cb8`
Donor: `avinashraz19818/shreewin`, `86e5048e8b07a04e5fa4e3825db77dfa917ac1bd`

The owner explicitly selected ShreeWin-backend migration while retaining Veegame's existing SaaS screen. This is NOT a promise to retain the former one-period-behind engine. New bets use the provider's real current issue, close time, published result and actual server placement timestamp.

## What was integrated

- Adapted ShreeWin's WinGo API contracts, odds/evaluation, transaction-based placement, settlement and record serialization.
- Implemented the missing `evenvessis/app_core_live_v4.php` as a **narrow, working WinGo settings/schema integration**. The donor's broad site-wide admin installer was not imported.
- Added `/api/Lottery/...` and `/WinGo/WinGo_*.json` routes with Apache rewrites. The existing cPanel handler block is retained.
- Kept the existing SaaS WinGo screen. Its API client now uses the current site origin, not stale/external `ar_api` / `ar_api_json` cache values.
- Added a small migration-status / Legacy history link, plus an authenticated read-only archive page.
- Kept native JWT validation and database session matching; additionally reject malformed tokens, explicitly expired tokens and disabled accounts.
- Native `conn.php`, `functions2.php`, `index.html`, wallet/deposit/withdrawal implementation, cron files and unrelated games are **not replaced**.

Only **WinGo 30s / 1m / 3m / 5m** is integrated. K3, 5D, TRX, MotoRace, follow strategies, VIP experience, referral commissions and deposit-turnover/reward tracking are **not migrated**. Review these exclusions before activating real-money play. The migration must not be described as a complete port of every ShreeWin feature.

## Provider and safety behavior

The donor's `draw.ar-lottery01.com` feed returned HTTP 403 during inspection. The existing Veegame SaaS client identifies `draw.ar-lottery06.com` as its draw default. Current and history feeds for all four WinGo intervals returned HTTP 200 there and were also checked with the actual PHP adapter.

- HTTPS certificate verification is enabled. Requests only go to the fixed public draw host; no user tokens or database credentials are sent there.
- No random-result fallback, manual/admin result override, fabricated follow statistics, issue renumbering, backdating or timer bypass is included.
- Provider issue numbers and start/end timestamps are retained, not rebound to a local sequence.
- A stale/unavailable/malformed current feed pauses acceptance. There is a bounded retry for a just-expired feed at a publication boundary.
- At least the final five seconds are closed to new bets, including after acquiring the wallet lock.
- Placement also refuses an issue whose result is already present in the provider's history.
- Pending bets are settled only from published, closed provider results. A transaction failure can be retried without a second debit or payout.
- Result conflicts are not automatically overwritten or paid from an alternative value.
- ShreeWin's WinGo odds and 2% payout calculation are retained. Actual frontend selection capitalization and 12-digit numeric request nonces are supported.

## Installation is preview-only by default

Back up the site's files **and database** before installing. Extract the update's paths into the Veegame document root, not into 13L or ShreeWin. The ZIP does not contain the full homepage, database connection settings, credentials, a database dump, or real account data.

If live `.htaccess` has additional rules not present in the repository, merge the marked `BEGIN VEEGAME SAAS V1` block instead of discarding those rules. cPanel/Apache must allow rewrites and authorization-header forwarding.

On the first launcher / lottery request, only isolated `veegame_saas_*` tables/settings and the archive table are created. Existing tables/rows are not backfilled or rewritten. The new game opens in **Migration preview — Betting paused** until the database cutover is explicitly completed. Merely extracting the ZIP **does not activate betting**.

Requirements: compatible PHP 8.1+ with mysqli/mysqlnd and cURL; Apache 2.4 mod_rewrite; outbound HTTPS to the provider; CREATE privileges for the new tables. Financial tables must be InnoDB with the expected unique keys. The existing wallet needs an exact DECIMAL amount and a single-column unique index on `balakedara`; incompatible existing data/schema is a blocker, not automatically converted.

## Controlled cutover — operator steps, not an automatic web installer

Use a terminal in the site's document root. `tools/veegame-migration/` is HTTP-denied, and its PHP entrypoint independently refuses non-CLI execution. Do not make an unauthenticated web activation endpoint.

1. Inspect the **read-only** preflight:

   ```sh
   php tools/veegame-migration/migrate.php --status
   ```

   No command below should be run until a real backup is available. Do not use the isolated test configuration on the live site.

2. Prepare preview schema, then freeze the legacy WinGo placement path:

   ```sh
   php tools/veegame-migration/migrate.php --prepare
   php tools/veegame-migration/migrate.php --freeze-legacy
   ```

3. Let already-started requests finish. Wait at least 30 seconds and verify none remain. Reconcile any old pending bets through the old authoritative process. Then stop the **legacy WinGo settlement crons** and put money-changing operations into maintenance. Do not stop unrelated games' crons blindly. The tool does not control cPanel cron jobs or external payment workers.

4. Archive only after the preflight has no blockers:

   ```sh
   php tools/veegame-migration/migrate.php --archive --maintenance-confirmed --legacy-cron-stopped
   ```

   The tool checks row counts, source-record hashes and wallet snapshots before/after. Changed records, pending/unresolved legacy results, incompatible wallet schema, or an unreviewed pre-existing SaaS ledger block cutover. These snapshots establish that the migration did not change balances; they are **not a full audit of historical wagering or all external payment transactions**.

5. Inspect the archive through the authenticated Legacy history page; review rewards/turnover exclusions and odds. Only then activate:

   ```sh
   php tools/veegame-migration/migrate.php --activate --maintenance-confirmed --legacy-cron-stopped --rewards-scope-reviewed
   ```

   Activation rechecks source/wallet snapshots and all four provider feeds. The old WinGo placement endpoint remains closed. Reopen approved non-legacy operations only after acceptance checks. Do not restart old WinGo settlement crons against the new engine.

6. Schedule the new CLI settlement worker, for example once per minute with the site's correct PHP executable and absolute path:

   ```sh
   php /ABSOLUTE/SITE/ROOT/tools/veegame-migration/migrate.php --settle
   ```

   API/history polling also settles published results. The CLI worker uses an advisory lock, stops on unavailable or repeated provider pages, and reports remaining pending bets. If an outage exceeds the provider's actual history retention, bets remain pending for authoritative reconciliation; no invented result closes them.

Emergency pause:

```sh
php tools/veegame-migration/migrate.php --pause
```

This rejects new SaaS bets but keeps settlement available for existing ones, and does not reopen legacy placement. There is intentionally no automatic financial rollback/replay. After any new SaaS bet, reverting files alone is not a safe database rollback.

## Historical data handling

Legacy `bajikattuttate*` records are preserved in their original tables and copied into `veegame_legacy_archive`, keyed by source table + source record ID, with an immutable raw-record hash. They are **not inserted into the new payable bet ledger**, so old stakes/payouts cannot be replayed by the new engine.

The existing legacy settlement source updates `tiarikala`; therefore that value cannot reliably be described as the original placement time. The archive explicitly calls it **Recorded time**. The source has inconsistent interval labels/timing in its old APIs, so archival records retain their source-table identity rather than guessing a new game/issue mapping.

New **My history** contains new SaaS bets. Old records are available separately via **Legacy history** after the archive step. This distinction is intentional and visible, not silent loss of old records.

## Verification performed

- PHP syntax checks for every changed/added PHP file; JavaScript syntax checks for both changed/added modules.
- **23 automated tests passed on an isolated MariaDB database under Apache HTTPS**, using the real rewrite rules and synthetic accounts/provider data. No live account was used.
- Coverage: launcher dependency/signature, JWT/session/expiry/status, SaaS contracts, preview gate, actual timestamps, concurrent duplicate debit prevention, provider outage/staleness, closing lock, premature-result rejection, rollback fault injection, once-only payout, settlement retry, cross-user history isolation, pending-legacy blockers, idempotent archival without wallet writes, changed-wallet activation blocker, legacy placement gate, valid frontend nonce format, paused settlement, all 150 WinGo number/selection combinations, nontransactional-table rejection and missing-unique-key rejection.
- Local 390px browser render of the actual SaaS screen with the synthetic signed-in account: balance, four interval tabs, countdown, results and migration notice displayed. The real frontend submitted a synthetic ₹1 bet, updated the fixture balance once and displayed the pending record. General home/promotions endpoints are not all implemented by the fixture, so this is **not** a whole-site or live acceptance test.
- Live public provider feeds were checked separately with real HTTPS requests. No live wager, wallet mutation, database migration or deployment was performed.

Before declaring the live site fixed, verify authenticated launch, all four intervals, wallet reconciliation, old/new history separation and the cutover state on the actual host. Do not claim live success from these local tests.
