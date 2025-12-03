<?php declare(strict_types=1);
/** 
 * File: index.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */

### Set the default timezone / Jocke
date_default_timezone_set('Europe/Berlin');



if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// Lightweight debug toggle: add &debug=1 to the URL to show PHP errors
$__DEBUG = isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1';
if ($__DEBUG) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
// Require db.php if present, otherwise surface a clear 500 message (avoids silent fatal)
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    http_response_code(500);
    error_log('[BOOT] Missing db.php at ' . __DIR__ . '/db.php');
    if (!empty($__DEBUG)) {
        echo '<h2 style="color:red">Server misconfiguration: missing db.php</h2>';
    }
    exit('Server misconfiguration');
}

// Ensure application logic is loaded so POST actions (search/reset/store/etc.) are processed.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/permissions.php';
// ===== DEBUG: request probe (add &debug=1 to the URL) =====
if ($__DEBUG) {
    echo '<div style="margin:12px 0;padding:10px;border:1px dashed #999;border-radius:8px;background:#f9f9f9">';
    echo '<strong>DEBUG — request</strong><pre style="white-space:pre-wrap">';
    echo "URI: ", htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''), "\n\n";
    echo "\$_GET:\n", htmlspecialchars(print_r($_GET, true));
    echo "\n\$_POST:\n", htmlspecialchars(print_r($_POST, true));
    echo "</pre></div>";
}

// Logout
if(isset($_GET['logout']) && $_GET['logout'] == '1') {
	$user = '';
	session_destroy();
	header('Location: index.php');
	exit; // Add exit after header redirect
}

// Connect to the database or die
//include("db.php");

// Include all functions
//include("functions.php");

// Include all actions for forms
//include("actions.php");

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
// NOTE: Do not render flashes here. The centralized flash is rendered inside #main
// after the included page content so any included file (search1, insert, etc.)
// can set $_SESSION['feedback_message'] and it will appear consistently in one place.
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

                                        <?php if (can('add_strains')) { ?>
                                                <?php
                                                        // Mark Add tab active for both the add form and the "add results" view (mode=add3)
                                                        $add_modes = ['add','add3'];
                                                        $add_active = (isset($_GET['mode']) && in_array($_GET['mode'], $add_modes, true)) ? 'current' : NULL;
                                                ?>
                                                <li><a class="<?php echo $add_active; ?>" href="index.php?mode=add&amp;Line=<?php echo isset($_SESSION['Line']) ? $_SESSION['Line'] : 1;?>">Add strain(s)</a></li>
                                        <?php } ?>
                                        <?php if (can('manage_users')) { ?>
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
                // CENTRALIZED FLASH RENDERER (placed here so included files can set $_SESSION feedback)
                if (!empty($_SESSION['feedback_message'])) {
                    $flash_type = $_SESSION['feedback_type'] ?? 'info';
                    $msg = $_SESSION['feedback_message'];
                    if ($flash_type === 'success') {
                        $style = 'color:#075; background:#eefaf0; border:1px solid #9ad18b;';
                    } elseif ($flash_type === 'error') {
                        $style = 'color:#900; background:#ffecec; border:1px solid #f5c2c2;';
                    } else {
                        $style = 'color:#064; background:#ebf8ff; border:1px solid #bee3f8;';
                    }
                    echo '<div style="margin:12px 0;padding:10px 14px;border-radius:8px;font-weight:700;' . $style . '">';
                    echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                    echo '</div>';
                    unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);
                }
            ?>
				<?php
				$value = isset($_GET['mode']) ? $_GET['mode'] : '';

                // If add-results were requested with explicit min/max in the URL, ensure session contains the range.
                // actions.php now redirects to ?mode=add3&minNum=...&maxNum=...
                if ($value === 'add3' && (isset($_GET['minNum']) || isset($_GET['maxNum']))) {
                    $gmin = isset($_GET['minNum']) ? (int)$_GET['minNum'] : 0;
                    $gmax = isset($_GET['maxNum']) ? (int)$_GET['maxNum'] : 0;
                    if ($gmin > 0 && $gmax > 0) {
                        if ($gmin > $gmax) { [$gmin, $gmax] = [$gmax, $gmin]; }
                        $_SESSION['minNum'] = $gmin;
                        $_SESSION['maxNum'] = $gmax;
                        $_SESSION['last_search_page'] = 1;
                    }
                }

                 // If we just returned from a successful add (either mode=add3 or mode=add&show=add3),
                 // ensure search1.php can see the numeric range by copying recent_add -> minNum/maxNum.
                 // Actions.php sets $_SESSION['recent_add'] and redirects to ?mode=add&show=add3.
                 if (!empty($_SESSION['recent_add']) && is_array($_SESSION['recent_add'])
                     && ($value === 'add3' || ($value === 'add' && (isset($_GET['show']) && $_GET['show'] === 'add3')))
                 ) {
                     $rmin = (int)($_SESSION['recent_add']['min'] ?? 0);
                     $rmax = (int)($_SESSION['recent_add']['max'] ?? 0);
                     if ($rmin > 0 && $rmax > 0) {
                         if ($rmin > $rmax) { [$rmin, $rmax] = [$rmax, $rmin]; }
                         $_SESSION['minNum'] = $rmin;
                         $_SESSION['maxNum'] = $rmax;
                         $_SESSION['last_search_page'] = 1;
                     }
                 }
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
// Helper: first present (not only non-empty) value from REQUEST by list of keys
$reqVal = static function(array $names) {
    foreach ($names as $n) {
        if (array_key_exists($n, $_REQUEST)) return $_REQUEST[$n]; // PRESENCE matters ('' will be returned)
    }
    return null;
};

