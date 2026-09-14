<?php
/**
 * Veergame Admin - ONE-TIME full enablement setup.
 *
 * Usage (browser):
 *   https://YOUR-SITE/admin_full_enable.php?key=VEER-SETUP-2026          (report mode)
 *   https://YOUR-SITE/admin_full_enable.php?key=VEER-SETUP-2026&copy=1   (also copy missing tables)
 *   Add &donor=club532583_shreewin if the donor database has a different name.
 *
 * What it does:
 *   1. Enables ALL menu permissions for every admin row in nirvahaka_shonu.
 *   2. Detects tables that exist in the donor (shreewin) database but are
 *      missing from this (veergame) database.
 *   3. With &copy=1 : creates the missing tables (structure + data) from the
 *      donor DB and replaces donor-domain strings inside the copied rows.
 *      Existing tables are NEVER touched or overwritten.
 *
 * IMPORTANT: DELETE THIS FILE after running it.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$key = 'VEER-SETUP-2026';
if (($_GET['key'] ?? '') !== $key) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('404 Not Found');
}

$mode   = (int)($_GET['copy'] ?? 0) === 1 ? 'copy' : 'report';
$rows   = array();
$fail   = array();

function out(string $line): void {
    echo htmlspecialchars($line, ENT_QUOTES) . "\n";
}

header('Content-Type: text/plain; charset=utf-8');
out('==========================================================');
out(' VEEGAME ADMIN - FULL ENABLEMENT SETUP (' . strtoupper($mode) . ' mode)');
out(' Date: ' . date('Y-m-d H:i:s'));
out('==========================================================');
out('');

/* ---------- DB connection (site's own connection) ---------- */
$siteRoot = '';
$candidates = array(
    __DIR__ . '/serive/samparka.php',          // file placed at site root
    dirname(__DIR__) . '/serive/samparka.php', // file placed in a subfolder
);
foreach ($candidates as $cand) {
    if (is_file($cand)) { $siteRoot = $cand; break; }
}
if ($siteRoot === '') {
    out('FATAL: serive/samparka.php not found next to this file. Make sure this file is in the site root (the same folder as index.html and serive/).');
    exit;
}
out('Using DB connection file: ' . $siteRoot);
ob_start();
try {
    require $siteRoot;
} finally {
    ob_end_clean();
}
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    out('FATAL: could not connect to the site database via serive/samparka.php');
    exit;
}
$conn->set_charset('utf8mb4');

$currentDb = $conn->query('SELECT DATABASE() AS db') ? $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] : '';
out('Current database : ' . $currentDb);

/* ---------- derive donor db name ---------- */
$donorDb = (string)($_GET['donor'] ?? '');
if ($donorDb === '') {
    $parts = explode('_', $currentDb);
    $last  = end($parts);
    $donorDb = $last === 'veergame' ? implode('_', array_slice($parts, 0, -1)) . '_shreewin' : 'club532583_shreewin';
}
out('Donor database   : ' . $donorDb);
out('');

/* ---------- DB credentials (read from the connection file itself) ---------- */
$dbUser = ''; $dbPass = '';
$rawConn = (string)@file_get_contents($siteRoot);
if (preg_match("/mysqli_connect\s*\(\s*['\"]?localhost['\"]?\s*,\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]*)['\"]/", $rawConn, $mCred)) {
    $dbUser = $mCred[1]; $dbPass = $mCred[2];
}
out('DB user          : ' . ($dbUser !== '' ? $dbUser : '(could not detect from connection file)'));
out('');

/* =========================================================
 * PHASE 1 - enable all admin permissions
 * ========================================================= */
out('--- PHASE 1: ADMIN PERMISSIONS -------------------------');
$permissionKeys = ['dashboard','manageteam','games','wingomanager','k3manager','5dmanager','setting','finance','admins','manageusers','manage_game','manage_agent','assign_bonus','support','users_pan','other'];

