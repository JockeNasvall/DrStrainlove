<?php declare(strict_types=1);
require_once __DIR__ . '/lib/input_sanitize.php';
require_once __DIR__ . '/permissions.php';
// Ensure a session is active (actions rely on session)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// Collect action-level errors in one place (avoid undefined variable warnings)
$__action_errors = [];
// Ensure core helpers are available (require_once is safe if index.php already included them)
require_once __DIR__ . '/functions.php';

/** 
 * File: actions.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */
// Bind int-or-null safely for nullable INT columns
if (!function_exists('bindIntOrNull')) {
    function bindIntOrNull(PDOStatement $stmt, string $name, $val): void {
        if ($val === null || $val === '') {
            $stmt->bindValue($name, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($name, (int)$val, PDO::PARAM_INT);
        }
    }
}

// --- START: Strain Memory Management ---

// Function to add selected strains to session memory
/**
 * addStrainsToMemory — function documentation.
 *
 * @param mixed $selectedStrains
 * @return mixed
 */

function addStrainsToMemory($selectedStrains) {
    if (!isset($_SESSION['stored_strains'])) {
        $_SESSION['stored_strains'] = array();
}
    $added_count = 0;
    if (is_array($selectedStrains)) {
        foreach ($selectedStrains as $strain_id) {
            $strain_id_int = (int)$strain_id;
            if ($strain_id_int > 0 && !isset($_SESSION['stored_strains'][$strain_id_int])) {
                $_SESSION['stored_strains'][$strain_id_int] = 1; // Use key for uniqueness
                $added_count++;
            }
        }
    }
    return $added_count;
}

// Prepare the list of strains to be used by Show/Edit/Print
// This should contain ALL strains currently in memory
/**
 * prepareStrainListForAction — function documentation.
 *
 * @return mixed
 */

function prepareStrainListForAction() {
     if (isset($_SESSION['stored_strains']) && is_array($_SESSION['stored_strains']) && count($_SESSION['stored_strains']) > 0) {
         // Store the actual strain IDs (the keys)
         $_SESSION['action_strain_list'] = array_keys($_SESSION['stored_strains']);
}
else {
         $_SESSION['action_strain_list'] = array(); // Ensure it's an empty array
     }
}

// --- END: Strain Memory Management ---


// DO LOGIN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'login'){

    $uname = $_POST['username'];
    $pword = $_POST['password'];

    try {
        // Look up by username only
        $stmt = $dbh->prepare("SELECT * FROM users WHERE Username = :uname LIMIT 1");
        $stmt->bindParam(":uname", $uname);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $ok = false;
        if ($user && isset($user['Password'])) {
            $stored   = (string)$user['Password'];
            $used_md5 = false; // track legacy

            // Legacy MD5 (case-insensitive) OR modern password_hash()
            if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
                if (strcasecmp($stored, md5($pword)) === 0) {
                    $ok = true;
                    $used_md5 = true;
                }
            }
            if (!$ok) {
                $ok = password_verify($pword, $stored);
            }
        }

        if ($ok) {
            $_SESSION['login']     = '1';
            $_SESSION['Username']  = $user['Username'] ?? '';
            $_SESSION['user']      = $user['Username'] ?? '';
            $_SESSION['Usertype']  = $user['Usertype'] ?? '';
            if (isset($user['Signature'])) {
                $_SESSION['Signature'] = $user['Signature'];
            }
            $user = $_SESSION['Username'];
            $message = "Welcome " . e($_SESSION['Username']) . "!";

            // important: force upgrade page if legacy md5 was used
            session_regenerate_id(true);
            if (!empty($used_md5)) {
                $_SESSION['needs_pw_upgrade'] = 1; // show upgrade banner/form
                header('Location: index.php?mode=changePassword');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            unset($_SESSION['login'], $_SESSION['Username'], $_SESSION['user'], $_SESSION['Usertype'], $_SESSION['Signature']);
            flash_error('Login failed.');
        }

    } catch (Throwable $t) {
        unset($_SESSION['login'], $_SESSION['Username'], $_SESSION['Usertype'], $_SESSION['Signature']);
        $message = "Login error";
        error_log($t);
    }
}
// DO SEARCH (and handle memory actions triggered from search page)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'search'){

    // Check if Search button was pressed
    if( (isset($_POST['search-text']) && $_POST['search-text'] == 'Search') || (isset($_POST['search-list']) && $_POST['search-list'] == 'Search') ){

        // Save ALL potential fields from the combined form to session
        $_SESSION['sign1'] = $_POST['sign1'] ?? '';
        $_SESSION['term1'] = $_POST['term1'] ?? '';
        $_SESSION['term2'] = $_POST['term2'] ?? '';
        $_SESSION['term3'] = $_POST['term3'] ?? '';
        $_SESSION['term4'] = $_POST['term4'] ?? '';
        $_SESSION['notterm1'] = $_POST['notterm1'] ?? '';
        $_SESSION['notterm2'] = $_POST['notterm2'] ?? '';
        $_SESSION['notterm3'] = $_POST['notterm3'] ?? '';
        $_SESSION['notterm4'] = $_POST['notterm4'] ?? '';
        $_SESSION['minNum'] = $_POST['minNum'] ?? '';
        $_SESSION['maxNum'] = $_POST['maxNum'] ?? '';

        // Handle checkboxes
        $_SESSION['searchgenotype'] = isset($_POST['check']['genotype']) ? 1 : 0;
        $_SESSION['searchcomment'] = isset($_POST['check']['comment']) ? 1 : 0;

        // If all keyword AND number AND signature fields are empty, mark empty search
        if( empty($_POST['term1']) && empty($_POST['term2']) && empty($_POST['term3']) && empty($_POST['term4']) &&
            empty($_POST['notterm1']) && empty($_POST['notterm2']) && empty($_POST['notterm3']) && empty($_POST['notterm4']) &&
            empty($_POST['minNum']) && empty($_POST['maxNum']) && empty($_POST['sign1'])
        ) {
            // Produce a centralized error flash (will be rendered by index.php in main)
            $_SESSION['feedback_type'] = 'error';
            $_SESSION['feedback_message'] = 'Please enter keywords OR a number range/signature.';
            // Clear any session search keys to ensure a fresh state
            $_SESSION['term1'] = $_SESSION['term2'] = $_SESSION['term3'] = $_SESSION['term4'] = '';
            $_SESSION['notterm1'] = $_SESSION['notterm2'] = $_SESSION['notterm3'] = $_SESSION['notterm4'] = '';
            $_SESSION['minNum'] = $_SESSION['maxNum'] = $_SESSION['sign1'] = '';
            header("Location: index.php?mode=search&type=word");
            exit;
        }
    }

	// The reset button clears all submitted variables from all forms
	if(isset($_POST['reset-list']) || isset($_POST['reset-word'])){
		//Search:
		$_SESSION['total_records'] = '';
		$_SESSION['word'] = ''; // Still used? Keep for now
		$_SESSION['num'] = ''; // Still used? Keep for now
		$_SESSION['check'] = ''; // Still used? Keep for now
		$_SESSION['genotype'] = ''; // Still used? Keep for now
		$_SESSION['comment'] = ''; // Still used? Keep for now
		$_SESSION['term1'] = '';
		$_SESSION['term2'] = ''; // Corrected duplicate term1
		$_SESSION['term3'] = '';
		$_SESSION['term4'] = '';
		$_SESSION['notterm1'] = '';
		$_SESSION['notterm2'] = '';
		$_SESSION['notterm3'] = '';
		$_SESSION['notterm4'] = '';
		$_SESSION['sign1'] = '';
		$_SESSION['minNum'] = ''; // Add this
		$_SESSION['maxNum'] = ''; // Add this
        $_SESSION['searchgenotype'] = 0; // Reset checkbox state
        $_SESSION['searchcomment'] = 0; // Reset checkbox state


		// Redirect back to the appropriate (now single) search page type
		// Both reset buttons now functionally do the same thing
		header("Location: index.php?mode=search&type=word");
		exit; // Add exit after header redirect
	}

    // DO CLEAR STORED STRAINS (Button below form in search.php)
    if (isset($_POST['clear_memory'])) {
        unset($_SESSION['stored_strains']);
        unset($_SESSION['action_strain_list']); // Clear prepared list too
        $_SESSION['feedback_message'] = "Stored strain memory cleared.";
        // Redirect back to the search page (or referer)
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php?mode=search&type=word'));
        exit;
    }

} // End DO SEARCH block

