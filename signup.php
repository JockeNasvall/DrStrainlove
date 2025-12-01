<?php
// Allowed user types for UI (kept centralized)
if (!defined('ALLOWED_USERTYPES')) { define('ALLOWED_USERTYPES', json_encode(['Guest','User','Superuser'])); }

/** 
 * File: signup.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */

//session_start();
//if (!(isset($_SESSION['login']) && $_SESSION['login'] != '')) {
	//header ("Location: login.php");
//}

//set the session variable to 1, if the user signs up. That way, they can use the site straight away
//do you want to send the user a confirmation email?
//does the user need to validate an email address, before they can use the site?
//do you want to display a message for the user that a particular username is already taken?
//test to see if the u and p are long enough
//you might also want to test if the users is already logged in. That way, they can't sign up repeatedly without closing down the browser
//other login methods - set a cookie, and read that back for every page
//collect other information: date and time of login, ip address, etc
//don't store passwords without encrypting them

$helpmessage = "See help text next to each field, describing limitations.";

// Initialize variables to prevent warnings on first load
$insert_success = false;
$errorMessage = "";
$validation = array(
    'username' => 1,
    'signature' => 1,
    'password' => 1
);
$select = array(
    'superuser' => '',
    'user' => '' // Default to empty, let POST logic handle selection
);

// Check if usertype was posted and set the select array accordingly
if(isset($_POST['usertype']) && $_POST['usertype'] == "User"){
	$select['superuser'] = "";
	$select['user'] = "selected";
} elseif(isset($_POST['usertype']) && $_POST['usertype'] == "Superuser") {
    $select['superuser'] = "selected";
	$select['user'] = "";
} else {
    // Default if nothing posted (usually 'User')
    $select['user'] = "selected";
}


// Sett validation classes
$validation_class = array("validation-fail", ""); // 0 = fail, 1 = pass (no class)

if (isset($insert_success) && !$insert_success) { // Check if form processing failed
	echo "<p class='validation-fail'>" . $errorMessage . "</p>";
?>
	
<?php
// --- USER MANAGEMENT SELECTOR ---
$umode = $_POST['umode'] ?? $_GET['umode'] ?? '';
?>
<form method="post" action="index.php?mode=manageUsers" style="margin:10px 0; padding:8px; border:1px solid #ddd; border-radius:8px; max-width:520px;">
  <label for="umode"><strong>User management:</strong></label>
  <select id="umode" name="umode" onchange="this.form.submit()" style="margin-left:8px;">
    <option value="">-- choose action --</option>
    <option value="add" <?php echo ($umode==='add')?'selected':''; ?>>Add user</option>
    <option value="edit" <?php echo ($umode==='edit')?'selected':''; ?>>Edit users</option>
  </select>
  <noscript><button type="submit">Go</button></noscript>
</form>

<div id="add_user_block" style="display: <?php echo ($umode==='add')?'block':'none'; ?>;">
<form class="signup-form" name="form1" method="POST" action="index.php?mode=manageUsers">
		<input type="hidden" name="umode" value="add">
		<p class="note" style="margin:6px 0 12px;">Password will be set by the user at first login.</p>
		<input type="hidden" name="form-type" value="new-user">
		<div class="form-group">
			<label for="username">Username:</label>
			<input class="<?php echo $validation_class[$validation['username'] ?? 1]; ?>" type="text" id="username" name="username" placeholder="Username" value="<?php echo $_POST['username'] ?? '' ?>">
			<span class="helptext">Has to be at least 5 characters long</span>
		</div>
		<div class="form-group">
			<label for="signature">Signature:</label>
			<input class="<?php echo $validation_class[$validation['signature'] ?? 1]; ?>" type="signature" id="signature" name="signature" placeholder="Signature" value="<?php echo $_POST['signature'] ?? '' ?>">
			<span class="helptext">This will be shown in strain lists, has to be between 2 and 15 characters</span>
		</div>
		<div class="form-group">
			<label for="usertype">User type:</label>
			<select id="usertype" name="usertype">
				<option value="Superuser" <?php echo $select['superuser'] ?? '' ?>>Superuser</option>
				<option value="Guest">Guest</option>
<option value="User" <?php echo $select['user'] ?? '' ?>>User</option>
			</select>
			<span class="helptext">Superusers can insert, edit, and delete strains as well as add new users</span>
		</div>
		<input type="submit" name="Submit" value="Register">
	</form>
</div>

<?php
} else { // This else covers both the initial page load AND successful insertion
	// Check if the form was successfully processed (might be set in actions.php)
	if (isset($insert_success) && $insert_success === TRUE) {
	    echo "<p><span style='font-weight: bold'>Success!</span><br>The new user was successfully added.</p>";
	    echo "<p><a href='index.php?mode=addUser'>Add another user</a></p>";
	    $helpmessage = "To log in as the new user, <a href='index.php?logout=1'>log out " . $_SESSION['user'] . "</a>.";
	} else {
		// This is the initial form display when the page loads
?>
		<form class="signup-form" name="form1" method="POST" action="index.php?mode=manageUsers">
				<input type="hidden" name="umode" value="add">
		<p class="note" style="margin:6px 0 12px;">Password will be set by the user at first login.</p>
			<input type="hidden" name="form-type" value="new-user">
			<div class="form-group">
				<label for="username">Username:</label>
				<input class="<?php echo $validation_class[$validation['username'] ?? 1]; ?>" type="text" id="username" name="username" placeholder="Username" value="<?php echo $_POST['username'] ?? '' ?>">
				<span class="helptext">Has to be at least 5 characters long</span>
			</div>
			<div class="form-group">
				<label for="signature">Signature:</label>
				<input class="<?php echo $validation_class[$validation['signature'] ?? 1]; ?>" type="signature" id="signature" name="signature" placeholder="Signature" value="<?php echo $_POST['signature'] ?? '' ?>">
				<span class="helptext">This will be shown in strain lists, has to be between 2 and 15 characters</span>
			</div>
			<div class="form-group">
				<label for="usertype">User type:</label>
				<select id="usertype" name="usertype">
					<option value="Superuser" <?php echo $select['superuser'] ?? '' ?>>Superuser</option>
					<option value="User" <?php echo $select['user'] ?? '' ?>>User</option>
				</select>
				<span class="helptext">Superusers can insert, edit, and delete strains as well as add new users</span>
			</div>
			<input type="submit" name="Submit" value="Register">
		</form>
<?php
	}
}
?>

