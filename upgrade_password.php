<?php
// File: upgrade_password.php
// Purpose: Change password in two modes:
//   - Blank/NULL stored password  -> no current-password validation
//   - Existing password (md5 or password_hash) -> require & validate current

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Preserve debug flag from GET/POST so it survives POST+redirect
$debugParam = (isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1') ? '1' : '';

// Ensure DB handle is available
if (!isset($dbh) || !($dbh instanceof PDO)) {
    @require_once __DIR__ . '/db.php';
}

// Resolve users table name (supports 'users' or 'Users')
$usersTable = 'users';
try {
    $hasLower = (bool)@$dbh->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    $hasUpper = (bool)@$dbh->query("SHOW TABLES LIKE 'Users'")->fetchColumn();
    if ($hasLower) {
        $usersTable = 'users';
    } elseif ($hasUpper) {
        $usersTable = 'Users';
    } else {
        $usersTable = 'users';
    }
} catch (Throwable $t) {
    // best-effort fallback, we'll try to continue and surface any DB error to the user
    $usersTable = 'users';
}

// Guard: must be logged in and have a username in session
$logged_in = !empty($_SESSION['login']) && $_SESSION['login'] === '1';
$username  = $_SESSION['Username'] ?? $_SESSION['user'] ?? null;
if (!$logged_in || !$username) {
    header('Location: index.php?mode=login');
    exit;
}

// Fetch stored password for this user
$storedRaw = null;
try {
    $stmt = $dbh->prepare("SELECT `Password` FROM `{$usersTable}` WHERE `Username` = :u LIMIT 1");
    $stmt->bindParam(':u', $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $storedRaw = $row ? $row['Password'] : null; // may be NULL
} catch (Throwable $t) {
    error_log('[UPGRADE_PW] DB error: '.$t->getMessage());
    $row = null;
}

$stored      = (string)($storedRaw ?? '');
$storedTrim  = trim($stored);
$is_blank    = ($storedRaw === null || $storedTrim === '');
$is_md5      = (bool)preg_match('/^[a-f0-9]{32}$/i', $storedTrim);
$require_current = !$is_blank;

$errs = [];
$ok_message = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new1    = (string)($_POST['new_password'] ?? '');
    $new2    = (string)($_POST['repeat_password'] ?? '');

    // Validate new passwords
    if (strlen($new1) < 8) {
        $errs[] = 'New password must be at least 8 characters.';
    }
    if ($new1 !== $new2) {
        $errs[] = 'New passwords do not match.';
    }

    // Validate current password ONLY if required
    if ($require_current && empty($errs)) {
        $valid_current = false;
        if ($is_md5) {
            $valid_current = hash_equals(strtolower($storedTrim), md5($current));
        } else {
            $valid_current = password_verify($current, $storedTrim);
        }
        if (!$valid_current) {
            $errs[] = 'Current password is incorrect.';
        }
    }

    if (empty($errs)) {
        // Hash and save new password
        $new_hash = password_hash($new1, PASSWORD_DEFAULT);
        try {
            $upd = $dbh->prepare("UPDATE `{$usersTable}` SET `Password` = :p WHERE `Username` = :u LIMIT 1");
            $upd->bindValue(':p', $new_hash, PDO::PARAM_STR);
            $upd->bindValue(':u', $username, PDO::PARAM_STR);
            $upd->execute();
            $affected = $upd->rowCount();

            // Clear upgrade flag and sign the user out but keep session for feedback
            unset($_SESSION['needs_pw_upgrade']);
            // Clear login keys to force a fresh login
            unset($_SESSION['login'], $_SESSION['Username'], $_SESSION['user'], $_SESSION['Usertype'], $_SESSION['Signature']);

            // Provide a feedback message (will survive because we do not destroy session)
            $_SESSION['feedback_type'] = 'success';
            if ($affected > 0) {
                $_SESSION['feedback_message'] = 'Password updated. Please log in with your new password.';
            } else {
                // rowCount may be 0 if the value was identical (unlikely) — still treat as success
                $_SESSION['feedback_message'] = 'Password updated (no rows reported changed). Please log in with your new password.';
            }

            // Preserve debug flag on redirect if present
            $loc = 'index.php?mode=login' . ($debugParam === '1' ? '&debug=1' : '');
            header('Location: ' . $loc);
            exit;
        } catch (Throwable $t) {
            error_log('[UPGRADE_PW] Update error: '.$t->getMessage());
            // When debug requested, show DB error to user; otherwise provide generic message
            if (!empty($_REQUEST['debug']) && ini_get('display_errors')) {
                $errs[] = 'Failed to update password due to a server error: ' . $t->getMessage();
            } else {
                $errs[] = 'Failed to update password due to a server error.';
            }
        }
    }
}

// Render form (only show Current password when required)
?>
<h1>Change password</h1>

<?php if (!empty($_SESSION['needs_pw_upgrade'])): ?>
<div style="margin:10px 0;padding:10px;border:1px solid #ccc;background:#f9f9f9;border-radius:8px;">
  <strong>Password update required.</strong> Set a new password to activate your account.
</div>
<?php endif; ?>

<?php if (!empty($errs)): ?>
<div style="margin:10px 0;padding:10px;border:1px solid #c33;background:#fee;border-radius:8px;">
  <ul style="margin:0 0 0 18px;">
    <?php foreach ($errs as $e): ?>
      <li><?php echo htmlspecialchars($e); ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="post" action="index.php?mode=changePassword" autocomplete="off">
  <!-- preserve debug flag across POST -->
  <input type="hidden" name="debug" value="<?php echo htmlspecialchars($debugParam, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($require_current): ?>
    <p>
      <label>Current password<br>
        <input type="password" name="current_password" autocomplete="current-password" required>
      </label>
    </p>
  <?php endif; ?>
  <p>
    <label>New password<br>
      <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
    </label>
  </p>
  <p>
    <label>Repeat new password<br>
      <input type="password" name="repeat_password" minlength="8" autocomplete="new-password" required>
    </label>
  </p>
  <p>
    <button type="submit">Update password</button>
    <a href="index.php?mode=logout<?php echo ($debugParam === '1') ? '&debug=1' : ''; ?>" style="margin-left:12px;">Cancel</a>
  </p>
</form>