// --- START: Memory Action Handling (triggered from results table in search1.php) ---

// DO STORE SELECTED STRAINS TO MEMORY (Explicit Store Button)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['store'])) {
    $selected = $_POST['selected'] ?? []; // Get newly selected
    $added_count = addStrainsToMemory($selected);

	$_SESSION['feedback_message'] = $added_count . " strain(s) added to memory. Total in memory: " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0);
            // Redirect back to the page the user came from to prevent re-POST on refresh
            $redirect_page = $_SESSION['last_search_page'] ?? 1; // Use stored page, default to 1
            header("Location: index.php?mode=search&type=word&page=" . $redirect_page); // Explicitly redirect
            exit;
        }

        // PREPARE LIST FOR 'SHOW SELECTED' (Implicit Store + Use Memory)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['show'])) {
     $selected = $_POST['selected'] ?? []; // Get newly selected from current page
     $added = addStrainsToMemory($selected); // Add new selections to memory first
     prepareStrainListForAction(); // Prepare the FULL list from memory
     // Set feedback based on whether strains were added or just shown
     if ($added > 0) {
        $_SESSION['feedback_message'] = $added . " strain(s) added to memory before showing. Total in memory: " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0);
}
else {
        $_SESSION['feedback_message'] = "Showing " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0) . " strain(s) from memory.";
     }
     // Let index.php route to search1.php in myList mode
}

