VEEGAME UI UPDATE v2
Requires the previously installed veegame-saas-migration-v1 package.

CHANGES
1. Login/home startup still refreshes the real wallet balance, but uses the existing silent-refresh option. The unsolicited Refresh successfully toast is removed. Manual wallet refresh behavior is unchanged.
2. Removed the floating Migration preview / Betting paused / Legacy history banner. The read-only Legacy history link is now below the history section, not over the game.
3. WinGo preset buttons are exactly X1, X5, X10, X20, X100 in both the main row and the betting popup, for all four supported intervals. Coin denominations and manual quantity controls are unchanged.

IMPORTANT
This is a UI update, NOT betting activation. Existing preview/paused/active database state, authentication, provider, period, bet validation, wallet debit and settlement rules are unchanged. Removing the banner does not activate betting.

INSTALL
Back up the three files listed in VEEGAME-UI2-MANIFEST.json. Extract this ZIP with overwrite into the Veegame document root containing index.html and evenvessis. Do not upload into 13L or ShreeWin. No SQL or migration command is needed for this UI patch.

The ZIP includes only the three changed runtime files plus this note and a size/hash manifest. It does not replace index.html, stylesheets, database credentials, cron jobs or migration-state settings.

VERIFICATION
- Live homepage confirmed to load publicCHome-BXq3dfgY.js; its original bytes matched the local baseline before this edit.
- Actual wallet-store resetData method tested in isolation: silent startup updates balance without a toast; manual refresh still produces its existing toast.
- PHP/JavaScript syntax checks passed.
- Exact source comparison: bootstrap changed ONLY the betMultiples array; homepage changed ONLY the silent argument on its startup call.
- Local synthetic-browser checks passed at widths 360, 390, 430: exactly five chips in the main row and popup; no floating banner; archive link in normal document flow. No placement requests were made. Fixture does not implement all unrelated homepage APIs.
- No live account, bet, wallet mutation or database activation was used for these tests.

Live installation of this v2 patch must still be verified after the owner extracts it.
