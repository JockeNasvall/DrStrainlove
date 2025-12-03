<?php
/**
 * File: search1.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */

require_once __DIR__ . '/permissions.php';


$start_strain = 0;
$transaction_fail = false;
// searchgenotype and searchcomment will be defined by search.php or session
$inserted = array();
// term1, term2, term3, term4 will be defined by search.php or session
$sql = null;  // Initialize SQL variables as null
$sql2 = null; // Initialize SQL variables as null
// minNum and maxNum will NOW be read directly from session here
$minNum = $_SESSION['minNum'] ?? ''; // Read minNum from session
$maxNum = $_SESSION['maxNum'] ?? ''; // Read maxNum from session
// Get search checkbox state from session as well, needed for highlighting
$searchgenotype = $_SESSION['searchgenotype'] ?? 0;
$searchcomment = $_SESSION['searchcomment'] ?? 0;
// Get terms from session too, needed for highlighting
$term1 = $_SESSION['term1'] ?? '';
$term2 = $_SESSION['term2'] ?? '';
$term3 = $_SESSION['term3'] ?? '';
$term4 = $_SESSION['term4'] ?? '';
$sign1 = $_SESSION['sign1'] ?? ''; // Also get sign1 for binding

// Treat these values as "blank" in results
$BLANK_CREATED = ['0000-00-00 00:00:00', '1970-01-01 01:00:01'];


//Begin Search for keywords (Now handles combined search)//
if(isset($_GET['type']) && $_GET['type'] == 'word') {

    // Determine if this is a signature-only search first
	$is_signature_only = empty($term1) && empty($term2) && empty($term3) && empty($term4) &&
	                     empty($notterm1) && empty($notterm2) && empty($notterm3) && empty($notterm4) &&
	                     empty($minNum) && empty($maxNum) && // Also check number fields
	                     !empty($sign1);

	if($is_signature_only){
		// Build SQL statements for signature only
		$sql = "SELECT * FROM strains WHERE Signature LIKE :sign1 ORDER BY Strain ASC LIMIT :startval, :limitval";
		$sql2 = "SELECT COUNT(Strain) FROM strains WHERE Signature LIKE :sign1";
		// $message removed

	} else { // This else pairs with the "signature only" check
	    // --- Combined Keyword / Number / Signature Logic ---

	    $sqlConditions = array(); // Array to hold ALL potential WHERE clauses
	    $criteriaFound = false; // Flag to track if ANY valid criteria are entered

	    // --- 1. Evaluate Keyword Criteria ---
	    if (!empty($term1) || !empty($term2) || !empty($term3) || !empty($term4) ||
	        !empty($notterm1) || !empty($notterm2) || !empty($notterm3) || !empty($notterm4))
	    {
	        $criteriaFound = true; // Mark keywords as found
	        // Determine search fields (Genotype/Comment) - needed for building query
	        if ($searchgenotype && !$searchcomment) {
	            $field1 = "Genotype"; $field2 = "Genotype";
	            // $message removed
	        } elseif ($searchgenotype && $searchcomment) {
	            $field1 = "Genotype"; $field2 = "Comment";
	            // $message removed
	        } elseif (!$searchgenotype && $searchcomment) {
	            $field1 = "Comment"; $field2 = "Comment";
	            // $message removed
	        } else { // Default
	            $field1 = "Genotype"; $field2 = "Genotype";
	             // $message removed
	        }

	        // Add keyword inclusion conditions
	        if(!empty($term1)) { $sqlConditions[] = "($field1 LIKE :term1 OR $field2 LIKE :commentterm1)"; }
	        if(!empty($term2)) { $sqlConditions[] = "($field1 LIKE :term2 OR $field2 LIKE :commentterm2)"; }
	        if(!empty($term3)) { $sqlConditions[] = "($field1 LIKE :term3 OR $field2 LIKE :commentterm3)"; }
	        if(!empty($term4)) { $sqlConditions[] = "($field1 LIKE :term4 OR $field2 LIKE :commentterm4)"; }

	        // Add keyword exclusion conditions
	        if(!empty($notterm1)) { $sqlConditions[] = "($field1 NOT LIKE :notterm1 AND $field2 NOT LIKE :commentnotterm1)"; }
	        if(!empty($notterm2)) { $sqlConditions[] = "($field1 NOT LIKE :notterm2 AND $field2 NOT LIKE :commentnotterm2)"; }
	        if(!empty($notterm3)) { $sqlConditions[] = "($field1 NOT LIKE :notterm3 AND $field2 NOT LIKE :commentnotterm3)"; }
	        if(!empty($notterm4)) { $sqlConditions[] = "($field1 NOT LIKE :notterm4 AND $field2 NOT LIKE :commentnotterm4)"; }
	    }

	    // --- 2. Evaluate Number Criteria ---
	    if (!empty($minNum) || !empty($maxNum)) {
	        $criteriaFound = true; // Mark numbers as found
	         if (!empty($minNum) && !empty($maxNum) && $minNum > $maxNum) { // Inverted range
	            $sqlConditions[] = "(Strain >= :maxNum AND Strain <= :minNum)";
	        } elseif (!empty($minNum) && !empty($maxNum) && $minNum <= $maxNum) { // Normal range
	            $sqlConditions[] = "(Strain >= :minNum AND Strain <= :maxNum)";
	        } elseif (!empty($minNum) && empty($maxNum)) { // Single min number
	            $sqlConditions[] = "(Strain = :minNum)";
	        } elseif (empty($minNum) && !empty($maxNum)) { // Single max number
	            $sqlConditions[] = "(Strain = :maxNum)";
	        }
	    }

	    // --- 3. Evaluate Signature Criteria ---
	    if (!empty($sign1)) {
	         $criteriaFound = true; // Mark signature as found
	         $sqlConditions[] = "(Signature LIKE :sign1)";
	    }

	    // --- 4. Assemble the final SQL ---
	    if ($criteriaFound) { // Only assemble if criteria were found
	        $sqlBase = "FROM strains WHERE " . implode(" AND ", $sqlConditions);
	        $sql = "SELECT * " . $sqlBase . " ORDER BY Strain ASC LIMIT :startval, :limitval";
	        $sql2 = "SELECT COUNT(Strain) " . $sqlBase;
	        // REMOVED conditional message setting block

	    } else {
	        // No criteria entered at all — set centralized flash and stop rendering this include.
	        $_SESSION['feedback_type'] = 'error';
	        $_SESSION['feedback_message'] = 'Please enter keywords OR a number range/signature.';
	        return; // stop further rendering of search1.php so index.php will render the flash
	    } // End check if criteria were found

	} // End else for combined logic
}
//End Combined Search Logic//