// PREPARE LIST FOR 'EDIT SELECTED' (Implicit Store + Use Memory)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
     $selected = $_POST['selected'] ?? []; // Get newly selected
     $added = addStrainsToMemory($selected); // Add new selections first
     prepareStrainListForAction(); // Prepare the FULL list
     if ($added > 0) {
        $_SESSION['feedback_message'] = $added . " strain(s) added to memory before editing. Total in memory: " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0);
}
else {
         $_SESSION['feedback_message'] = "Editing " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0) . " strain(s) from memory.";
     }
     // Let index.php route to edit.php in myList mode
}

// PREPARE LIST FOR 'PRINT SELECTED' (Implicit Store + Use Memory)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['print'])) {
     $selected = $_POST['selected'] ?? []; // Get newly selected
     $added = addStrainsToMemory($selected); // Add new selections first
     prepareStrainListForAction(); // Prepare the FULL list
     if ($added > 0) {
        $_SESSION['feedback_message'] = $added . " strain(s) added to memory before printing. Total in memory: " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0);
}
else {
         $_SESSION['feedback_message'] = "Printing " . (isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0) . " strain(s) from memory.";
     }
     // Let index.php route to print.php in myList mode
}

// --- END: Memory Action Handling ---


// DO EDIT (This block processes the edit form submission)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'edit'){

    // Check user rights
    if (can('edit_strains')) {
        // Find out how many records there are to update
        $size = count($_POST['Genotype']);

        // Begin database transaction
        $dbh->beginTransaction();

        // Create SQL query (prepare once, bind per-row inside loop)
        $sql  = "UPDATE `strains` "
              . "SET `Genotype` = :genotype, "
              .     "`Donor` = :donor, "
              .     "`Recipient` = :recipient, "
              .     "`Comment` = :comment, "
              .     "`Signature` = :signature, "
              .     "`Created` = :created "
              . "WHERE `Strain` = :strain LIMIT 1";
        $stmt = $dbh->prepare($sql);

        $selected = []; // Re-initialize selected for this context
        $success = true; // Assume success initially

        for($i = 0; $i < $size; $i++){
            // Define variables
            $genotype  = str_replace(["\r", "\r\n", "\n"], " ", (string)($_POST['Genotype'][$i] ?? ''));
            $donor     = sanitize_num6($_POST['Donor'][$i]     ?? null); // NULL if blank
            $recipient = sanitize_num6($_POST['Recipient'][$i] ?? null); // NULL if blank
            $comment   = str_replace(["\r", "\r\n", "\n"], " ", (string)($_POST['Comment'][$i] ?? ''));
            $signature = (string)($_POST['Signature'][$i] ?? ($_SESSION['Signature'] ?? ''));
            $strain    = sanitize_num6($_POST['Strain'][$i] ?? null);     // MUST be present for edit

            // Created: use override if provided, else fallback
            if (empty($_POST['Created'][$i])) {
                $created = (string)($_POST['CreatedDate'][$i] ?? '');
            } else {
                $created = (string)$_POST['Created'][$i];
            }

            $selected[$i] = $strain; // Collect strains being edited

            // Bind per-row (so we can switch NULL/INT correctly)
            $stmt->bindValue(':genotype', $genotype, PDO::PARAM_STR);
            bindIntOrNull($stmt, ':donor', $donor);           // INT or NULL
            bindIntOrNull($stmt, ':recipient', $recipient);   // INT or NULL
            $stmt->bindValue(':comment',  $comment,   PDO::PARAM_STR);
            $stmt->bindValue(':signature',$signature,PDO::PARAM_STR);
            $stmt->bindValue(':created',  $created,   PDO::PARAM_STR);
            $stmt->bindValue(':strain',   (int)$strain, PDO::PARAM_INT);

            // Execute statement
            if (!$stmt->execute()) {
                $success = false;
                break;
            }
        }

        // Commit if all were successful
        if($success){
            $dbh->commit();
            $transaction_fail = false;
        } else {
            $dbh->rollBack();
            $transaction_fail = true;
        }
    } else { http_response_code(403); exit('Forbidden'); }
}