// Read raw values from either GET or POST, accepting a few aliases
$raw_min = $reqVal(['minNumber','minNum','min']);    // form may use minNum in old templates
$raw_max = $reqVal(['maxNumber','maxNum','max']);
$raw_lim = $reqVal(['limit']);
$raw_gen = $reqVal(['genotype','term1']);            // term1 is the legacy “contains” input
// signature filter
$raw_sign = $reqVal(['sign1']);

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
// IMPORTANT: use presence checks so an explicit empty field clears the session key
if (array_key_exists('minNumber', $_REQUEST) || array_key_exists('minNum', $_REQUEST) || array_key_exists('min', $_REQUEST)) {
    $_SESSION['minNum'] = ($min !== null) ? $min : '';
}
if (array_key_exists('maxNumber', $_REQUEST) || array_key_exists('maxNum', $_REQUEST) || array_key_exists('max', $_REQUEST)) {
    $_SESSION['maxNum'] = ($max !== null) ? $max : '';
}
// Genotype → legacy term1 (keeps your existing type=word logic intact)
// presence (even empty string) should overwrite session
if (array_key_exists('genotype', $_REQUEST) || array_key_exists('term1', $_REQUEST)) {
    $_SESSION['term1'] = (string)($raw_gen ?? '');
}
// Signature presence handling (explicit empty should clear session)
if (array_key_exists('sign1', $_REQUEST)) {
    $_SESSION['sign1'] = (string)($raw_sign ?? '');
}


// ===== DEBUG PANEL (add &debug=1 to URL) =====
if (!empty($__DEBUG)) {
    echo '<div style="margin:12px 0;padding:10px;border:1px dashed #46b;border-radius:8px;background:#f3faff">';
    echo '<strong>DEBUG — bridged filters (REQUEST → SESSION)</strong><pre style="white-space:pre-wrap">';
    echo "REQUEST min: ", htmlspecialchars(var_export($raw_min,true)), "\n";
    echo "REQUEST max: ", htmlspecialchars(var_export($raw_max,true)), "\n";
    echo "REQUEST lim: ", htmlspecialchars(var_export($raw_lim,true)), "\n";
    echo "REQUEST genotype: ", htmlspecialchars(var_export($raw_gen,true)), "\n";
    echo "REQUEST sign1: ", htmlspecialchars(var_export($raw_sign,true)), "\n\n";
    echo "SESSION minNum: ", htmlspecialchars(var_export($_SESSION['minNum'] ?? null,true)), "\n";
    echo "SESSION maxNum: ", htmlspecialchars(var_export($_SESSION['maxNum'] ?? null,true)), "\n";
    echo "SESSION limit:  ", htmlspecialchars(var_export($_SESSION['limit'] ?? null,true)), "\n";
    echo "SESSION term1:  ", htmlspecialchars(var_export($_SESSION['term1'] ?? null,true)), "\n";
    echo "SESSION sign1:  ", htmlspecialchars(var_export($_SESSION['sign1'] ?? null,true)), "\n";
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
                        } elseif (!empty($_SESSION['search_empty'])) {
                            // One-time message for completely empty search submissions
                            echo "<span style='color: red'>Please enter keywords OR a number range/signature.</span><br>";
                            unset($_SESSION['search_empty']);
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
                                                if (can('edit_strains')) {
                                                    include("edit.php"); // actions.php prepares list, edit.php uses it
                                                } else {
                                                    echo "<span style='color:red;font-weight:bold;'>You do not have permission to edit strains.</span>";
                                                }
                                        }
					elseif (($value == 'myList') && isset($_POST['print'])) {
						include("print.php"); // actions.php prepares list, print.php uses it
					}
                                        elseif ($value == 'add') {
                                                // If this request was triggered by a just-completed add (show=add3 or recent_add present),
                                                // show the add-results view instead of the form.
                                                // Synthesize mode=add3 in the request so search1.php takes the add3 branch.
                                                if ((isset($_GET['show']) && $_GET['show'] === 'add3') || (!empty($_SESSION['recent_add']) && is_array($_SESSION['recent_add']))) {
                                                    // Ensure search1.php sees mode=add3 (it checks $_GET['mode'] / $_REQUEST['mode'])
                                                    $_GET['mode'] = 'add3';
                                                    $_REQUEST['mode'] = 'add3';
                                                    if (can('add_strains')) {
                                                        include("search1.php"); // Show newly added rows in the Add tab (form removed)
                                                    } else {
                                                        echo "<span style='color:red;font-weight:bold;'>You do not have permission to add strains.</span>";
                                                    }
                                                } else {
                                                    if (can('add_strains')) {
                                                        include("insert.php");  // Normal add form
                                                    } else {
                                                        echo "<span style='color:red;font-weight:bold;'>You do not have permission to add strains.</span>";
                                                    }
                                                }
                                        }
                                        elseif ($value == 'add3') {
                        // Always show the add-results view for mode=add3.
                        // index.php already copies any minNum/maxNum from GET into the session earlier,
                        // so search1.php will have the numeric range it needs to render the table.
                        if (can('add_strains')) {
                            include("search1.php");
                        } else {
                            echo "<span style='color:red;font-weight:bold;'>You do not have permission to add strains.</span>";
                        }
                    }
                                        elseif ($value == 'edit2') {
                                                if (can('edit_strains')) {
                                                    include("search1.php"); // Shows edited strains for confirmation
                                                } else {
                                                    echo "<span style='color:red;font-weight:bold;'>You do not have permission to edit strains.</span>";
                                                }
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
                                                if (can('manage_users')) {
                                                    include("signup.php");
                                                    exit; // prevent fall-through
                                                } else {
                                                    echo "<span style='color:red;font-weight:bold;'>You do not have permission to manage users.</span>";
                                                }
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