$desc = $conn->query('DESCRIBE nirvahaka_shonu');
$realCols = array();
if ($desc) {
    while ($r = $desc->fetch_assoc()) { $realCols[] = $r['Field']; }
}
if (!$realCols) {
    out('ERROR: table nirvahaka_shonu not found in ' . $currentDb . ' - create/restore it first.');
} else {
    // First make sure ALL permission columns exist. The menu code defaults
    // any missing column to 0 (hidden), so columns that do not exist must
    // be ADDED or those menu sections can never appear.
    $missingCols = array_values(array_diff($permissionKeys, $realCols));
    if ($missingCols) {
        out('Adding ' . count($missingCols) . ' missing permission column(s): ' . implode(', ', $missingCols));
        foreach ($missingCols as $mc) {
            $mc2 = preg_replace('/[^a-z0-9_]/i', '', $mc);
            $okAlt = $conn->query("ALTER TABLE nirvahaka_shonu ADD COLUMN `" . $mc2 . "` TINYINT(1) NOT NULL DEFAULT 1");
            out($okAlt ? '   OK added `' . $mc2 . '`' : '   ALTER failed for ' . $mc2 . ': ' . $conn->error);
        }
        $desc = $conn->query('DESCRIBE nirvahaka_shonu');
        $realCols = array();
        if ($desc) { while ($r = $desc->fetch_assoc()) { $realCols[] = $r['Field']; } }
    }
    $permCols = array_values(array_intersect($permissionKeys, $realCols));
    out('Permission columns now present in nirvahaka_shonu: ' . count($permCols) . ' / ' . count($permissionKeys));
    $adminRows = $conn->query('SELECT unohs, nirvahaka_hesaru FROM nirvahaka_shonu');
    $n = 0;
    if ($permCols) {
        $set = implode(', ', array_map(function ($c) { return '`' . $c . '`=1'; }, $permCols));
        $upd = $conn->query("UPDATE nirvahaka_shonu SET $set");
        if ($upd) {
            out('OK: all permission columns set to 1 for ' . $conn->affected_rows . ' admin row(s).');
        } else {
            out('ERROR updating permissions: ' . $conn->error);
        }
    }
    if ($adminRows) {
        out('Admin accounts:');
        while ($r = $adminRows->fetch_assoc()) {
            out('   unohs=' . $r['unohs'] . '  username=' . $r['nirvahaka_hesaru']);
            $n++;
        }
        if (!$n) out('   (no admin rows found - add one first)');
    }
}
out('');

/* =========================================================
 * PHASE 1b - guaranteed critical tables (safe IF NOT EXISTS)
 * These tables are hard-referenced by admin pages and break the
 * page (mid-render PHP fatal -> DataTables "Incorrect column
 * count") when absent. Only created if missing; never modified.
 * ========================================================= */
