<?php declare(strict_types=1);
/** 
 * File: index.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */

### Set the default timezone / Jocke
date_default_timezone_set('Europe/Berlin');



if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
@require_once __DIR__ . '/db.php';
// ===== DEBUG: request probe (add &debug=1 to the URL) =====
$__DEBUG = isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1';
if ($__DEBUG) {
    echo '<div style="margin:12px 0;padding:10px;border:1px dashed #999;border-radius:8px;background:#f9f9f9">';
    echo '<strong>DEBUG — request</strong><pre style="white-space:pre-wrap">';
    echo "URI: ", htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''), "\n\n";
    echo "\$_GET:\n", htmlspecialchars(print_r($_GET, true));
    echo "\n\$_POST:\n", htmlspecialchars(print_r($_POST, true));
    echo "</pre></div>";
}

// ===== DEBUG: request probe (add &debug=1 to the URL) =====
$__DEBUG = isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1';
if ($__DEBUG) {
    echo '<div style="margin:12px 0;padding:10px;border:1px dashed #999;border-radius:8px;background:#f9f9f9">';
    echo '<strong>DEBUG — request</strong><pre style="white-space:pre-wrap">';
    echo "URI: ", htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''), "\n\n";
    echo "\$_GET:\n", htmlspecialchars(print_r($_GET, true));
    echo "\n\$_POST:\n", htmlspecialchars(print_r($_POST, true));
    echo "</pre></div>";
}

// === EARLY LOGIN HANDLER (no layout changes) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['Username']) || isset($_POST['username']))) {
    $username = trim($_POST['Username'] ?? $_POST['username']);
    $password = (string)($_POST['Password'] ?? ($_POST['password'] ?? ''));

    try {
        $stmt = $dbh->prepare("SELECT * FROM `users` WHERE `Username` = :u LIMIT 1");
        $stmt->bindParam(':u', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        error_log("[LOGIN] DB error: ".$t->getMessage());
        $user = false;
    }

    if (!$user) {
        $_SESSION['feedback_type'] = 'error';
        $_SESSION['feedback_message'] = 'Login failed.';
        header('Location: index.php?mode=login');
        exit;
    }

    $storedRaw  = array_key_exists('Password', $user) ? $user['Password'] : null; // see NULL too
    $stored     = (string)($storedRaw ?? '');
    $storedTrim = trim($stored);

    // Blank stored password: auto-login + force upgrade (only changePassword allowed)
    if ($storedRaw === null || $storedTrim === '') {
        $_SESSION['login']     = '1';
        $_SESSION['Username']  = $user['Username'] ?? '';
        $_SESSION['user']      = $user['Username'] ?? '';
        $_SESSION['Usertype']  = $user['Usertype'] ?? '';
        if (isset($user['Signature'])) { $_SESSION['Signature'] = $user['Signature']; }
        $_SESSION['needs_pw_upgrade'] = 1;
        session_regenerate_id(true);
        header('Location: index.php?mode=changePassword');
        exit;
    }

    // Verify MD5 or password_hash
    $ok = false;
    if (preg_match('/^[a-f0-9]{32}$/i', $storedTrim)) {
        $ok = hash_equals(strtolower($storedTrim), md5($password));
    } else {
        $ok = password_verify($password, $storedTrim);
    }

    if ($ok) {
        $_SESSION['login']     = '1';
        $_SESSION['Username']  = $user['Username'] ?? '';
        $_SESSION['user']      = $user['Username'] ?? '';
        $_SESSION['Usertype']  = $user['Usertype'] ?? '';
        if (isset($user['Signature'])) { $_SESSION['Signature'] = $user['Signature']; }
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
    }

    $_SESSION['feedback_type'] = 'error';
    $_SESSION['feedback_message'] = 'Login failed.';
    header('Location: index.php?mode=login');
    exit;
}
// === /EARLY LOGIN HANDLER ===

// Logout
if(isset($_GET['logout']) && $_GET['logout'] == '1') {
	$user = '';
	session_destroy();
	header('Location: index.php');
	exit; // Add exit after header redirect
}

// Connect to the database or die
include("db.php");

// Include all functions
include("functions.php");

// Include all actions for forms
include("actions.php");

// Troubleshooting


?>


<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<meta charset="utf-8" />
		<link rel="stylesheet" type="text/css" href="variant.css" media="screen,projection" title="Variant Portal" />
		<link rel="stylesheet" type="text/css" href="print.css" media="print"><script src="//code.jquery.com/jquery-1.11.2.min.js"></script>
		<script type="text/javascript" src="js/script.js" ></script>


		<title>Dr Strainlove</title>
	<style id="hotfix-js-disabled">
  #js-disabled { display: none !important; }
