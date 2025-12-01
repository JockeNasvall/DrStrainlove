<?php declare(strict_types=1);

/**
 * tools_db_post_import_audit_plus.php — Guest checks + Dry-run/Apply + smart summary
 * - declare(strict_types=1) first
 * - Dry-run (default) / Apply via POST apply=1 or ?apply=1
 * - Summary table (uses audit_log), with fallback that parses detailed <li> lines
 */

@session_start();
@include_once __DIR__ . '/db.php'; // expects $dbh (PDO)
if (!isset($dbh) || !($dbh instanceof PDO)) {
    echo "<h1>DB Post-Import Audit</h1>";
    echo "<div style='color:#c00'>ERROR: No PDO \$dbh available. Ensure db.php initializes \$dbh.</div>";
    exit;
}

/* ---------- Mode & state ---------- */
$APPLY_FIXES = (
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply']) && $_POST['apply'] === '1')
    || (isset($_GET['apply']) && $_GET['apply'] === '1')
);
$GLOBALS['APPLY_FIXES']   = $APPLY_FIXES;
$GLOBALS['AUDIT_SUMMARY'] = []; // rows: [category, check, status, details, ts]
$GLOBALS['AUDIT_DETAILS'] = []; // rows: "<li>..</li>"

/* ---------- Helpers ---------- */
function audit_log(string $category, string $check, string $status, string $details): void {
    $GLOBALS['AUDIT_SUMMARY'][] = [
        'category' => $category,
        'check'    => $check,
        'status'   => strtoupper($status),
        'details'  => $details,
        'ts'       => date('Y-m-d H:i:s'),
    ];
}
function add_detail(string $liHtml): void { $GLOBALS['AUDIT_DETAILS'][] = $liHtml; }

/** Execute or dry-run a SQL statement; returns affected rows (or 0 in dry-run) */
function run_sql(PDO $dbh, string $sql, string $desc, string $cat='sql'): int {
    if (!empty($GLOBALS['APPLY_FIXES'])) {
        $n = $dbh->exec($sql);
        audit_log($cat, $desc, 'FIXED', $sql);
        add_detail("<li>Fixed: ".htmlspecialchars($desc)."</li>");
        return (int)$n;
    }
    audit_log($cat, $desc, 'INFO', 'DRY-RUN: '.$sql);
    add_detail("<li>Would run: ".htmlspecialchars($sql)."</li>");
    return 0;
}

/* ---------- Header & rendering ---------- */
function render_header(bool $apply): void { ?>
    <div style="padding:10px;border:1px solid #ccc;background:#f8f8f8;margin-bottom:10px;border-radius:8px">
        <strong>DB Post-Import Audit</strong>
        — Mode:
        <?php echo $apply ? '<span style="color:#060">APPLY</span>' : '<span>DRY-RUN</span>'; ?>
        <form method="post" style="display:inline;margin-left:12px">
            <input type="hidden" name="apply" value="0">
            <button type="submit">Re-run (Dry-run)</button>
        </form>
        <form method="post" style="display:inline;margin-left:8px">
            <input type="hidden" name="apply" value="1">
            <button type="submit">Apply fixes</button>
        </form>
    </div>
<?php }

function render_summary_table(array $rows): void {
    echo "<h2>Audit Summary (All Checks)</h2>";
    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;width:100%;max-width:1200px'>";
    echo "<thead><tr style='background:#f3f3f3'>
            <th style='text-align:left'>Category</th>
            <th style='text-align:left'>Check</th>
            <th>Status</th>
            <th style='text-align:left'>Details</th>
            <th style='text-align:left'>When</th>
          </tr></thead><tbody>";
    if (!$rows) {
        echo "<tr><td colspan='5'><em>No checks recorded.</em></td></tr>";
    } else {
        foreach ($rows as $r) {
            $bg = ($r['status']==='FIXED') ? '#eefaf0'
                 : (($r['status']==='WARN' || $r['status']==='ERROR') ? '#fff4f0' : 'transparent');
            echo "<tr style='background:{$bg}'>
                    <td>".htmlspecialchars($r['category'])."</td>
                    <td>".htmlspecialchars($r['check'])."</td>
                    <td style='text-align:center'><strong>".htmlspecialchars($r['status'])."</strong></td>
                    <td>".htmlspecialchars($r['details'])."</td>
                    <td>".htmlspecialchars($r['ts'])."</td>
                  </tr>";
        }
    }
    echo "</tbody></table>";
}