out('--- PHASE 1b: CRITICAL TABLES GUARANTEE ---------------');
$guaranteed = array(
    'demo' => "CREATE TABLE IF NOT EXISTS demo (
        balakedara BIGINT UNSIGNED NOT NULL,
        motta VARCHAR(50) NOT NULL DEFAULT '',
        dinankavannuracisi DATETIME NULL DEFAULT NULL,
        shonu VARCHAR(10) NOT NULL DEFAULT '',
        sthiti TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (balakedara)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
foreach ($guaranteed as $tname => $tsql) {
    $exists = $conn->query("SHOW TABLES LIKE '" . preg_replace('/[^a-z0-9_]/i', '', $tname) . "'");
    if ($exists && $exists->num_rows > 0) {
        out('`' . $tname . '` already exists - left untouched.');
    } elseif ($conn->query($tsql)) {
        out('`' . $tname . '` created (structure only, no data).');
    } else {
        out('FAILED to create `' . $tname . '`: ' . $conn->error);
    }
}
out('');

/* =========================================================
 * PHASE 2 - missing tables vs donor database
 * ========================================================= */
out('--- PHASE 2: MISSING TABLES CHECK ----------------------');
$donorOk = false;
try {
    $t = @new mysqli($conn->host, $dbUser, $dbPass, $donorDb);
    if ($t->connect_errno) {
        throw new RuntimeException($t->connect_error);
    }
    $donorOk = true;
    $t->close();
} catch (Throwable $e) {
    $donorErr = $e->getMessage();
}

if (!$donorOk) {
    out('Donor DB not reachable with the site DB user (' . $donorErr . ')');
    out('');
    out('To let this script see/copy the donor tables, do ONE of these:');
    out('  A) cPanel -> MySQL Databases -> "Add User To Database":');
    out('       database = ' . $donorDb);
    out('       user     = ' . $dbUser);
    out('       click ALL PRIVILEGES.');
    out('     Then re-run this URL with &copy=1');
    out('  B) Or in phpMyAdmin: open ' . $donorDb . ' -> export the missing tables,');
    out('     then import them into ' . $currentDb . '.');
    out('');
} else {
    $stmt = $conn->prepare("SELECT s.TABLE_NAME AS t FROM information_schema.TABLES s WHERE s.TABLE_SCHEMA=? AND s.TABLE_NAME NOT IN (SELECT v.TABLE_NAME FROM information_schema.TABLES v WHERE v.TABLE_SCHEMA=?) ORDER BY s.TABLE_NAME");
    $stmt->bind_param('ss', $donorDb, $currentDb);
    $stmt->execute();
    $missing = array();
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $missing[] = $r['t']; }
    } else {
        // hosts without mysqlnd: manual fetch
        $stmt->store_result();
        $colRef = null;
        $stmt->bind_result($colRef);
        while ($stmt->fetch()) { $missing[] = $colRef; }
    }
    $stmt->close();

    if (!$missing) {
        out('OK: no missing tables - ' . $currentDb . ' already has everything the donor has.');
    } else {
        out(count($missing) . ' table(s) in donor but missing here:');
        foreach ($missing as $m) out('   - ' . $m);
        out('');
        if ($mode === 'copy') {
            out('--- PHASE 3: COPYING MISSING TABLES ----------');
            $donor = new mysqli($conn->host, $dbUser, $dbPass, $donorDb);
            $copied = 0; $errors = 0;
            foreach ($missing as $m) {
                $mt = preg_replace('/[^a-z0-9_]/i', '', $m);
                $q1 = "CREATE TABLE `{$currentDb}`.`{$mt}` LIKE `{$donorDb}`.`{$mt}`";
                if (!$conn->query($q1)) {
                    out('CREATE failed for ' . $m . ' : ' . $conn->error);
                    $errors++;
                    continue;
                }
                $q2 = "INSERT INTO `{$currentDb}`.`{$mt}` SELECT * FROM `{$donorDb}`.`{$mt}`";
                if ($conn->query($q2)) {
                    $cnt = $conn->affected_rows;
                    out('copied ' . $mt . ' (' . $cnt . ' rows)');
                    $copied++;
                } else {
                    out('DATA copy failed for ' . $m . ' : ' . $conn->error);
                    $errors++;
                }
                // replace donor domain strings inside text columns of the fresh table
                $d = $conn->query("DESCRIBE `{$currentDb}`.`{$mt}`");
                if ($d) {
                    while ($r = $d->fetch_assoc()) {
                        if (preg_match('/^varchar|^text|^mediumtext|^longtext/i', $r['Type'])) {
                            $c = $r['Field'];
                            $conn->query("UPDATE `{$currentDb}`.`{$mt}` SET `{$c}`=REPLACE(`{$c}`,'shreewin.club9.eu.cc','veergame.club9.eu.cc') WHERE `{$c}` LIKE '%shreewin%'");
                        }
                    }
                }
            }
            $donor->close();
            out('');
            out("Copy summary: {$copied} copied, {$errors} with errors.");
            out('');
        } else {
            out('Re-run with  &copy=1  appended to the URL to copy them now.');
        }
    }
}

out('');
out('==========================================================');
out(' DONE. Next steps:');
out('  1) Logout of the admin panel and login again.');
out('  2) The full menu (Team Dashboards, Manage Users,');
out('     Website Settings, Game Settings, Tanover, Agent');
out('     Settings, Support, ...) will now appear.');
out('  3) DELETE admin_full_enable.php from the server now.');
out('==========================================================');