// --- REMOVED old type=number block --- //

//Show Donor/ recipient from clickable link in table:
if ($_GET['mode'] == 'myNum') {
	$page = 1;
	$limit = 100;
	$myNum = $_GET['myNum'];
	$sql  = "SELECT * FROM strains WHERE Strain = :myNum";
	$sql2 = "SELECT COUNT(Strain) FROM strains WHERE Strain = :myNum";
	// Optional: Set a specific message if desired, otherwise generic will be used
}
//End Show Donor/ recipient from clickable link in table

//Begin List selected strains (Now uses session list prepared by actions.php)
if ($_GET['mode'] == 'myList') {
	$page = 1;
	$limit = 100;
	$list = $_SESSION['action_strain_list'] ?? array(); // Use list prepared by actions.php

	if(count($list) == 0) {
		// Centralized feedback for empty memory
		$_SESSION['feedback_type'] = 'error';
		$_SESSION['feedback_message'] = 'No strains currently stored in memory.';
		return;
	} else {
		// Prepare parameters for strains in list
		$listInQuery = array();
		for($i = 1; $i <= count($list); $i++){
			$listInQuery[$i] = ":list_" . $i;
		}
		$listInQuery = implode(", ", $listInQuery);
		$numberOfListParameters = count($list);

		// $message removed - generic used later

		$sql  = "SELECT * FROM strains WHERE Strain IN (" . $listInQuery . ") ORDER BY Strain ASC"; // Added ORDER BY
		$sql2 = "SELECT COUNT(Strain) FROM strains WHERE Strain IN (" . $listInQuery . ")";
	}
}
//End List selected strains

//Begin List inserted strains
if ($_GET['mode'] == 'add3') {
    // Show newly added strains. Prefer the one-time recent_add range set by actions.php.
    $page = 1;
    $limit = 100;

    $add_minId = null;
    $add_maxId = null;

    // Recent add (set by actions.php on successful insert)
    if (!empty($_SESSION['recent_add']) && is_array($_SESSION['recent_add'])) {
        $add_minId = (int)($_SESSION['recent_add']['min'] ?? 0);
        $add_maxId = (int)($_SESSION['recent_add']['max'] ?? 0);
    }

    // Fallback: if no recent_add, but session min/max exist use them
    if (($add_minId <= 0 || $add_maxId <= 0) && (!empty($_SESSION['minNum']) || !empty($_SESSION['maxNum']))) {
        $tmin = $_SESSION['minNum'] ?? '';
        $tmax = $_SESSION['maxNum'] ?? '';
        $add_minId = ($tmin !== '') ? (int)$tmin : ($tmax !== '' ? (int)$tmax : 0);
        $add_maxId = ($tmax !== '') ? (int)$tmax : ($tmin !== '' ? (int)$tmin : 0);
    }

    if ($add_minId > 0 && $add_maxId > 0) {
        // Normalize order
        if ($add_minId > $add_maxId) { $tmp = $add_minId; $add_minId = $add_maxId; $add_maxId = $tmp; }
        if ($add_minId === $add_maxId) {
            $sql  = "SELECT * FROM strains WHERE Strain = :minNum ORDER BY Strain ASC LIMIT :startval, :limitval";
            $sql2 = "SELECT COUNT(Strain) FROM strains WHERE Strain = :minNum";
        } else {
            $sql  = "SELECT * FROM strains WHERE Strain BETWEEN :minNum AND :maxNum ORDER BY Strain ASC LIMIT :startval, :limitval";
            $sql2 = "SELECT COUNT(Strain) FROM strains WHERE Strain BETWEEN :minNum AND :maxNum";
        }
        // expose to parameter binding later
        $minNum = $add_minId;
        $maxNum = $add_maxId;
    } else {
        $_SESSION['feedback_type'] = 'error';
        $_SESSION['feedback_message'] = 'No strains were added.';
        return;
    }
}
//End List inserted strains