</style>
<style id="hotfix-overlay">
  /* Kill the “No JS” banner just in case */
  #js-disabled { display: none !important; }

  /* Clear floats from #desc so it doesn’t get overlapped */
  #desc::after { content: ""; display: table; clear: both; }

  /* Ensure main content starts below #desc */
  #main { clear: both; }

  /* Normalize stacking: force both sections into the normal flow */
  #desc, #main { position: relative !important; z-index: auto !important; }

  /* If your theme sets a z-index on #main, reset it */
  #main { z-index: 0 !important; }

  /* If an absolutely positioned child is covering the intro, disarm it */
  #desc *, #main * {
    /* keep normal flow unless a child truly needs positioning */
    /* comment this back out if it breaks some widget */
    /* position: static !important; */
  }
</style>


<?php $pw_upgrade_mode = (!empty($_SESSION['needs_pw_upgrade']) || (isset($_GET['mode']) && $_GET['mode']==='changePassword')); ?>
<style>
/* PW-UPGRADE HIDE */
<?php if ($pw_upgrade_mode): ?>
#desc, #left, .tabs, .nav, .noUpgradeHide { display: none !important; }
<?php endif; ?>
</style>
</head>

	<body>
<?php
// Standard flash renderer
if (!empty($_SESSION['feedback_message'])) {
    $flash_type = $_SESSION['feedback_type'] ?? 'info';
    $msg = $_SESSION['feedback_message'];
    echo '<div class="flash flash-' . htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8') . '">'
         . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);
}
?>

		<div id="container" >
			<!-- HEADERBAR: logo (left) + toplinks (right) -->
<div id="headerbar">
  <div id="logo" class="noPrint">
    <h1 style="margin:0;"><a href="index.php">Dr Strainlove</a></h1>
    <p style="margin:2px 0 0 0;">Powered by <i>Salmonella</i> genetics!</p>
  </div>

  <div id="toplinks" class="noPrint">
  <p style="margin:0;">
    Welcome to the machine. Today is <?= date('M j, Y'); ?>.<br>
    <?php
      if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
      $isLoggedIn = !empty($_SESSION['login']) && $_SESSION['login'] === '1';
      $uname = $isLoggedIn ? ($_SESSION['Username'] ?? 'User') : null;
    ?>

    <?php if ($isLoggedIn): ?>
      You are currently logged in as <strong><?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8'); ?></strong>.<br>
      If you are not <?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8'); ?>,
      please <a href="index.php?logout=1">Log out</a>.
    <?php else: ?>
      You are not logged in.<br>
      Please <a href="index.php?mode=login">Log in</a>.
    <?php endif; ?>
  </p>
</div>

</div>

			<div class='noPrint'>
				<h2 class="hide">Site menu:</h2>
				<ul id="navitab" style="position:relative; z-index:10; clear:both; margin-bottom:8px;">
									<li><a class="<?php echo (isset($_GET['mode']) && $_GET['mode'] == 'search' ? 'current' : NULL); // Simplified: Search tab is active if mode is 'search' ?>" href="index.php?mode=search&amp;type=word">Search strains</a></li>	

					<?php if(isset($_SESSION['Usertype']) && $_SESSION['Usertype'] == 'Superuser'){ ?>
						<li><a class="<?php echo (isset($_GET['mode']) && $_GET['mode'] == 'add' ? 'current' : NULL); ?>" href="index.php?mode=add&amp;Line=<?php echo isset($_SESSION['Line']) ? $_SESSION['Line'] : 1;?>">Add strain(s)</a></li>
						<li><a class="<?php echo (isset($_GET['mode']) && $_GET['mode'] == 'addUser' ? 'current' : NULL); ?>" href="index.php?mode=addUser">User management</a></li>
					<?php } ?>
					<li><a class="<?php echo (isset($_GET['mode']) && $_GET['mode'] == 'guidelines' ? 'current' : NULL); ?>" href="index.php?mode=guidelines">Guidelines</a></li>
					<li><a class="<?php echo (isset($_GET['mode']) && $_GET['mode'] == 'popStrains' ? 'current' : NULL); ?>" href="index.php?mode=popStrains">Popular Strains</a></li>
					<li><a class="<?php echo (isset($_GET['mode']) && $_GET['mode'] == 'misc' ? 'current' : NULL); ?>" href="index.php?mode=misc">Misc. lab related stuff</a></li>
				</ul>
			</div>

<div id="desc" class="noPrint" style="clear:both; float:none; position:relative; z-index:0; margin:0;">
  <div class="splitleft" style="float:none; position:static; margin:0; width:auto;">
    <h2>Welcome to the DA strain database!</h2>
    <p>This is where the good strains are. And some <i>E. coli</i> too...</p>
  </div>
</div>

<div id="main" style="clear:both; position:relative; z-index:0; margin-top:8px;">
				<p id="js-disabled" class="validation-fail">No JS detected! Some functionality disabled!</p>