/** Smart summary: use AUDIT_SUMMARY; if empty, derive it from AUDIT_DETAILS <li> lines */
function render_summary_smart(): void {
    $rows = $GLOBALS['AUDIT_SUMMARY'] ?? [];
    if (!empty($rows)) { render_summary_table($rows); return; }

    // Fallback: parse detail lines
    $fallback = [];
    foreach ($GLOBALS['AUDIT_DETAILS'] ?? [] as $liHtml) {
        $text = trim(strip_tags($liHtml));
        $status = 'INFO';
        if (preg_match('/^(OK|Fixed|WARN|ERROR)\s*:/i', $text, $m)) {
            $k = strtoupper($m[1]); $status = ($k==='FIXED') ? 'FIXED' : $k;
            $text = preg_replace('/^(OK|Fixed|WARN|ERROR)\s*:\s*/i', '', $text, 1);
        } elseif (stripos($text, 'Would run:') === 0) {
            $status = 'INFO';
        }
        $low = strtolower($text);
        $cat = 'general';
        if (str_contains($low,'user') || str_contains($low,'guest')) $cat = 'users';
        elseif (str_contains($low,'strain')) $cat = 'strains';
        elseif (str_contains($low,'engine') || str_contains($low,'myisam') || str_contains($low,'innodb')) $cat = 'engine';
        elseif (str_contains($low,'index')) $cat = 'indexes';

        $fallback[] = [
            'category' => $cat,
            'check'    => (str_contains($low,'would run') ? 'SQL (dry-run)' : 'Check'),
            'status'   => $status,
            'details'  => $text,
            'ts'       => date('Y-m-d H:i:s'),
        ];
    }
    render_summary_table($fallback);
}

function render_details(): void {
    echo "<h2>Detailed Checks</h2>";
    echo "<ul>";
    foreach ($GLOBALS['AUDIT_DETAILS'] ?? [] as $li) echo $li;
    echo "</ul>";
}

/* ---------- Checks ---------- */

/** Ensure users.Usertype ENUM contains 'Guest' and DEFAULT 'Guest' */
function ensure_usertype_allows_guest(PDO $dbh): void {
    try {
        $row = $dbh->query(
            "SELECT DATA_TYPE, COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'Usertype'"
        )->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            add_detail("<li>WARN: users.Usertype column not found.</li>");
            audit_log('users','Usertype column','WARN','Column not found');
        } else {
            $dataType   = strtolower((string)($row['DATA_TYPE'] ?? ''));
            $columnType = (string)($row['COLUMN_TYPE'] ?? '');
            if ($dataType !== 'enum') {
                add_detail("<li>OK: users.Usertype is {$dataType}; no ENUM change needed.</li>");
                audit_log('users','Usertype type','OK',"Type {$dataType}; no change");
            } elseif (stripos($columnType, "'Guest'") !== false) {
                add_detail("<li>OK: users.Usertype ENUM already includes 'Guest'.</li>");
                audit_log('users','Usertype ENUM','OK',"Already includes 'Guest'");
            } elseif (preg_match("/^enum\\((.+)\\)$/i", $columnType, $m)) {
                $vals  = $m[1];
                $alter = "ALTER TABLE `users` MODIFY `Usertype` ENUM(".$vals.",'Guest') NOT NULL DEFAULT 'Guest'";
                run_sql($dbh, $alter, "Add 'Guest' to users.Usertype ENUM and set DEFAULT 'Guest'", 'users');
            } else {
                add_detail("<li>WARN: Could not parse ENUM definition for users.Usertype.</li>");
                audit_log('users','Usertype ENUM','WARN',"Could not parse ENUM definition");
            }
        }
    } catch (Throwable $t) {
        add_detail("<li>ERROR: Usertype ENUM check failed: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('users','Usertype ENUM','ERROR','Exception during check');
    }
}

