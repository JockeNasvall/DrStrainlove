<?php
/** 
 * File: edit.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */


require_once __DIR__ . '/permissions.php';


// Check user rights
if (can('edit_strains')) {

	$sql = null; // Initialize
	$sql2 = null;
	$list = null;
	$total_records = 0;
	$message = ""; // Initialize message

	// Check the mode (should be myList when coming from the results table)
	if (isset($_GET['mode']) && $_GET['mode'] == 'myList') {
	    // Get the prepared list (entire memory + new selections) from the session
		$list = $_SESSION['action_strain_list'] ?? null; // Get list from session

		if(isset($list) && is_array($list) && count($list) > 0){ // Check if list is a non-empty array
			// Set help message
			$helpmessage = "Before you press the save button, please verify that you changed the strain information correctly. To update the timestamp in the \"Created\" column, click the checkbox.";

			// Prepare parameters for strains in list
			$listInQuery = array();
			for($i = 1; $i <= count($list); $i++){
				$listInQuery[$i] = ":list_" . $i;
			}
			$listInQuery = implode(", ", $listInQuery);
			$numberOfListParameters = count($list);

			$message = "Editing the following "; // Specific message for edit mode
			$sql = "SELECT * FROM strains WHERE Strain IN (" . $listInQuery . ") ORDER BY Strain ASC"; // Added ORDER BY
			$sql2 = "SELECT COUNT(Strain) FROM strains WHERE Strain IN (" . $listInQuery . ")";

		} else {
			$helpmessage = "No strains stored in memory to edit.";
			// $sql, $sql2 remain null
		}
	} else {
	    // Handle cases where mode is not myList
	    $helpmessage = "Incorrect mode for editing selected strains.";
	    // $sql, $sql2 remain null
	}

	// Proceed only if SQL queries were successfully built
	if($sql && $sql2){
		// Prepare PDO statements
		$stmt = $dbh->prepare($sql);
		$stmtTotal = $dbh->prepare($sql2);

		// Bind parameters
		if (isset($numberOfListParameters) && $numberOfListParameters > 0) {
		    for($i = 1; $i <= $numberOfListParameters; $i++){
		        // Check list index exists before binding
		        if (isset($list[$i-1])) {
    		        $stmt->bindParam(":list_" . $i, $list[$i-1], PDO::PARAM_INT);
    		        $stmtTotal->bindParam(":list_" . $i, $list[$i-1], PDO::PARAM_INT);
		        } else {
		             echo "<span style='color: red'>Error binding parameters.</span>";
		             $sql = null; // Prevent execution
		             $sql2 = null;
		             break;
		        }
		    }
		} else {
		     // Should not happen if count > 0 check passed, but safeguard
		     $sql = null;
		     $sql2 = null;
		}
	}

    // Proceed only if SQL is still valid
	if($sql && $sql2){
		// Execute statements
		$stmt->execute();
		$stmtTotal->execute();

		// Fetch total number of rows
		$total_records = $stmtTotal->fetchColumn();

		// Fetch results
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$showing_records = count($result); // Should match $total_records if no LIMIT

        if ($total_records > 0) {
    		// Make a nice table:
    		echo $message . "<strong>" . $total_records . "</strong> strain" . ($total_records == 1 ? '' : 's') . " from memory:";
    		echo "<table class='sample' border='1'><br>"; // Consider removing border='1' if CSS handles it

    			// Print out a table of the resulting strains:
    			echo "<thead><tr>"; // Add thead for structure
    				echo "<th>Strain</th>";
    				echo "<th width='300px'>Genotype</th>";
    				echo "<th>Parents</th>";
    				echo "<th width='300px'>Comment</th>";
    				echo "<th>Signature</th>";
    				echo "<th>Created</th>";
    			echo "</tr></thead>";
                echo "<tbody>"; // Add tbody

    			echo "<form name='strainstoupdate' method='post' action='index.php?mode=edit2'>";
    				// Preserve debug flag across POST so redirects can include it
    				$__debug_val = htmlspecialchars($_REQUEST['debug'] ?? '', ENT_QUOTES, 'UTF-8');
    				echo "<input type='hidden' name='debug' value='" . $__debug_val . "'>";

    				echo "<input type='hidden' name='form-type' id='form-type' value='edit'>";
    				echo "<input type='submit' title='Save the changes' name='save' value='save'>";

    				$strainIndex = 0;

    				// Loop through all strains
    				foreach ($result as $row) {
    					$genotype = htmlspecialchars($row['Genotype']);
    					$comment = htmlspecialchars($row['Comment']);

    					echo "<tr>";
    						echo "<td>";
    							echo 'DA' . $row['Strain'];
    							echo "<input type='hidden' name='Strain[" . $strainIndex . "]' value='" . $row['Strain'] . "'>";
    						echo "</td>";

    						echo "<td>";
    							echo $genotype . "<br><br>";
    							echo "<textarea type='text' rows=3 cols=40 name='Genotype[" . $strainIndex . "]'>" . $row['Genotype'] . "</textarea>\n";
    						echo "</td>";

    						echo "<td>";
    						// Parental (Recipient)
    						echo "<div>Parental</br>(Recipient):</div>";
    						$recipientId = (int)($row['Recipient'] ?? 0);
    						if ($recipientId > 0) {
    							echo "<a href='index.php?mode=myNum&myNum=" . $recipientId . "' title='View DA" . $recipientId . " in a new tab'>DA" . $recipientId . "</a><br>";
    							echo "<input type='text' size=5 name='Recipient[" . $strainIndex . "]' value='" . $recipientId . "'>\n";
    						} else {
    							echo "<input type='text' size=5 name='Recipient[" . $strainIndex . "]' value=''>\n";
    						}
    						echo "<br><br>";
    						// Donor
    						echo "<div>Donor:</div>";
    						$donorId = (int)($row['Donor'] ?? 0);
    						if ($donorId > 0) {
    							echo "<a href='index.php?mode=myNum&myNum=" . $donorId . "' title='View DA" . $donorId . " in a new tab'>DA" . $donorId . "</a><br>";
    							echo "<input type='text' size=5 name='Donor[" . $strainIndex . "]' value='" . $donorId . "'>\n";
    						} else {
    							echo "<input type='text' size=5 name='Donor[" . $strainIndex . "]' value=''>\n<br><br>";
    						}
    						echo "</td>";

    						echo "<td>";
    							echo $comment . "<br><br>";
    							echo "<textarea type='text' rows=3 cols=40 name='Comment[" . $strainIndex . "]'>" . $row['Comment'] . "</textarea>\n";
    						echo "</td>";

    						echo "<td>";
    							if($row['Signature'] == '') {
    								echo "";
    								echo "<input type='text' size='3' name='Signature[" . $strainIndex . "]' value='" . ($_SESSION['Signature'] ?? '') . "'>";
    							} else {
    								echo htmlspecialchars($row['Signature']); // Sanitize display
    								echo "<input type='text' size='3' name='Signature[" . $strainIndex . "]' value='" . htmlspecialchars($row['Signature']) . "'>"; // Sanitize value
    							}
    						echo "</td>";

    						echo "<td>";
    							if ($row['Created'] == "0000-00-00 00:00:00") {
    								echo "";
    							} else {
    								echo $row['Created'] . "<br>";
    							}
    							echo "Update<input type='checkbox' size='3' name='Created[" . $strainIndex . "]' value='" . $today . "'>";
    							echo "<input type='hidden' name='CreatedDate[" . $strainIndex . "]' value='" . $row['Created'] . "'>";
    						echo "</td>";
    					echo "</tr>";
    					//Stop making the table

    					$strainIndex++;
    				} // End foreach ($result as $row)
                echo "</tbody>"; // End tbody
    			echo "</form>";
    		echo "</table>";

    		// Close the mysql connection
    		if (isset($stmt)) $stmt->closeCursor();
    		if (isset($stmtTotal)) $stmtTotal->closeCursor();
        } else {
             // This case means the list from session was valid, but query returned 0 rows (unlikely but possible)
             echo "<span style='color: orange; font-weight: bold;'>Strains found in memory, but no matching records in database.</span>";
        }

	} elseif (isset($_GET['mode']) && $_GET['mode'] == 'myList' && isset($list) && count($list) == 0) {
	    // Message for empty list already shown
	} elseif (!isset($_GET['mode']) || $_GET['mode'] != 'myList') {
        // Message for incorrect mode already shown
	} else {
	    // Generic fallback
	    echo "<span style='color: red; font-weight: bold;'>Could not load strains for editing.</span>";
	}

} // End if($_SESSION['Usertype'] == 'Superuser')
else {
	echo "<span style='color: red; font-weight: bold;'>You are not trusted to edit strains!</span>";
}
?>
