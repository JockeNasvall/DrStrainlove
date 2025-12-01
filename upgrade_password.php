<?php
// File: upgrade_password.php
// Purpose: Change password in two modes:
//   - Blank/NULL stored password  -> no current-password validation
//   - Existing password (md5 or password_hash) -> require & validate current

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Ensure DB handle is available
if (!isset($dbh) || !($dbh instanceof PDO)) {
    @require_once __DIR__ . '/db.php';
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
    $stmt = $dbh->prepare("SELECT `Password` FROM `users` WHERE `Username` = :u LIMIT 1");
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
            $upd = $dbh->prepare("UPDATE `users` SET `Password` = :p WHERE `Username` = :u LIMIT 1");
            $upd->bindParam(':p', $new_hash);
            $upd->bindParam(':u', $username);
            $upd->execute();

            // Clear upgrade flag then force fresh login
            unset($_SESSION['needs_pw_upgrade']);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            header('Location: index.php?mode=login');
            exit;
        } catch (Throwable $t) {
            error_log('[UPGRADE_PW] Update error: '.$t->getMessage());
            $errs[] = 'Failed to update password due to a server error.';
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
    <a href="index.php?mode=logout" style="margin-left:12px;">Cancel</a>
  </p>
</form>