<?php $today = date ('Y-m-d H:i:s'); ?>
<?php $helpmessage = ""; ?>
<?php $helpmessage2 = ""; ?>
            <?php
                // Display and clear feedback messages
                if (isset($_SESSION['feedback_message'])) {
                    echo "<p style='color: green; font-weight: bold; border: 1px solid green; padding: 5px; margin-bottom: 10px;'>" . htmlspecialchars($_SESSION['feedback_message']) . "</p>";
                    unset($_SESSION['feedback_message']); // Clear message after displaying
                }
            ?>
				<?php
				$value = isset($_GET['mode']) ? $_GET['mode'] : '';
				if (!empty($_SESSION['needs_pw_upgrade']) && (isset($mode) ? $mode !== 'changePassword' : $value !== 'changePassword')) {
    if (isset($mode)) { $mode = 'changePassword'; } else { $value = 'changePassword'; }
}
				// Force password upgrade only when needed
				if (!empty($_SESSION['needs_pw_upgrade']) && $value !== 'changePassword') { $value = 'changePassword'; }

				if (!isset($_SESSION['login']) || $_SESSION['login'] != '1') {
					include("login.php");
				} else {
					// --- START REPLACEMENT BLOCK ---
                    if ($value == 'search') {
                        // Always include the search form when mode is 'search'
                        include("search.php");
// ===== BRIDGE: REQUEST (GET/POST) -> SESSION for search filters =====
// Helper: first non-empty from REQUEST by list of keys
$reqVal = static function(array $names) {
    foreach ($names as $n) {
        if (isset($_REQUEST[$n]) && $_REQUEST[$n] !== '') return $_REQUEST[$n];
    }
    return null;
};

// Read raw values from either GET or POST, accepting a few aliases
$raw_min = $reqVal(['minNumber','minNum','min']);    // form may use minNum in old templates
$raw_max = $reqVal(['maxNumber','maxNum','max']);
$raw_lim = $reqVal(['limit']);
$raw_gen = $reqVal(['genotype','term1']);            // term1 is the legacy “contains” input

// Normalizer: digits only → int 0..999999, null if empty
$norm = static function($v) {
    if ($v === null) return null;
    $s = preg_replace('/\D+/', '', (string)$v);
    if ($s === '') return null;
    if (strlen($s) > 6) $s = substr($s, 0, 6);
    $n = (int)$s; if ($n < 0) $n = 0; if ($n > 999999) $n = 999999;
    return $n;
};

$min = $norm($raw_min);
$max = $norm($raw_max);

// If only one side provided -> exact match
if ($min !== null && $max === null) $max = $min;
if ($max !== null && $min === null) $min = $max;
// Swap if reversed
if ($min !== null && $max !== null && $min > $max) { $t=$min; $min=$max; $max=$t; }

// Push into the session keys that search1.php reads
if ($min !== null) $_SESSION['minNum'] = $min;       // used by search1.php
if ($max !== null) $_SESSION['maxNum'] = $max;       // used by search1.php

// Limit (default 1000, clamp 1..1_000_000) — only if provided
if ($raw_lim !== null) {
    $lim = $norm($raw_lim);
    if ($lim === null) $lim = 1000;
    if ($lim < 1) $lim = 1;
    if ($lim > 1000000) $lim = 1000000;
    $_SESSION['limit'] = $lim;
}

// Genotype → legacy term1 (keeps your existing type=word logic intact)
if ($raw_gen !== null) {
    $_SESSION['term1'] = (string)$raw_gen;
}

// ===== DEBUG PANEL (add &debug=1 to URL) =====
if (!empty($__DEBUG)) {
    echo '<div style="margin:12px 0;padding:10px;border:1px dashed #46b;border-radius:8px;background:#f3faff">';
    echo '<strong>DEBUG — bridged filters (REQUEST → SESSION)</strong><pre style="white-space:pre-wrap">';
    echo "REQUEST min: ", htmlspecialchars(var_export($raw_min,true)), "\n";
    echo "REQUEST max: ", htmlspecialchars(var_export($raw_max,true)), "\n";
    echo "REQUEST lim: ", htmlspecialchars(var_export($raw_lim,true)), "\n";
    echo "REQUEST genotype: ", htmlspecialchars(var_export($raw_gen,true)), "\n\n";
    echo "SESSION minNum: ", htmlspecialchars(var_export($_SESSION['minNum'] ?? null,true)), "\n";
    echo "SESSION maxNum: ", htmlspecialchars(var_export($_SESSION['maxNum'] ?? null,true)), "\n";
    echo "SESSION limit:  ", htmlspecialchars(var_export($_SESSION['limit'] ?? null,true)), "\n";
    echo "SESSION term1:  ", htmlspecialchars(var_export($_SESSION['term1'] ?? null,true)), "\n";
    echo "</pre></div>";
}


                        // Check if there was an explicit error message to display
                        if (isset($_GET['error']) && $_GET['error'] == 'error') {
                            echo "<span style='color: red'><strong>You need to provide at least one keyword or number range!</strong></span><br>";
                        }

                        // Determine if there are active search criteria stored in the session
                        // (These are cleared by the Reset button in actions.php)
                        $has_session_criteria = !empty($_SESSION['term1']) || !empty($_SESSION['term2']) || !empty($_SESSION['term3']) || !empty($_SESSION['term4']) ||
                                                !empty($_SESSION['notterm1']) || !empty($_SESSION['notterm2']) || !empty($_SESSION['notterm3']) || !empty($_SESSION['notterm4']) ||
                                                !empty($_SESSION['minNum']) || !empty($_SESSION['maxNum']) || !empty($_SESSION['sign1']);

                        // Determine if this is a request that should trigger results
                        // (A new POST search, a pagination click via GET, or returning when session criteria exist)
                        $is_new_search_post = (isset($_POST['isPosted']) && $_POST['isPosted'] == 'TRUE');
                        $is_pagination_or_link = (isset($_GET['search']) && $_GET['search'] == "1") || isset($_GET['page']); // 'search=1' might be legacy, keep for safety

                        // Show results if it's a new search, pagination, OR if returning to the page with criteria still in session
                        // But NOT if the 'Reset' button was just clicked (actions.php handles the reset, but index reloads)
                        $was_reset = (isset($_POST['reset-word']) || isset($_POST['reset-list'])); // Check if Reset was pressed

                        $should_show_results = ($is_new_search_post || $is_pagination_or_link || $has_session_criteria) && !$was_reset;


                        // Include the results table if criteria exist or it's a new search/page navigation
                        if ($should_show_results) {
                            include("search1.php");
                        }
                        // If none of the above, only the search form (already included) is shown.
                    }
                    // --- END REPLACEMENT BLOCK ---
					elseif ($value == 'myNum') {
						include("search1.php");
					}
					elseif (($value == 'myList') && isset($_POST['show'])) {
						include("search1.php"); // actions.php prepares list, search1 displays it
					}
					elseif (($value == 'myList') && isset($_POST['edit'])) {
						include("edit.php"); // actions.php prepares list, edit.php uses it
					}
					elseif (($value == 'myList') && isset($_POST['print'])) {
						include("print.php"); // actions.php prepares list, print.php uses it
					}
					elseif ($value == 'add') {
						include("insert.php");
					}
					elseif ($value == 'add3') {
						include("search1.php"); // Shows newly added strains
					}
					elseif ($value == 'edit2') {
						include("search1.php"); // Shows edited strains for confirmation
					}
					elseif ($value == 'pedigree') {
						include("search1.php");
					}
					elseif ($value == 'descendants') {
						include("search1.php");
					}
					elseif ($value == 'changePassword') {
						include('upgrade_password.php');
						exit;
					}
					elseif ((($value == 'addUser') || ($value == 'manageUsers')) && (!isset($_GET['save']) || $_GET['save'] != 'success')) {
						include("signup.php");
						exit; // prevent fall-through
						exit; // prevent fall-through
					}
					elseif ($value == 'guidelines') {
						include("guidelines.htm");
					}
					elseif ($value == 'popStrains') {
						include("popstrains.html");
					}
					elseif ($value == 'misc') {
						include("misc.html");
					}
					else { // Default action if logged in but no specific mode matches (or mode is empty)
						include("search.php"); // Show the search form by default
						// Check session again to see if results should be restored on default load
						$has_session_criteria = !empty($_SESSION['term1']) || !empty($_SESSION['term2']) || !empty($_SESSION['term3']) || !empty($_SESSION['term4']) ||
                                                !empty($_SESSION['notterm1']) || !empty($_SESSION['notterm2']) || !empty($_SESSION['notterm3']) || !empty($_SESSION['notterm4']) ||
                                                !empty($_SESSION['minNum']) || !empty($_SESSION['maxNum']) || !empty($_SESSION['sign1']);
                        if ($has_session_criteria) {
                             include("search1.php"); // Restore results if session has criteria
                        }
					}
				} // End else for logged in check
				?>

				<div class='noPrint'>
					<br><br>
					<p class="block"><strong>Please note:</strong> <?php echo $helpmessage . " " . $helpmessage2; ?></p>
				</div>
			</div> <div class='noPrint'>
				<div id="footer">
					<p>2011-2025 &middot; Joakim Näsvall &middot; This page was last updated 2025-11-11 by Joakim Näsvall and ChatGPT (some added magic, major code cleanup, migration to php 8 and finally runs as a real LAMP server!).</p>
				</div>
			</div>
		</div> </body>
</html>
