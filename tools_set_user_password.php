<?php
/**
 * /var/www/strainlove/tools_set_user_password.php
 *
 * Minimal admin tool to set a user's password using password_hash().
 * - Finds the auth table automatically: `users` or `Users`.
 * - Validates and updates `Password` column to VARCHAR(255) hash.
 *
 * Note: Keep this file protected (not world-accessible) or remove after use.
 */

declare(strict_types=1);
@ini_set('display_errors','1'); @ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
if (!isset($dbh) || !($dbh instanceof PDO)) {
  http_response_code(500);
  echo "db.php must define \$dbh (PDO).";
  exit;
}
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---- helpers ---- */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function current_db(PDO $dbh): string { return (string)$dbh->query('SELECT DATABASE()')->fetchColumn(); }
function table_exists(PDO $dbh, string $table): bool {
  $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?';
  $stmt = $dbh->prepare($sql); $stmt->execute([current_db($dbh), $table]);
  return (int)$stmt->fetchColumn() > 0;
}
function users_table(PDO $dbh): ?string {
  if (table_exists($dbh,'users')) return 'users';
  if (table_exists($dbh,'Users')) return 'Users';
  return null;
}

/* ---- resolve users table ---- */
$usersTable = users_table($dbh);
if (!$usersTable) {
  http_response_code(500);
  echo "Could not find `users` or `Users` table.";
  exit;
}

/* ---- handle post ---- */
$message = null; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim((string)($_POST['username'] ?? ''));
  $pw1 = (string)($_POST['new_password'] ?? '');
  $pw2 = (string)($_POST['confirm_password'] ?? '');

  if ($username === '' || $pw1 === '' || $pw2 === '') {
    $message = "All fields are required.";
  } elseif ($pw1 !== $pw2) {
    $message = "Passwords do not match.";
  } elseif (strlen($pw1) < 8) {
    $message = "Password must be at least 8 characters.";
  } else {
    // why: ensure user exists to avoid silent no-op
    $check = $dbh->prepare("SELECT Username FROM `$usersTable` WHERE Username = :u LIMIT 1");
    $check->execute([':u' => $username]);
    $exists = $check->fetchColumn();
    if (!$exists) {
      $message = "User not found: " . h($username);
    } else {
      $hash = password_hash($pw1, PASSWORD_DEFAULT);
      $upd = $dbh->prepare("UPDATE `$usersTable` SET `Password` = :p WHERE `Username` = :u");
      $upd->execute([':p' => $hash, ':u' => $username]);
      $rowCount = $upd->rowCount();
      // why: MySQL may report 0 if value is identical, still treat as success
      $ok = true;
      $message = "Password updated for '".h($username)."'. Rows affected: ".(int)$rowCount.".";
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Set User Password (password_hash)</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;max-width:640px;margin:24px auto;padding:0 16px;line-height:1.45}
    .card{border:1px solid #ddd;border-radius:8px;padding:16px}
    label{display:block;margin:8px 0 4px}
    input[type=text],input[type=password]{width:100%;padding:8px;border:1px solid #bbb;border-radius:6px}
    button{margin-top:12px;padding:8px 12px;border:1px solid #888;border-radius:8px;background:#f7f7f7;cursor:pointer}
    button:hover{background:#eee}
    .msg{margin:12px 0;padding:10px;border-radius:6px}
    .ok{background:#eefbea;border:1px solid #8bc34a}
    .err{background:#fff4f4;border:1px solid #e57373}
    .muted{color:#666;font-size:.9em}
    code{background:#f5f5f5;padding:2px 4px;border-radius:4px}
  </style>
</head>
<body>
  <h1>Set User Password</h1>
  <p class="muted">Table: <code><?=h($usersTable)?></code>. This sets <code>Password</code> to a <code>password_hash()</code> value.</p>

  <?php if ($message): ?>
    <div class="msg <?= $ok ? 'ok' : 'err' ?>"><?= $message ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post" autocomplete="off">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" required>

      <label for="new_password">New password</label>
      <input id="new_password" name="new_password" type="password" required>

      <label for="confirm_password">Confirm new password</label>
      <input id="confirm_password" name="confirm_password" type="password" required>

      <button type="submit">Update password</button>
    </form>
  </div>

  <p class="muted">Tip: Ensure the <code>Password</code> column is <code>VARCHAR(255) NOT NULL</code> (your audit tool can enforce this).</p>
</body>
</html>