//Begin List edited strains
if ($_GET['mode'] == 'edit2') {
	if(!$transaction_fail){
		$page = 1;
		$limit = 100;
		// Edit confirmation uses $selected passed from actions.php after DB update
		$list = $selected ?? ($_SESSION['action_strain_list'] ?? array()); // Fallback to session list if needed
		// Ensure $list is always an array
		if (!isset($list) || !is_array($list)) {
		    $list = array();
		}

		$numberOfListParameters = count($list);

		if ($numberOfListParameters > 0) {
		    // Prepare parameters for strains in list
		    $listInQuery = array();
		    for($i = 1; $i <= $numberOfListParameters; $i++){
		        $listInQuery[$i] = ":list_" . $i;
		    }
		    $listInQuery = implode(", ", $listInQuery);

		    // $message removed
		    $sql  = "SELECT * FROM strains WHERE Strain IN (" . $listInQuery . ") ORDER BY Strain ASC"; // Added ORDER BY
		    $sql2 = "SELECT COUNT(Strain) FROM strains WHERE Strain IN (" . $listInQuery . ")";
		} else {
		     // If somehow $selected/list was empty
		     echo "No strains were selected for editing.<br>";
		     $sql = null;
		     $sql2 = null; // Also stop the count query
		}
	} else {
		// Transaction failed during edit
		echo "<span style='color: red'><strong>Database update failed during edit! Please check data.</strong></span><br>";
		$sql = null; // Ensure SQL is unset
		$sql2 = null;
	}
}
//End List inserted strains
//Begin Pedigree search
if ($_GET['mode'] == 'pedigree') {

    try {
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $page = 1;
        $limit = 100;
        $start_strain = isset($_GET['strain']) ? (int)$_GET['strain'] : 0; // Check isset

        if ($start_strain <= 0) {
            echo "No strain selected.<br>";
            $sql = null; // Prevent query
            $sql2 = null;
        } else {
            // --- Use BATCHED PHP loop to find all ancestors ---
            $pedigree_list = array();
            $strains_to_check = array();
            $pedigree_list[$start_strain] = 1;
            $strains_to_check[] = $start_strain;
            $max_generations = 50;
            $i = 0;

            while (count($strains_to_check) > 0 && $i < $max_generations) {
                $placeholders = implode(',', array_fill(0, count($strains_to_check), '?'));
                $stmt_pedigree = $dbh->prepare("SELECT Recipient, Donor FROM strains WHERE Strain IN ($placeholders)");
                $stmt_pedigree->execute($strains_to_check);
                $ancestors_list = $stmt_pedigree->fetchAll(PDO::FETCH_ASSOC);
                $strains_to_check = array();
                foreach ($ancestors_list as $ancestors) {
                    $recipient = (int)$ancestors['Recipient'];
                    $donor = (int)$ancestors['Donor'];
                    if ($recipient > 0 && !isset($pedigree_list[$recipient])) {
                        $pedigree_list[$recipient] = 1;
                        $strains_to_check[] = $recipient;
                    }
                    if ($donor > 0 && !isset($pedigree_list[$donor])) {
                        $pedigree_list[$donor] = 1;
                        $strains_to_check[] = $donor;
                    }
                }
                $i++;
            }

            if ($i >= $max_generations) {
                echo "<span style='color: red'>Error: Pedigree search stopped. More than $max_generations generations found.</span><br>";
                $sql = null;
                $sql2 = null;
            } else {
                $list = array_keys($pedigree_list);
                $numberOfListParameters = count($list);
                if($numberOfListParameters == 0) {
                    echo "No ancestors found.<br>";
                    $sql = null;
                    $sql2 = null;
                } else {
                    $temp_table_name = "temp_pedigree_" . uniqid();
                    $dbh->exec("CREATE TEMPORARY TABLE $temp_table_name (Strain INT PRIMARY KEY) ENGINE=MEMORY");
                    $sql_insert = "INSERT INTO $temp_table_name (Strain) VALUES (:strain)";
                    $stmt_insert = $dbh->prepare($sql_insert);
                    foreach ($list as $strain_id) {
                        $stmt_insert->bindParam(':strain', $strain_id, PDO::PARAM_INT);
                        $stmt_insert->execute();
                    }
                    $sql = "SELECT s.* FROM strains s JOIN $temp_table_name t ON s.Strain = t.Strain ORDER BY s.Strain ASC LIMIT :startval, :limitval";
                    $sql2 = "SELECT COUNT(s.Strain) FROM strains s JOIN $temp_table_name t ON s.Strain = t.Strain";
                    // $message removed
                }
            }
        }
    } catch (Throwable $t) {
        echo "<span style='color: red; font-weight: bold;'>PEDIGREE ERROR:</span><br>" . $t->getMessage();
        $sql = null;
        $sql2 = null;
    }
}
//End Pedigree search
//Begin Descendants search
if ($_GET['mode'] == 'descendants') {
    try {
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $page = 1;
        $limit = 100;
        $start_strain = isset($_GET['strain']) ? (int)$_GET['strain'] : 0; // Check isset

        if ($start_strain <= 0) {
            echo "No strain selected.<br>";
            $sql = null;
            $sql2 = null;
        } else {
            $descendants_list = array();
            $strains_to_check = array();
            $descendants_list[$start_strain] = 1;
            $strains_to_check[] = $start_strain;
            $max_generations = 50;
            $i = 0;

            while (count($strains_to_check) > 0 && $i < $max_generations) {
                $placeholders = implode(',', array_fill(0, count($strains_to_check), '?'));
                $stmt_desc = $dbh->prepare("SELECT Strain FROM strains WHERE Recipient IN ($placeholders) OR Donor IN ($placeholders)");
                $params = array_merge($strains_to_check, $strains_to_check);
                $stmt_desc->execute($params);
                $child_list = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);
                $strains_to_check = array();
                foreach ($child_list as $child) {
                    $strain_id = (int)$child['Strain'];
                    if ($strain_id > 0 && !isset($descendants_list[$strain_id])) {
                        $descendants_list[$strain_id] = 1;
                        $strains_to_check[] = $strain_id;
                    }
                }
                $i++;
            }

            if ($i >= $max_generations) {
                echo "<span style='color: red'>Error: Descendant search stopped. More than $max_generations generations found.</span><br>";
                $sql = null;
                $sql2 = null;
            } else {
                $list = array_keys($descendants_list);
                $numberOfListParameters = count($list);
                if($numberOfListParameters == 0) {
                    echo "No descendants found.<br>";
                    $sql = null;
                    $sql2 = null;
                } else {
                    $temp_table_name = "temp_descendants_" . uniqid();
                    $dbh->exec("CREATE TEMPORARY TABLE $temp_table_name (Strain INT PRIMARY KEY) ENGINE=MEMORY");
                    $sql_insert = "INSERT INTO $temp_table_name (Strain) VALUES (:strain)";
                    $stmt_insert = $dbh->prepare($sql_insert);
                    foreach ($list as $strain_id) {
                        $stmt_insert->bindParam(':strain', $strain_id, PDO::PARAM_INT);
                        $stmt_insert->execute();
                    }
                    $sql = "SELECT s.* FROM strains s JOIN $temp_table_name t ON s.Strain = t.Strain ORDER BY s.Strain ASC LIMIT :startval, :limitval";
                    $sql2 = "SELECT COUNT(s.Strain) FROM strains s JOIN $temp_table_name t ON s.Strain = t.Strain";
                    // $message removed
                }
            }
        }
    } catch (Throwable $t) {
        echo "<span style='color: red; font-weight: bold;'>DESCENDANTS ERROR:</span><br>" . $t->getMessage();
        $sql = null;
        $sql2 = null;
    }
}
//End Descendants search