/** Ensure Username='Guest' has Usertype='Guest' (if the row exists) */
function ensure_guest_account_usertype(PDO $dbh): void {
    try {
        $exists = $dbh->prepare("SELECT COUNT(*) FROM `users` WHERE `Username`='Guest'");
        $exists->execute();
        $count = (int)$exists->fetchColumn();
        if ($count === 0) {
            add_detail("<li>OK: Username='Guest' not present (no change needed).</li>");
            audit_log('users','Guest account presence','OK','No Guest user row');
            return;
        }
        $sql = "UPDATE `users`
                SET `Usertype`='Guest'
                WHERE `Username`='Guest' AND (`Usertype` IS NULL OR `Usertype` <> 'Guest')";
        $n = run_sql($dbh, $sql, "Set Username='Guest' to Usertype='Guest'", 'users');
        if (!empty($GLOBALS['APPLY_FIXES']) && $n === 0) {
            add_detail("<li>OK: Username='Guest' already Usertype='Guest'.</li>");
            audit_log('users','Guest account role','OK','Already correct');
        }
    } catch (Throwable $t) {
        add_detail("<li>ERROR: setting Guest account usertype: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('users','Guest account role','ERROR','Exception during update');
    }
}

/** Enforce blank-password policy: NULL -> '' and NOT NULL DEFAULT '' */
function enforce_password_blank_policy(PDO $dbh): void {
    try {
        $n = run_sql($dbh, "UPDATE `users` SET `Password`='' WHERE `Password` IS NULL", "Normalize NULL passwords to ''", 'users');
        if (!empty($GLOBALS['APPLY_FIXES']) && $n > 0) {
            add_detail("<li>Fixed: converted {$n} NULL password(s) to empty string ''.</li>");
            audit_log('users','Password blanks','FIXED',"Converted {$n} to ''");
        }
        $meta = $dbh->query(
            "SELECT IS_NULLABLE, COLUMN_DEFAULT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME='users' AND COLUMN_NAME='Password'"
        )->fetch(PDO::FETCH_ASSOC);
        $needsAlter = !$meta || $meta['IS_NULLABLE'] !== 'NO' || $meta['COLUMN_DEFAULT'] != '';
        if ($needsAlter) {
            run_sql(
                $dbh,
                "ALTER TABLE `users` MODIFY `Password` varchar(255) NOT NULL DEFAULT ''",
                "Ensure users.Password NOT NULL DEFAULT ''",
                'users'
            );
        } else {
            add_detail("<li>OK: users.Password is NOT NULL DEFAULT ''.</li>");
            audit_log('users','Password column','OK',"Already NOT NULL DEFAULT ''");
        }
    } catch (Throwable $t) {
        add_detail("<li>ERROR: enforcing password blank policy: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('users','Password policy','ERROR','Exception during enforcement');
    }
}

/**
 * Repair zero-dates in strains.Created.
 * - Relax sql_mode (drop NO_ZERO_DATE/NO_ZERO_IN_DATE) and set UTC BEFORE any comparison/update.
 * - Use UPDATE IGNORE for extra safety under edge modes.
 * - Always restore session settings in finally.
 */
function repair_zero_dates_in_strains_created(PDO $dbh, string $replacement = '1970-01-01 00:00:01'): void {
    $oldMode = null;
    $oldTz   = null;

    try {
        if (empty($GLOBALS['APPLY_FIXES'])) {
            // Dry-run: just show what we'd do
            add_detail("<li>Would set session time_zone='+00:00' and relax sql_mode (remove NO_ZERO_DATE/NO_ZERO_IN_DATE), then UPDATE rows to {$replacement}.</li>");
            audit_log('strains','Zero-date Created','INFO',"DRY-RUN: normalize zero-dates to {$replacement} under UTC with relaxed sql_mode");
            return;
        }

        // Snapshot current settings
        $oldMode = (string)$dbh->query("SELECT @@SESSION.sql_mode")->fetchColumn();
        $oldTz   = (string)$dbh->query("SELECT @@SESSION.time_zone")->fetchColumn();

        // Build relaxed mode (drop NO_ZERO_DATE / NO_ZERO_IN_DATE)
        $relaxed = $oldMode;
        foreach (['NO_ZERO_IN_DATE','NO_ZERO_DATE'] as $flag) {
            $relaxed = preg_replace('/(^|,)\s*'.preg_quote($flag,'/').'\s*(?=,|$)/', '', $relaxed ?? '');
        }
        $relaxed = trim(preg_replace('/\s*,\s*/', ',', (string)$relaxed), ',');

        // Apply relaxed mode + UTC BEFORE any comparisons on the TIMESTAMP literal
        $dbh->exec("SET SESSION sql_mode = " . $dbh->quote($relaxed));
        $dbh->exec("SET time_zone = '+00:00'");

        // Count zero-date rows under relaxed settings
        $stmt = $dbh->query("SELECT COUNT(*) FROM `strains` WHERE `Created` = '0000-00-00 00:00:00'");
        $cnt  = (int)($stmt ? $stmt->fetchColumn() : 0);
        if ($cnt <= 0) {
            add_detail("<li>OK: no zero-date rows in strains.Created.</li>");
            audit_log('strains','Zero-date Created','OK','None found');
            return;
        }

        // Update under relaxed settings; IGNORE protects against residual mode issues
        $u = $dbh->prepare("UPDATE IGNORE `strains` SET `Created` = :replacement WHERE `Created` = '0000-00-00 00:00:00'");
        $u->bindValue(':replacement', $replacement, PDO::PARAM_STR);
        $u->execute();
        $n = $u->rowCount();

        add_detail("<li>Fixed: updated {$n} zero-date Created value(s) to {$replacement} (UTC).</li>");
        audit_log('strains','Zero-date Created','FIXED',"Updated {$n} row(s) to {$replacement} under UTC");
    } catch (Throwable $t) {
        add_detail("<li>ERROR: repairing zero-date Created: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('strains','Zero-date Created','ERROR',$t->getMessage());
    } finally {
        // Always restore session settings
        try {
            if ($oldTz !== null)   { $dbh->exec("SET SESSION time_zone = " . $dbh->quote($oldTz)); }
            if ($oldMode !== null) { $dbh->exec("SET SESSION sql_mode = " . $dbh->quote($oldMode)); }
        } catch (Throwable $restoreErr) {
            add_detail("<li>WARN: failed to restore session settings: ".htmlspecialchars($restoreErr->getMessage())."</li>");
            audit_log('engine','Restore session','WARN',$restoreErr->getMessage());
        }
    }
}

/**
 * Convert users/strains to InnoDB if currently MyISAM (dry-run/apply aware).
 */
function convert_myisam_to_innodb(PDO $dbh): void {
    try {
        $q = $dbh->query(
            "SELECT TABLE_NAME, ENGINE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('users','strains')"
        );
        $todo = [];
        foreach ($q ?: [] as $r) {
            $tbl = (string)$r['TABLE_NAME'];
            $eng = strtoupper((string)$r['ENGINE']);
            if ($eng === 'MYISAM') $todo[] = $tbl;
        }

        if (!$todo) {
            add_detail("<li>OK: users/strains already InnoDB (or not MyISAM).</li>");
            audit_log('engine','Convert to InnoDB','OK','Already not MyISAM');
            return;
        }

        foreach ($todo as $tbl) {
            $sql = "ALTER TABLE `{$tbl}` ENGINE=InnoDB";
            $desc = "Convert {$tbl} to InnoDB";
            run_sql($dbh, $sql, $desc, 'engine');
        }
    } catch (Throwable $t) {
        add_detail("<li>ERROR: converting to InnoDB: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('engine','Convert to InnoDB','ERROR',$t->getMessage());
    }
}
/** Add helpful indexes on strains */
function ensure_strains_indexes(PDO $dbh): void {
    try {
        $idx   = $dbh->query("SHOW INDEX FROM `strains`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $names = array_map(static fn($r) => $r['Key_name'], $idx);
        $added = 0;
        if (!in_array('idx_strains_recipient', $names, true)) {
            run_sql($dbh, "ALTER TABLE `strains` ADD INDEX idx_strains_recipient (Recipient)", "Add index idx_strains_recipient", 'strains');
            $added++;
        }
        if (!in_array('idx_strains_donor', $names, true)) {
            run_sql($dbh, "ALTER TABLE `strains` ADD INDEX idx_strains_donor (Donor)", "Add index idx_strains_donor", 'strains');
            $added++;
        }
        if (empty($GLOBALS['APPLY_FIXES']) && $added === 0) {
            add_detail("<li>OK: strains indexes present.</li>");
            audit_log('strains','Strains indexes','OK','Indexes present');
        }
    } catch (Throwable $t) {
        add_detail("<li>ERROR: ensuring strains indexes: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('strains','Strains indexes','ERROR','Exception during index check');
    }
}

/** Informational: warn if using MyISAM */
function warn_if_myisam(PDO $dbh): void {
    try {
        $rs = $dbh->query(
            "SELECT TABLE_NAME, ENGINE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('users','strains')"
        );
        $warn = [];
        foreach ($rs ?: [] as $r) {
            if (strtoupper((string)$r['ENGINE']) === 'MYISAM') { $warn[] = $r['TABLE_NAME']; }
        }
        if ($warn) {
            add_detail("<li>WARN: MyISAM tables (".htmlspecialchars(implode(', ', $warn)).") — no foreign keys/transactions.</li>");
            audit_log('engine','Storage engines','WARN','MyISAM: '.implode(', ', $warn));
        } else {
            add_detail("<li>OK: non-MyISAM engines for users/strains.</li>");
            audit_log('engine','Storage engines','OK','Non-MyISAM');
        }
    } catch (Throwable $t) {
        add_detail("<li>ERROR: engine check failed: ".htmlspecialchars($t->getMessage())."</li>");
        audit_log('engine','Storage engines','ERROR','Exception during engine check');
    }
}

/* ---------- Run & Render ---------- */
render_header($APPLY_FIXES);

/* Guest checks first */
ensure_usertype_allows_guest($dbh);
ensure_guest_account_usertype($dbh);

/* Normalize data that can break ALTER TABLE */
enforce_password_blank_policy($dbh);
repair_zero_dates_in_strains_created($dbh, '1970-01-01 00:00:01');
convert_myisam_to_innodb($dbh);            // << add this
/* Structural/index checks */
ensure_strains_indexes($dbh);
warn_if_myisam($dbh);

/* Output */
render_summary_smart();
render_details();