// DO ADD (This block processes the add strain form submission)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'add' && isset($_POST['submit'])){

    if (can('add_strains')) {
        $num_lines = count($_POST["txtGenotype"]);
        $_SESSION["Line"] = $num_lines;

        // ---- validation (unchanged, compact) ----
        if (!isset($_SESSION['saveFail'])) { $_SESSION['saveFail'] = []; } else { $_SESSION['saveFail'] = []; }
        $goAhead = true; $test = [];

        for($i = 1; $i <= $num_lines; $i++) {
            $_SESSION["txtGenotype"][$i]  = $_POST["txtGenotype"][$i] ?? '';
            $_SESSION["txtDonor"][$i]     = $_POST["txtDonor"][$i] ?? '';
            $_SESSION["txtRecipient"][$i] = $_POST["txtRecipient"][$i] ?? '';
            $_SESSION["txtComment"][$i]   = $_POST["txtComment"][$i] ?? '';

            $vG = !empty($_POST["txtGenotype"][$i]) ? 1 : 0;
            $vD = (strain_exists($_POST["txtDonor"][$i] ?? '')     || empty($_POST["txtDonor"][$i] ?? '')) ? 1 : 0;
            $vR = (strain_exists($_POST["txtRecipient"][$i] ?? '') || empty($_POST["txtRecipient"][$i] ?? '')) ? 1 : 0;

            $emptyRow = empty($_POST["txtDonor"][$i] ?? '') && empty($_POST['txtRecipient'][$i] ?? '') && empty($_POST['txtComment'][$i] ?? '');

            if (($vG + $vD + $vR) === 3) {
                $test[$i] = $vG ? ($emptyRow ? 'PASS_MINIMAL' : 'PASS') : 'FAIL';
            } else {
                $test[$i] = ($vG === 0 && $emptyRow) ? 'EMPTY' : 'FAIL';
            }
            if ($test[$i] === 'FAIL') { $_SESSION['saveFail'][$i] = ['Genotype'=>$vG,'Donor'=>$vD,'Recipient'=>$vR]; $goAhead = false; }
        }

        if (!empty($_SESSION['saveFail'])) $goAhead = false;

        if ($goAhead) {
            // ---- INSERT (auto-increment Strain) ----
            $sql = "INSERT INTO `strains`
                    (`Genotype`,`Donor`,`Recipient`,`Comment`,`Signature`)
                    VALUES (:genotype, :donor, :recipient, :comment, :signature)";

            $dbh->beginTransaction();
            $stmt = $dbh->prepare($sql);

            $inserted = [];
            $success  = true;
		$nInserted = 0;                 // <<< add this
		$lastIdSeen = 0;                // <<< add this
		
		
            // Optional one-off debug: add &dbg_add=1 to see failures inline
            $DBG = isset($_REQUEST['dbg_add']) && $_REQUEST['dbg_add'] === '1';

            for($i = 1; $i <= $num_lines; $i++) {
                if ($test[$i] !== 'PASS' && $test[$i] !== 'PASS_MINIMAL') continue;

                $genotype  = str_replace(["\r","\r\n","\n"], " ", (string)($_POST["txtGenotype"][$i] ?? ''));
                $donorRaw  = $_POST["txtDonor"][$i]     ?? null;
                $recipRaw  = $_POST["txtRecipient"][$i] ?? null;
                $donor     = sanitize_num6($donorRaw);     // NULL if blank/non-digits
                $recipient = sanitize_num6($recipRaw);     // NULL if blank/non-digits
                $comment   = str_replace(["\r","\r\n","\n"], " ", (string)($_POST["txtComment"][$i] ?? ''));
                $signature = (string)($_POST["txtSignature"] ?? ($_SESSION['Signature'] ?? ''));

                // Execute with a per-row param array (correctly sends NULLs/INTs)
                $params = [
                    ':genotype'  => $genotype,
                    ':donor'     => $donor,       // can be NULL or int
                    ':recipient' => $recipient,   // can be NULL or int
                    ':comment'   => $comment,
                    ':signature' => $signature,
                ];

                try {
                    $ok = $stmt->execute($params);
                    if (!$ok) { $success = false; break; }

$lastId = (int)$dbh->lastInsertId();
$lastIdSeen = $lastId ?: $lastIdSeen;   // keep most recent non-zero id
if ($lastId > 0) { $inserted[] = $lastId; }
$nInserted++;                            // <<< count successful rows
                } catch (Throwable $t) {
                    $ok = false;
                    if ($DBG) {
                        echo '<div style="color:#900;background:#fee;border:1px solid #caa;padding:8px;border-radius:6px;margin:8px 0">';
                        echo '<strong>Add strain failed on row '.htmlspecialchars((string)$i).'</strong><br>';
                        echo htmlspecialchars($t->getMessage());
                        echo '</div>';
                    }
                    error_log('[ADD STRAIN ERROR] row='.$i.' msg='.$t->getMessage());
                }

                if (!$ok) { $success = false; break; }

                $inserted[] = $dbh->lastInsertId();

                // Clear successfully inserted row data
                unset($_SESSION["txtGenotype"][$i], $_SESSION["txtDonor"][$i], $_SESSION["txtRecipient"][$i], $_SESSION["txtComment"][$i]);
            }

            if ($success) {
    $dbh->commit();
    $transaction_fail = false;
    unset($_SESSION["Line"]); // only on full success

    // Determine which IDs to show
    if (empty($inserted) && $nInserted > 0 && $lastIdSeen > 0) {
        // Fallback: contiguous range ending at the last seen auto-id
        $minId = max(1, $lastIdSeen - ($nInserted - 1));
        $maxId = $lastIdSeen;
    } elseif (!empty($inserted)) {
        $ids = array_map(static fn($x) => (int)$x, $inserted);
        $minId = min($ids);
        $maxId = max($ids);
    } else {
        // No rows reported as inserted
        $_SESSION['feedback_type']    = 'info';
        $_SESSION['feedback_message'] = 'No strains were added.';
        $dbg = (isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1') ? '&debug=1' : '';
        header("Location: index.php?mode=add&Line=" . ($_SESSION["Line"] ?? 1) . $dbg);
        exit;
    }

    // Drive results table exactly like a search, without switching tabs
    $_SESSION['minNum'] = $minId;
    $_SESSION['maxNum'] = $maxId;

    // Clear other filters so the range alone determines results
    $_SESSION['term1'] = $_SESSION['term2'] = $_SESSION['term3'] = $_SESSION['term4'] = '';
    $_SESSION['notterm1'] = $_SESSION['notterm2'] = $_SESSION['notterm3'] = $_SESSION['notterm4'] = '';
    $_SESSION['sign1'] = '';

    // One-time banner for search1.php to show ABOVE the table
    $_SESSION['recent_add'] = [
        'count' => $nInserted,
        'min'   => $minId,
        'max'   => $maxId,
    ];

    // Preserve debug flag (submitted as hidden field) across redirect if present.
    $dbg = (isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1') ? '&debug=1' : '';
    // Redirect to add3 and include the numeric range in the query string so the next request
    // deterministically shows the newly added rows (avoids transient session race).
    $loc = 'index.php?mode=add3&minNum=' . intval($minId) . '&maxNum=' . intval($maxId) . '&recent=1' . $dbg;
    session_write_close(); // ensure session is written
    header('Location: ' . $loc);
    exit;

} else {
    $dbh->rollBack();
    $transaction_fail = true;
    $_SESSION['feedback_type']    = 'error';
    $_SESSION['feedback_message'] = 'Insert failed.';
    $dbg = (isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1') ? '&debug=1' : '';
    header("Location: index.php?mode=add&Line=" . ($_SESSION["Line"] ?? 1) . $dbg);
    exit;
}

        } else {
            // Set a centralized error flash so the banner appears in the main area.
            $_SESSION['feedback_type'] = 'error';
            $_SESSION['feedback_message'] = 'There are fields with errors. Please correct highlighted fields and save again.';
            $dbg = (isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1') ? '&debug=1' : '';
            header("Location: index.php?mode=add&Line=" . ($_SESSION["Line"] ?? 1) . $dbg);
            exit;
        }
    } else { http_response_code(403); exit('Forbidden'); }
}


// DO UPDATE LINES (On Add Strain page)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'add' && isset($_POST['update_lines'])){
        if (!can('add_strains')) { http_response_code(403); exit('Forbidden'); }
        $num_lines = count($_POST["txtGenotype"]);

	unset($_SESSION["txtGenotype"]);
	unset($_SESSION["txtDonor"]);
	unset($_SESSION["txtRecipient"]);
	unset($_SESSION["txtComment"]);

	// Save sessions
	for($i = 1; $i <= $num_lines; $i++) {
		$_SESSION["txtGenotype"][$i] = $_POST["txtGenotype"][$i];
		$_SESSION["txtDonor"][$i] = $_POST["txtDonor"][$i];
		$_SESSION["txtRecipient"][$i] = $_POST["txtRecipient"][$i];
		$_SESSION["txtComment"][$i] = $_POST["txtComment"][$i];
	}

	$newLines = $_POST['menu1'];
	$_SESSION['Line'] = $newLines;

	header("Location: index.php?mode=add&Line=" . $newLines);
	exit; // Add exit
}

// DO RESET INPUT (On Add Strain page)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'add' && isset($_POST['reset'])){

        if (!can('add_strains')) { http_response_code(403); exit('Forbidden'); }
        unset($_SESSION["txtGenotype"]);
	unset($_SESSION["txtDonor"]);
	unset($_SESSION["txtRecipient"]);
	unset($_SESSION["txtComment"]);
	$_SESSION['Line'] = 1;

	unset($_SESSION['saveFail']);

	header("Location: index.php?mode=add&Line=1");
	exit; // Add exit
}

// DO ADD NEW USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form-type']) && $_POST['form-type'] == 'new-user'){

    $errorMessage = ""; // Initialize error message string
	// Save post variables
	$username  = $_POST['username'];
    $usertype  = $_POST['usertype'];
	$password  = $_POST['password'] ?? '';
	$signature = $_POST['signature'];

	// Get length of input
	$uLength = strlen($username);
	$pLength = strlen($password);
	$sLength = strlen($signature);

	// Validate
	$goAhead = TRUE;
	unset($validation); // Ensure validation is fresh

	$validation['username'] = 1;
	$validation['signature'] = 1;
	$validation['password'] = 1;

	if ($uLength < 5) {
		$goAhead = FALSE;
		$errorMessage .= "Username must be at least 5 characters long<br>";
		$validation['username'] = 0;
	}

	if ($sLength < 2 OR $sLength > 15) {
		$goAhead = FALSE;
		$errorMessage .= "Signature must be between 2 and 15 characters<br>";
		$validation['signature'] = 0;
	}

	if ($pLength > 0 && $pLength < 8) {
		$goAhead = FALSE;
		$errorMessage .= "Password must be at least 8 characters long<br>";
		$validation['password'] = 0;
	}

        // Check that type is one of the allowed roles
        if (!in_array($usertype, allowed_roles(), true)) {
                $goAhead = FALSE;
                $errorMessage .= "User Type must be Guest, User or Superuser<br>";
        }

	// Check if username already exists
	if(user_exists($username, "username")){
		$goAhead = FALSE;
		$errorMessage .= "User name already taken!<br>";
		$validation['username'] = 0;
	}

	// Check if signature already exists (was checking username twice)
	if(user_exists($signature, "signature")){
		$goAhead = FALSE;
		$errorMessage .= "Signature already taken!<br>";
		$validation['signature'] = 0;
	}

	// Validation passed
	if ($goAhead) {
		// Create SQL query
		$sql = "INSERT INTO users (Username, Usertype, Password, Signature) VALUES (:username, :usertype, :password, :signature)";

		// Prepare statement
		$stmt = $dbh->prepare($sql);

		// Hash password
		$password_hashed = ($pLength === 0) ? '' : password_hash($password, PASSWORD_DEFAULT);
// modern hash


		// Bind parameters
		$stmt->bindParam(":username", $username);
		$stmt->bindParam(":usertype", $usertype);
		$stmt->bindParam(":password", $password_hashed);
		$stmt->bindParam(":signature", $signature);

		// Execute statement
		// Execute statement with error handling (dev-friendly)
try {
    $stmt->execute();
    $insert_success = TRUE;
    if (!function_exists('flash_success')) {
        // minimal fallback
        $_SESSION['feedback_type'] = 'success';
        $_SESSION['feedback_message'] = 'User created.';
    } else {
        flash_success('User created.');
    }
} catch (PDOException $e) {
    $insert_success = FALSE;
    if (ini_get('display_errors')) {
        if (!function_exists('flash_error')) {
            $_SESSION['feedback_type'] = 'error';
            $_SESSION['feedback_message'] = 'User creation failed: ' . $e->getMessage();
        } else {
            flash_error('User creation failed: ' . $e->getMessage());
        }
    } else {
        error_log($e);
        if (!function_exists('flash_error')) {
            $_SESSION['feedback_type'] = 'error';
            $_SESSION['feedback_message'] = 'User creation failed.';
        } else {
            flash_error('User creation failed.');
        }
    }
}
        // Feedback message
        if ($insert_success === TRUE) {
            flash_success('User created.');
}
else {
            $_SESSION['feedback_message'] = "User creation failed.";}
	}
else {
	    // Ensure insert_success is false if validation fails
	    $insert_success = FALSE;
        $_SESSION['feedback_message'] = ($errorMessage ?? '') !== '' ? $errorMessage : 'User creation failed (validation).';}
}