<!-- USER MANAGEMENT: EDIT USERS -->
<div id="edit_user_block" style="display: <?php echo ($umode==='edit')?'block':'none'; ?>;">
<h2>Edit users</h2>
<?php
require_once __DIR__ . '/db.php';
function table_exists_local(PDO $dbh, string $table): bool {
    try {
        $stmt = $dbh->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $t) { return false; }
}
$usersTable = table_exists_local($dbh,'users') ? 'users' : (table_exists_local($dbh,'Users') ? 'Users' : 'users');
$users = [];
try { $stmt = $dbh->query("SELECT Username FROM `{$usersTable}` ORDER BY Username ASC"); $users = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); } catch (Throwable $t) { $users = []; }

$selected_user = $_POST['selected_user'] ?? ($_GET['selected_user'] ?? '');
$preview = $_SESSION['user_edit_preview'] ?? null;
?>
<form method="post" action="index.php?mode=addUser" style="margin-bottom:10px;">
  <input type="hidden" name="form-type" value="edit-user-load">
  <input type="hidden" name="umode" value="edit">
  <input type="hidden" name="umode" value="edit">
  <label for="selected_user">Choose user:</label>
  <select name="selected_user" id="selected_user" onchange="this.form.submit()">
    <option value="">-- select --</option>
    <?php foreach ($users as $u): ?>
      <option value="<?php echo e($u); ?>" <?php echo ($selected_user===$u)?'selected':''; ?>><?php echo e($u); ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button type="submit">Load</button></noscript>
</form>
<?php
$row = null;
if ($selected_user) {
    try { $stmt = $dbh->prepare("SELECT * FROM `{$usersTable}` WHERE Username = :u LIMIT 1"); $stmt->bindParam(':u',$selected_user); $stmt->execute(); $row=$stmt->fetch(PDO::FETCH_ASSOC); } catch (Throwable $t) { $row=null; }
}
if ($row && empty($preview)):
?>
<form method="post" action="index.php?mode=addUser" style="border:1px solid #ddd;padding:10px;border-radius:8px;max-width:520px;">
  <input type="hidden" name="form-type" value="edit-user-preview">
    <input type="hidden" name="umode" value="edit">
    <input type="hidden" name="umode" value="edit">
  <input type="hidden" name="username" value="<?php echo e($row['Username']); ?>">
  <p><label>Username (read-only)<br><input type="text" value="<?php echo e($row['Username']); ?>" readonly></label></p>
  <p><label>Signature<br><input type="text" name="signature" maxlength="15" value="<?php echo e($row['Signature'] ?? ''); ?>"></label></p>
  <p><label>Usertype<br>
    <select name="usertype">
      <?php foreach (['Guest','User','Superuser'] as $role): ?>
        <option value="<?php echo e($role); ?>" <?php echo (($row['Usertype'] ?? '')===$role)?'selected':''; ?>><?php echo e($role); ?></option>
      <?php endforeach; ?>
    </select>
  </label></p>
  <p><label><input type="checkbox" name="reset_password" value="1"> Reset password (set to blank)</label></p>
  <p>
    <button type="submit">Submit</button>
    <button type="submit" name="form-type" value="edit-user-cancel">Cancel</button>
  </p>
</form>
<?php endif; ?>
<?php if (!empty($preview)): ?>
  <div style="margin-top:10px; padding:10px; border:1px solid #ddd;border-radius:8px;max-width:620px;">
    <h3>Review and apply</h3>
    <pre style="white-space:pre-wrap;"><?php echo e($preview['sql_pretty']); ?></pre>
    <form method="post" action="index.php?mode=addUser">
      <input type="hidden" name="form-type" value="edit-user-apply">
      <input type="hidden" name="umode" value="edit">
      <input type="hidden" name="umode" value="edit">
      <button type="submit">Apply</button>
      <button type="submit" name="form-type" value="edit-user-cancel-apply">Cancel</button>
    </form>
  </div>
<?php endif; ?>
</div>
<!-- END USER MANAGEMENT: EDIT USERS -->

