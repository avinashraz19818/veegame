VEEGAME UI v3 — repeated homepage refresh-success toast fix

Requires the existing Veegame migration installation. Keep UI v2 installed for its multiplier/banner changes.

The live UI v2 files were verified as installed correctly. This was NOT diagnosed as a stale ZIP/cache issue. UI v2 silenced one homepage startup call; shared wallet actions still had success notifications that could be triggered by other refresh paths.

This patch adds homepage/login-route guards to exactly THREE refreshSuccess notifications in the active shared wallet store:
- GetARGameAndPlatWallets
- getAllwalletsBalance
- resetData

On /, /home, /home/ and /login, these success notifications are suppressed regardless of which caller requests the refresh. Balance requests/updates still run. Existing throttling, busy flags, error handling and manual success notifications outside those routes are preserved.

No other application-code bytes are changed. UI v2's five multiplier presets and removed floating banner remain untouched. No PHP, HTML, CSS, database settings, credentials, timer, bet, wallet transaction, settlement or activation implementation is included or changed.

INSTALL
Back up assets/js/index-CBcbycSk.js. Extract this ZIP with overwrite into the same Veegame document root containing index.html and evenvessis. Reload the page once afterwards so the already-running app loads the updated JavaScript. Do not reinstall the old v1/v2 ZIP over this file.

The ZIP contains only the updated JavaScript, this note, and VEEGAME-UI3-MANIFEST.json. No SQL or migration command is required.

VERIFICATION
- Live homepage route confirmed as / using publicCHome-BXq3dfgY.js.
- Live shared bundle before patch: 1,213,653 bytes, SHA256 88cb0a8ed86e3dee2d9f855b62a3c4154574171d41ccfe61f69b68f4c27e08f9.
- Exact-byte reconstruction verifies that only the three notification guards differ.
- 255 isolated cases executed the actual wallet-store methods: all three methods across eight routes, both notification flags, success/empty/error/cooldown/busy paths, plus repeated home visits. API calls, balance values, timestamps, flags and propagated errors match the original behavior.
- Previous UI v2 regression test and JavaScript syntax check also pass.
- These tests use synthetic API responses, not a real account or wager. Live v3 installation must be verified after extraction.
