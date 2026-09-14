VEEGAME UI v4 — add X50

Apply after the existing migration/UI updates.

The multiplier presets are now, in order:
X1, X5, X10, X20, X50, X100

This applies to both the main WinGo row and betting popup, on all four supported intervals. Only the betMultiples presentation array in saas_lottery/bootstrap_live_v4.php changed. Manual quantity controls, coin amounts, validation, wallet, period, settlement, activation state and the v3 homepage-toast fix are unchanged.

INSTALL
Back up saas_lottery/bootstrap_live_v4.php. Extract this ZIP with overwrite into the same Veegame root containing index.html and evenvessis. Reopen WinGo so it fetches the updated preset list. No SQL or migration command is required.

TESTS
PHP syntax passed. All four game-info responses return the exact six-value list; both frontend multiplier rows use that API list. Exact-byte comparison confirms no other bootstrap change. The existing 255-case wallet-toast regression suite also passes.

The live v3 shared JavaScript was verified as deployed before this v4 patch was built. Live v4 installation remains pending until the owner extracts this ZIP.
