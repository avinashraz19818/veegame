# Isolated verification only

These tests never use the repository's `evenvessis/conn.php`. A copied test site under `/home/user/.cache/veegame-testsite` uses a local-only MariaDB socket and the `veegame_fixture` database. JWT helpers are copied with a fixture-only key; the provider transport is replaced only in the test copy. No runtime test bypass exists in the delivered production files.

Prerequisites: PHP CLI + mysqli/mysqlnd + cURL, MariaDB, Python 3. Apache HTTPS was used for the recorded final run; PHP's development router is also supported for initial checks.

The tests **delete/reset fixture tables**. Do not adapt them to a production connection. HTTP targets are restricted to localhost/127.0.0.1. Fixture database credentials, tokens and TLS keys are not shipped in the update ZIP.

Local sandbox sequence (paths used by the supplied scripts):

1. Initialize/start an isolated MariaDB instance with data directory `/home/user/.cache/veegame-db`, socket `/home/user/.cache/veegame-db/mysql.sock`, and `--skip-networking`.
2. Create only the fixture schema:
   `mariadb --socket=/home/user/.cache/veegame-db/mysql.sock -u root < tests/veegame/fixture.sql`
3. `python3 tests/veegame/setup_fixture.py`
4. `php /home/user/.cache/veegame-testsite/tools/veegame-migration/migrate.php --prepare`
5. In the copied test site's directory, start `PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8791 router.php` as a managed test process.
6. `python3 tests/veegame/test_migration.py`

For real Apache rewrite verification, point a separate local Apache virtual host at the copied test site; copy the repository's root and `evenvessis/.htaccess`, permit AllowOverride, load mod_rewrite, mod_setenvif, mod_autoindex and PHP, and use local HTTPS (the original backend redirects HTTP). The recorded command was:

`VEE_FIXTURE_URL=https://127.0.0.1:8793 python3 tests/veegame/test_migration.py`

The test client permits the self-signed certificate only for this localhost fixture; production provider TLS verification remains enabled.

Final recorded result: **23/23 tests PASS**, PHP 8.4.24, Apache 2.4.68, MariaDB 11.8.6. Test 21 checks all 150 WinGo selection/number combinations. Other tests include concurrent placement/settlement, financial rollback fault injection and fail-closed schema checks.

Browser smoke testing additionally rendered the existing 390px SaaS screen with an explicitly synthetic account and submitted one synthetic ₹1 wager through its actual UI. The browser fixture does not implement the entire site's promotions/home APIs; this was not a live acceptance test.