// DO EDIT EXISTING USER (preview/apply)
if (isset($_POST['form-type']) && in_array($_POST['form-type'], ['edit-user-load','edit-user-preview','edit-user-apply','edit-user-cancel','edit-user-cancel-apply'], true)) {
    if (!isset($_SESSION['Usertype']) || $_SESSION['Usertype']!=='Superuser') { http_response_code(403); exit('Forbidden'); }
    $ft = $_POST['form-type'];

    if ($ft === 'edit-user-preview') {
        $uname = $_POST['username'] ?? '';
        $newSig = $_POST['signature'] ?? '';
        $newType = $_POST['usertype'] ?? '';
        $resetPw = !empty($_POST['reset_password']);
        if ($uname === '') { $_SESSION['feedback_type']='error'; $_SESSION['feedback_message']='No user selected.'; header('Location: index.php?mode=addUser&umode=edit'); exit; }

        // Determine whether the database has 'users' or 'Users' table name
        $usersTable = 'users';
        try {
            $hasLower = (bool)$dbh->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            $hasUpper = (bool)$dbh->query("SHOW TABLES LIKE 'Users'")->fetchColumn();
            if ($hasLower) {
                $usersTable = 'users';
            } elseif ($hasUpper) {
                $usersTable = 'Users';
            } else {
                // default fallback
                $usersTable = 'users';
            }
        } catch (Throwable $t) {
            // In case the above check fails, fall back safely
            $usersTable = 'users';
        }

        $stmt = $dbh->prepare("SELECT * FROM `{$usersTable}` WHERE Username = :u LIMIT 1");
        $stmt->bindParam(':u', $uname);
        $stmt->execute();
        $cur = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cur) { $_SESSION['feedback_type']='error'; $_SESSION['feedback_message']='User not found.'; header('Location: index.php?mode=addUser&umode=edit'); exit; }

        $sets = []; $params = [':u'=>$uname];
        if (isset($cur['Signature']) && $newSig !== $cur['Signature']) { $sets[] = "`Signature` = :s"; $params[':s'] = $newSig; }
        if (isset($cur['Usertype']) && $newType !== '' && $newType !== $cur['Usertype']) { $sets[] = "`Usertype` = :t"; $params[':t'] = $newType; }
        if ($resetPw) { $sets[] = "`Password` = ''"; }

        if (empty($sets)) {
            $_SESSION['feedback_type'] = 'info';
            $_SESSION['feedback_message'] = 'No changes to apply.';
            unset($_SESSION['user_edit_preview']);
            header('Location: index.php?mode=addUser&umode=edit&selected_user=' . urlencode($uname));
            exit;
        }

        $sql = "UPDATE `{$usersTable}` SET " . implode(", ", $sets) . " WHERE `Username` = :u";
        $pretty = $sql;
        foreach ($params as $k=>$v) { if (in_array($k, [':u',':s',':t'], true)) { $rep = $dbh->quote($v); $pretty = str_replace($k, $rep, $pretty); } }
        $_SESSION['user_edit_preview'] = ['sql'=>$sql, 'params'=>$params, 'sql_pretty'=>$pretty];
        header('Location: index.php?mode=addUser&umode=edit&selected_user=' . urlencode($uname));
        exit;
    }
    elseif ($ft === 'edit-user-apply') {
        $plan = $_SESSION['user_edit_preview'] ?? null;
        if (!$plan || empty($plan['sql'])) { $_SESSION['feedback_type']='error'; $_SESSION['feedback_message']='Nothing to apply.'; header('Location: index.php?mode=addUser&umode=edit'); exit; }
        try {
            $stmt = $dbh->prepare($plan['sql']);
            foreach (($plan['params'] ?? []) as $k=>$v) { $stmt->bindValue($k, $v); }
            $stmt->execute();
            unset($_SESSION['user_edit_preview']);
            $_SESSION['feedback_type']='success';
            $_SESSION['feedback_message']='User updated.';
        } catch (Throwable $t) {
            error_log($t);
            $_SESSION['feedback_type']='error';
            $_SESSION['feedback_message']='Update failed.';
        }
        header('Location: index.php?mode=addUser&umode=edit');
        exit;
    }
}

/* __ACTION_ERROR_RENDER__ (fallback) */
if (!empty($__action_errors)) {
    echo '<div style="max-width:800px;margin:12px auto;padding:10px;border:1px solid #caa;background:#fee;color:#900;border-radius:8px">';
    echo '<strong>We found some issues:</strong><br>';
    echo implode('<br>', array_map('htmlspecialchars', $__action_errors));
    echo '<br><br><button onclick="history.back()">Go back</button>';
    echo '</div>';
    exit;
}
