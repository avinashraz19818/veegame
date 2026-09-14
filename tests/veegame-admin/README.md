# Admin build-1 tests

All services/data are synthetic and loopback-bound. Never load fixture.sql on a real DB.
`test-integration.py` refuses configurable production URLs: its endpoint, socket and
`veegame_admin_fixture` database are hardcoded to a disposable `.cache` fixture.

Runtime: PHP 8.4 CLI/mysqli/mbstring, MariaDB, Python requests, Playwright Chromium;
Apache/mod_php/OpenSSL for the TLS subset. No production DB credentials or APIs used.

Reproduce:
1. Initialize a MariaDB datadir at `/home/user/.cache/vee-admin-db`, start via process
   tools using `--skip-networking --socket=.../db.sock`, then import fixture.sql and the
   existing `saas_lottery/schema.sql` into `veegame_admin_fixture` only.
2. Copy `digitaladmin/` to `/home/user/.cache/vee-admin-fixture/digitaladmin/`. Create
   fixture `evenvessis/conn.php` connecting to that disposable Unix socket/database.
3. Serve fixture only (NEVER repository root/real connection) with PHP CLI at
   127.0.0.1:8786, VEEGAME_ADMIN_TEST_MODE=1, PHP_CLI_SERVER_WORKERS=4. The router must
   deny lib/internal files and permit only the five public PHP entries and CSS/JS/WOFF2.
4. Run `python tests/veegame-admin/test-integration.py`. It overwrites only the fixture
   setup verifier with a public fixture key, never the actual deployment verifier.
   Expected 79 assertions; it leaves a synthetic owner for browser/TLS checks.
5. Run `node tests/veegame-admin/test-browser.cjs` (Playwright under inspection/node_modules).
   Expected 36 responsive checks. Screenshots only contain fixture data.
6. Run a separate Apache HTTPS fixture at 127.0.0.1:8787 (HTTP8788), self-signed cert,
   AllowOverride All, opcache disabled to allow deterministic injected-DB-failure test.
   Run `python tests/veegame-admin/test-apache.py`; expected17 checks. Self-signed verify
   bypass exists in this test only, never in application code.
7. Run existing tests/veegame/test-ui2.cjs and test-ui3.cjs; PHP and JS lint.

Audit note: first TLS test iteration used a case-sensitive assertion for lowercase
`secure`; assertion fixed. The injected fixture DB failure initially hit Apache's
OPcache interval; fixture disables OPcache. Repeat-run fixture reset was added so the
profile replay test starts from its original synthetic nickname/status/token.
All final reruns pass. No test failure was disguised as production acceptance.