// Do the actual mysql query
if($sql && $sql2){ // Make sure both SQL strings are set before proceeding

    // Prepare PDO statements
    $stmt = $dbh->prepare($sql);
    $stmtTotal = $dbh->prepare($sql2);

    // --- Dynamic Parameter Building for Total Count ($stmtTotal) ---
    $params_total = [];

    // ** 1. Handle Combined Search parameters (now under type=word) **
    if(isset($_GET['type']) && $_GET['type'] == 'word') {
        $sign1param = '%' . $sign1 . '%'; // Prepare for potential use

        // Check if it was signature-only search
        $is_signature_only = empty($term1) && empty($term2) && empty($term3) && empty($term4) &&
                             empty($notterm1) && empty($notterm2) && empty($notterm3) && empty($notterm4) &&
                             empty($minNum) && empty($maxNum) &&
                             !empty($sign1);

        if ($is_signature_only) {
            // Only signature parameter is needed
            $params_total['sign1'] = $sign1param;
        } else {
            // --- Bind parameters for ANY combination (Keywords, Numbers, Signature) ---

            // Bind keyword parameters IF they have values
            if (!empty($term1)) {
                $term1param = '%' . $term1 . '%';
                $params_total['term1'] = $term1param;
                $params_total['commentterm1'] = $term1param;
            }
            if (!empty($term2)) {
                $term2param = '%' . $term2 . '%';
                $params_total['term2'] = $term2param;
                $params_total['commentterm2'] = $term2param;
            }
            if (!empty($term3)) {
                $term3param = '%' . $term3 . '%';
                $params_total['term3'] = $term3param;
                $params_total['commentterm3'] = $term3param;
            }
            if (!empty($term4)) {
                $term4param = '%' . $term4 . '%';
                $params_total['term4'] = $term4param;
                $params_total['commentterm4'] = $term4param;
            }
            if (!empty($notterm1)) {
                $notterm1param = '%' . $notterm1 . '%';
                $params_total['notterm1'] = $notterm1param;
                $params_total['commentnotterm1'] = $notterm1param;
            }
            if (!empty($notterm2)) {
                $notterm2param = '%' . $notterm2 . '%';
                $params_total['notterm2'] = $notterm2param;
                $params_total['commentnotterm2'] = $notterm2param;
            }
            if (!empty($notterm3)) {
                $notterm3param = '%' . $notterm3 . '%';
                $params_total['notterm3'] = $notterm3param;
                $params_total['commentnotterm3'] = $notterm3param;
            }
            if (!empty($notterm4)) {
                $notterm4param = '%' . $notterm4 . '%';
                $params_total['notterm4'] = $notterm4param;
                $params_total['commentnotterm4'] = $notterm4param;
            }

            // Bind number parameters IF they have values
            if (!empty($minNum) || !empty($maxNum)) {
                if (!empty($minNum) && !empty($maxNum) && $minNum > $maxNum) { // Inverted
                    $params_total['maxNum'] = (int) $maxNum;
                    $params_total['minNum'] = (int) $minNum;
                } elseif (!empty($minNum) && !empty($maxNum) && $minNum <= $maxNum) { // Normal
                    $params_total['minNum'] = (int) $minNum;
                    $params_total['maxNum'] = (int) $maxNum;
                } elseif (!empty($minNum) && empty($maxNum)) { // Min only
                    $params_total['minNum'] = (int) $minNum;
                } elseif (empty($minNum) && !empty($maxNum)) { // Max only
                    $params_total['maxNum'] = (int) $maxNum;
                }
            }

            // Bind signature parameter IF it has a value
            if (!empty($sign1)) {
                 $params_total['sign1'] = $sign1param;
            }
        } // End else for combined binding
    }

    // --- REMOVED old type=number parameter block --- //

    // ** 3. Handle 'myNum' (single strain view from a link) **
    elseif ($_GET['mode'] == 'myNum') {
        $myNum = isset($_GET['myNum']) ? $_GET['myNum'] : 0; // Check isset
        $params_total['myNum'] = (int) $myNum;
    }
    // ** 4. Handle 'myList', 'add3', or 'edit2' (strain list) **
    elseif ($_GET['mode'] == 'myList' || $_GET['mode'] == 'add3' || $_GET['mode'] == 'edit2') {
        // Two supported shapes here:
        // - explicit list of IDs (myList / edit2)
        // - numeric range (add3) exposed earlier via $minNum/$maxNum
        if (isset($list) && is_array($list) && isset($numberOfListParameters) && $numberOfListParameters > 0) {
            for($i = 1; $i <= $numberOfListParameters; $i++){
                if (isset($list[$i-1])) {
                    $params_total['list_' . $i] = (int) $list[$i-1];
                }
            }
        } elseif (isset($minNum) && $minNum !== '' && isset($maxNum) && $maxNum !== '') {
            // bind range for add3 when recent_add/minNum-maxNum were used to build the SQL above
            $params_total['minNum'] = (int)$minNum;
            $params_total['maxNum'] = (int)$maxNum;
        } elseif (isset($minNum) && $minNum !== '') {
            $params_total['minNum'] = (int)$minNum;
        } elseif (isset($maxNum) && $maxNum !== '') {
            $params_total['maxNum'] = (int)$maxNum;
        }
    }
    // ** 5. Handle 'pedigree' (PHP loop view) **
    elseif ($_GET['mode'] == 'pedigree') {
        // No parameters needed for $sql2 (uses temp table).
    }
    // ** 6. Handle 'descendants' (PHP loop view) **
    elseif ($_GET['mode'] == 'descendants') {
        // No parameters needed for $sql2 (uses temp table).
    }


	//Execute total statement
	// PDO::execute expects parameter array keys WITHOUT the leading colon.
	// Filter the params so we only pass keys that exist as named placeholders in the SQL.
	$placeholders_total = [];
	if (isset($stmtTotal) && is_object($stmtTotal)) {
	    $qs = $stmtTotal->queryString ?? ($sql2 ?? '');
	    preg_match_all('/:([a-zA-Z0-9_]+)/', $qs, $m);
	    $placeholders_total = $m[1] ?? [];
	}
	$exec_total_params = [];
	foreach ($params_total as $k => $v) {
	    if (in_array($k, $placeholders_total, true)) { $exec_total_params[$k] = $v; }
	}
	$stmtTotal->execute($exec_total_params);

    // Fetch total number of rows
    $total_records = $stmtTotal->fetchColumn();

    // Determine pagination variables
    $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 100; // Get limit from GET or POST
    if ($limit <= 0) $limit = 100; // Default limit
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1; // Get page from GET or POST
    if ($page <= 0) $page = 1; // Default page

    if ($total_records > 0) {
        $pages = ceil($total_records / $limit);
        if($page > $pages){ $page = $pages; }
        $startval = ($page - 1) * $limit;
    } else {
        $pages = 1;
	$startval = 0;
        $page = 1; // Ensure page is 1 if only one page
    }
    $_SESSION['last_search_page'] = $page; // Add this line

    // --- Dynamic Parameter Building for Limited Rows ($stmt) ---
    $params_limited = $params_total;

    // Add LIMIT parameters, but only if the SQL query contains the LIMIT placeholders.
    if (strpos($sql, ':startval') !== false && strpos($sql, ':limitval') !== false) {
        // Again: keys without leading colon
        $params_limited['startval'] = (int) $startval;
        $params_limited['limitval'] = (int) $limit;
    }

    // Execute statement (Limited)
    // Filter limited params to placeholders present in the limited query
    $placeholders_limited = [];
    if (isset($stmt) && is_object($stmt)) {
        $qs2 = $stmt->queryString ?? ($sql ?? '');
        preg_match_all('/:([a-zA-Z0-9_]+)/', $qs2, $m2);
        $placeholders_limited = $m2[1] ?? [];
    }
    $exec_limited_params = [];
    foreach ($params_limited as $k => $v) {
        if (in_array($k, $placeholders_limited, true)) { $exec_limited_params[$k] = $v; }
    }
    $stmt->execute($exec_limited_params);

    // Fetch results
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $showing_records = count($result);


	/* TODO Troubleshooting
	echo $sql;
	echo "<br>";
	echo $sql2;
	echo "<br>";
	print_r($params_total);
	echo "<br>";
	print_r($params_limited);
	echo "<br>";
	echo "startval: $startval";
	echo "<br>";
	echo "limitval: $limit";
	echo "<br>";
	echo "page: $page";
	echo "<br>";
	echo "pages: $pages";
	echo "<br>";
	echo "total_records: $total_records<br>";
	echo "total_records session: " . ($_SESSION['total_records'] ?? 'Not Set') . "<br>";
	print("list:<pre>".print_r($list ?? 'Not Set', true)."</pre>"); */

	$_SESSION['total_records'] = $total_records;

	// Set result text and plural ending
	if ($total_records == '1') {
		$message_records = 'only one';
		$plural = '';
	} else {
		$message_records = $total_records;
		$plural = 's';
	}

	// Print result text
	if ($total_records == '0') {
		$_SESSION['feedback_type'] = 'error';
		$_SESSION['feedback_message'] = 'Sorry, no matches. Try again.';
		return;
	} else {
		$helpmessage2 = "<br><span style='font-weight: bold; color: red;'>Tip:</span> To see the information about a donor or recipient, click the link in the corresponding cell. Use ctrl+click (cmd+click in Mac OS) to open in a new tab.";
	    // Always use the generic message
	    echo "Your search resulted in " . "<span style='font-weight: bold'>" . $total_records . "</span> strain" . $plural . ". ";

		//Multi page:
		if ($total_records > $limit) {
			echo "Showing <span style='font-weight: bold'> " . $showing_records . "</span> strains per page starting at no. <span style='font-weight: bold'>" . ($startval+1) . "</span>:<br>";

			echo "<br>Page <span style='font-weight: bold'>" . $page . "</span> of <span style='font-weight: bold'>" . $pages . "</span>. ";

			// Print pagination links
			for($n = 1; $n <= $pages; $n++) {

				// Combined search now always uses type=word for pagination
				if(isset($_GET['type']) && $_GET['type'] == 'word') {
					if ($n != $page){
						$link  = "?mode=search&amp;type=word"; // Always use type=word now
						$link .= "&amp;limit=" . urlencode($limit);
						$link .= "&amp;page=" . $n;
						echo ' <a href="' . $link . '">' . $n . '</a> ';
					} else {
						echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
					}
				}
				// --- REMOVED old type=number pagination block --- //
				elseif(isset($_GET['mode']) && $_GET['mode'] == 'pedigree'){
					if ($n != $page){
						echo ' <a href="?mode=pedigree&amp;strain=' . $start_strain . '&amp;limit=' . $limit . '&amp;page=' . $n . '">' . $n . '</a> ';
					} else {
						echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
					}
				}
				elseif(isset($_GET['mode']) && $_GET['mode'] == 'descendants'){
					if ($n != $page){
						echo ' <a href="?mode=descendants&amp;strain=' . $start_strain . '&amp;limit=' . $limit . '&amp;page=' . $n . '">' . $n . '</a> ';
					} else {
						echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
					}
				}
				// Added fallback for other modes like myList, add3, edit2 if they might be paginated
				// This assumes they don't need extra parameters in the pagination links
				elseif (isset($_GET['mode'])) {
				    if ($n != $page) {
				        $link = "?mode=" . urlencode($_GET['mode']);
				        if (isset($start_strain)) $link .= "&amp;strain=" . $start_strain; // Keep strain for pedigree/descendants
				        $link .= "&amp;limit=" . urlencode($limit);
				        $link .= "&amp;page=" . $n;
				        echo ' <a href="' . $link . '">' . $n . '</a> ';
				    } else {
				        echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
				    }
				}

			} // End for loop for pagination links
		} // End if ($total_records > $limit)
// ===== Show “N strains were saved” above the table when coming from add3
if (!empty($_SESSION['recent_add']) && is_array($_SESSION['recent_add'])) {
    $rc = (int)($_SESSION['recent_add']['count'] ?? 0);
    $rmin = (int)($_SESSION['recent_add']['min'] ?? 0);
    $rmax = (int)($_SESSION['recent_add']['max'] ?? 0);
    if ($rc > 0) {
        echo '<div class="msg success" style="margin:10px 0;padding:8px;border:1px solid #9ad18b;background:#eefaf0;border-radius:6px">';
        echo htmlspecialchars($rc . ($rc === 1 ? " strain was saved." : " strains were saved."), ENT_QUOTES, 'UTF-8');
        echo '</div>';
    }
    unset($_SESSION['recent_add']); // one-time banner
}
		// Print out a table of the resulting strains
		$csv_hdr = "";
		$csv_output = "";
		echo "<table class='sample'>";

			echo "<tr>";
				echo "<th>Strain</th>";
				echo "<th style='width: 300px'>Genotype</th>";
				echo "<th>Parental (Recipient)</th>";
				echo "<th>Donor</th>";
				echo "<th style='width: 300px'>Comment</th>";
				echo "<th>Signature</th>";
				echo "<th>Created</th>";
				echo "<th>Lineage</th>";
				echo "<th>";
					echo "Select";
					echo "<button class='display-none' id='check-all'>All</button>";
					echo "<button class='display-none' id='check-none'>None</button>";
				echo "</th>";
			echo "</tr>";

			echo "<form action='index.php?mode=myList' name='selectRow' method='post'>";
				echo "<input type='submit' title='Show a table of only the selected strains' name='show' value='Show selected'/>";

                                if (can('edit_strains')) {
                                        echo "<input type='submit' title='Edit the selected strains' name='edit' value='Edit selected'/>";
                                }

				echo "<input type='submit' title='Open the selected strains in a printer-friendly table' name='print' value='print selected'/>";
                echo "<input type='submit' title='Add selected strains to memory' name='store' value='Store in memory'/>";


				// Prepare CSV header
				$csv_hdr .= "Strain;Genotype;Recipient;Donor;Comment;Signature;Created\n";

				// Loop through all strains
				foreach ($result as $row) {

					// Convert html characters to printable
					$genotype = htmlspecialchars($row['Genotype']);
					$genotype1 = htmlspecialchars($row['Genotype']);
					$comment = htmlspecialchars($row['Comment']);
					$comment1 = htmlspecialchars($row['Comment']);

					// Display every match to the search word with colored text:
					// Genotype:
					if(isset($searchgenotype) && $searchgenotype){
						if($term1 != '') { $genotype = preg_replace('/' . preg_quote($term1, '/') . '/i', "¢$0¦", $genotype); }
						if($term2 != '') { $genotype = preg_replace('/' . preg_quote($term2, '/') . '/i', "¢$0¦", $genotype); }
						if($term3 != '') { $genotype = preg_replace('/' . preg_quote($term3, '/') . '/i', "¢$0¦", $genotype); }
						if($term4 != '') { $genotype = preg_replace('/' . preg_quote($term4, '/') . '/i', "¢$0¦", $genotype); }
						$genotype = preg_replace('/' . preg_quote('¢', '/') . '/i', "<span style='color: blue; font-weight: bold;'>", $genotype);
						$genotype = preg_replace('/' . preg_quote('¦', '/') . '/i', "</span>", $genotype);
					}

					// Comment:
					if(isset($searchcomment) && $searchcomment){
						if($term1 != '') { $comment = preg_replace('/' . preg_quote($term1, '/') . '/i', "¢$0¦", $comment); }
						if($term2 != '') { $comment = preg_replace('/' . preg_quote($term2, '/') . '/i', "¢$0¦", $comment); }
						if($term3 != '') { $comment = preg_replace('/' . preg_quote($term3, '/') . '/i', "¢$0¦", $comment); }
						if($term4 != '') { $comment = preg_replace('/' . preg_quote($term4, '/') . '/i', "¢$0¦", $comment); }
						$comment = preg_replace('/' . preg_quote('¢', '/') . '/i', "<span style='color: blue; font-weight: bold;'>", $comment);
						$comment = preg_replace('/' . preg_quote('¦', '/') . '/i', "</span>", $comment);
					}

					echo "<tr>";
						echo "<td>";
							echo 'DA' . $row['Strain'];
							$csv_output .= "DA" . $row['Strain'] . "; ";
						echo "</td>";

						echo "<td>";
							echo $genotype;
							$csv_output .= $genotype1 . "; ";
						echo "</td>";

						echo "<td align='right'>";
							// Only show DA prefix / link when Recipient is a positive integer
							$recipientId = (int)($row['Recipient'] ?? 0);
							if ($recipientId > 0) {
								echo "<a href='index.php?mode=myNum&myNum=" . $recipientId . "' target='_blank' title='View DA" . $recipientId . " in a new tab'>DA" . $recipientId . "</a>";
								$csv_output .= "DA" . $recipientId . "; ";
							} else {
								echo "";
								$csv_output .= "; ";
							}
						echo "</td>";

						echo "<td align='right'>";
							// Only show DA prefix / link when Donor is a positive integer
							$donorId = (int)($row['Donor'] ?? 0);
							if ($donorId > 0) {
								echo "<a href='index.php?mode=myNum&myNum=" . $donorId . "' target='_blank' title='View DA" . $donorId . " in a new tab'>DA" . $donorId . "</a>";
								$csv_output .= "DA" . $donorId . "; ";
							} else {
								echo "";
								$csv_output .= "; ";
							}
						echo "</td>";

						echo "<td>";
							echo $comment;
							$csv_output .= $comment1 . "; ";
						echo "</td>";

						echo "<td>";
							echo $row['Signature'];
							$csv_output .= $row['Signature'] . "; ";
						echo "</td>";

						echo "<td>";
							// Normalize sentinel timestamps to blank in both HTML and CSV
							$createdRaw = (string)($row['Created'] ?? '');
							if (in_array($createdRaw, $BLANK_CREATED, true) || $createdRaw === '') {
							    echo '';
							    $csv_output .= ";\n";
							} else {
							    echo htmlspecialchars($createdRaw, ENT_QUOTES, 'UTF-8');
							    $csv_output .= $createdRaw . "\n";
							}
						echo "</td>";
						echo "<td align='center'>";
						echo "<a href='index.php?mode=pedigree&amp;strain=" . $row['Strain'] . "' title='Show ancestors for DA" . $row['Strain'] . "'>Ancestors</a>";
						echo " / ";
						echo "<a href='index.php?mode=descendants&amp;strain=" . $row['Strain'] . "' title='Show descendants for DA" . $row['Strain'] . "'>Descendants</a>";
					echo "</td>";
						echo "<td>";
							echo "<div align=center><input class='strain-checkbox' type=checkbox name='selected[]' value=" . $row['Strain'] . "></div>";
						echo "</td>";
					echo "</tr>";
					//Stop making the table
				} // End foreach

			echo "</form>";
		echo "</table>";

		// Close the mysql connection
		if (isset($stmt)) $stmt->closeCursor(); // Check if stmt exists before closing
		if (isset($stmtTotal)) $stmtTotal->closeCursor(); // Check if stmtTotal exists

		// Bottom Pagination
		if ($total_records > $limit) {
			echo "<br>Page <span style='font-weight: bold'>" . $page . "</span> of <span style='font-weight: bold'>" . $pages . "</span>.";
			// Print pagination links
			for($n = 1; $n <= $pages; $n++) {
                // Combined search now always uses type=word for pagination
				if(isset($_GET['type']) && $_GET['type'] == 'word') {
					if ($n != $page){
						$link  = "?mode=search&amp;type=word"; // Always use type=word now
						$link .= "&amp;limit=" . urlencode($limit);
						$link .= "&amp;page=" . $n;
						echo ' <a href="' . $link . '">' . $n . '</a> ';
					} else {
						echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
					}
				}
				// --- REMOVED old type=number pagination block --- //
				elseif(isset($_GET['mode']) && $_GET['mode'] == 'pedigree'){
				    $start_strain = isset($_GET['strain']) ? $_GET['strain'] : 0; // Ensure start_strain exists
					if ($n != $page){
						echo ' <a href="?mode=pedigree&amp;strain=' . $start_strain . '&amp;limit=' . $limit . '&amp;page=' . $n . '">' . $n . '</a> ';
					} else {
						echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
					}
				}
				elseif(isset($_GET['mode']) && $_GET['mode'] == 'descendants'){
				    $start_strain = isset($_GET['strain']) ? $_GET['strain'] : 0; // Ensure start_strain exists
					if ($n != $page){
						echo ' <a href="?mode=descendants&amp;strain=' . $start_strain . '&amp;limit=' . $limit . '&amp;page=' . $n . '">' . $n . '</a> ';
					} else {
						echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
					}
				}
				// Added fallback for other modes like myList, add3, edit2
				elseif (isset($_GET['mode'])) {
				    if ($n != $page) {
				        $link = "?mode=" . urlencode($_GET['mode']);
				        // No need for strain in these modes
				        $link .= "&amp;limit=" . urlencode($limit);
				        $link .= "&amp;page=" . $n;
				        echo ' <a href="' . $link . '">' . $n . '</a> ';
				    } else {
				        echo " <span style='font-weight: bold; color: blue;'>" . $n . "</span> ";
				    }
				}

			} // End for loop
		} // End if ($total_records > $limit)


		echo '<form name="export" action="export.php" method="post">';
			echo '<input type="submit" value="Export table to CSV">';
			echo '<input type="hidden" value="' . htmlspecialchars($csv_hdr) . '" name="csv_hdr">'; // Use htmlspecialchars for hidden fields
			echo '<input type="hidden" value="' . htmlspecialchars($csv_output) . '" name="csv_output">'; // Use htmlspecialchars for hidden fields
		echo "</form>";

	} // <-- This brace closes the 'else' for 'if ($total_records == '0')'

} else { // <-- This 'else' pairs with 'if($sql && $sql2){'
	if(isset($transaction_fail) && $transaction_fail){ // Check isset
		echo "<span style='color: red'><strong>Something went wrong when talking to the database!</strong></span><br>";
	} elseif (isset($_POST['isPosted'])) { // Only show message if form was actually posted
		// Use the specific message echo'd earlier if criteriaFound was false
	    // echo "<span style='color: red'><strong>No search criteria entered or no results found.</strong></span><br>";
	} // Otherwise, no message needed (e.g., initial page load, or non-search modes that resulted in $sql=null)
}
?>
