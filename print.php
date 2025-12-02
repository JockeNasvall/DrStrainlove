<?php
/** 
 * File: print.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */


$sql = null; // Initialize sql to null
$list = array(); // Initialize list as empty array
$message = ""; // Initialize message

// Check if we are in the correct mode (set by index.php after actions.php runs)
if (isset($_GET['mode']) && $_GET['mode'] == 'myList') {
    // Get the prepared list (entire memory + new selections) from the session
	$list = $_SESSION['action_strain_list'] ?? array();

	if(count($list) == 0) {
		echo "No strains stored in memory to print.<br>"; // Corrected message
		// $sql remains null
	} else {
	    $message = "Printing the following "; // Set a base message
		// Prepare parameters for strains in list
		$listInQuery = array();
		for($i = 1; $i <= count($list); $i++){
			$listInQuery[$i] = ":list_" . $i;
		}
		$listInQuery = implode(", ", $listInQuery);
		$numberOfListParameters = count($list);

		// Build the SQL query - no LIMIT needed for printing all
		$sql = "SELECT * FROM strains WHERE Strain IN (" . $listInQuery . ") ORDER BY Strain ASC";
	}
} else {
    // If mode is not myList, don't execute SQL
    echo "Incorrect mode for printing selected strains.<br>";
    // $sql remains null
}

// Do the actual mysql query only if SQL was built
if($sql){
	// Prepare PDO statement
	$stmt = $dbh->prepare($sql);

	// Bind parameters (only if $list is not empty)
	if(isset($numberOfListParameters) && $numberOfListParameters > 0){
		for($i = 1; $i <= $numberOfListParameters; $i++){
		    // Check list index exists before binding
		    if (isset($list[$i-1])) {
			    $stmt->bindParam(":list_" . $i, $list[$i-1], PDO::PARAM_INT); // Explicitly type hint INT
		    } else {
		        // This case should ideally not happen if numberOfListParameters is correct
		        echo "<span style='color: red'>Error binding parameters.</span>";
		        $sql = null; // Prevent execution on error
		        break;
		    }
		}
	} else {
	    // Skip execution if parameters couldn't be bound (redundant check)
	    $sql = null;
	}
}

// Proceed only if SQL is still valid after binding check
if($sql){
	// Execute statement
	$stmt->execute();

	// Check how many rows were found
	$rows_found = $stmt->rowCount();

	if ($rows_found == '0') {
		echo "<span style='color: red'>Sorry, no matches found for the strains in memory.</span>"; // More specific message
	}
	else {
		// Set pluralization for message
		$plural = ($rows_found == '1') ? '' : 's';

		// Fetch results
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Display Print button and message
		echo "<div class='noPrint'>" . $message . "<strong>" . $rows_found . "</strong> strain" . $plural . " from memory:<br>";
			echo "<input type='button' value='Print' onclick='window.print()'>";
		echo "</div><br>";

		// Display Table
		echo "<div id='landscape'>"; // Assumes print.css handles landscape layout
			echo "<table class='sample' style='width:940px'><br>"; // Consider removing inline style if CSS handles it
				echo "<thead>";
					echo "<tr>";
						echo "<th>Strain</th>";
						echo "<th>Genotype</th>";
						echo "<th>Recipient</th>";
						echo "<th>Donor</th>";
						echo "<th>Comment</th>";
						echo "<th>Signature</th>";
					echo "</tr>";
				echo "</thead>";
                echo "<tbody>"; // Add tbody for better structure

				foreach($result as $row){
					$genotype = htmlspecialchars($row['Genotype']);
					$comment = htmlspecialchars($row['Comment']);

					echo "<tr>";
						echo "<td>";
							echo 'DA' . $row['Strain'];
						echo "</td>";

						echo "<td>";
							echo $genotype;
						echo "</td>";

						echo "<td align='right'>";
							$recipientId = (int)($row['Recipient'] ?? 0);
							echo ($recipientId > 0) ? "DA" . $recipientId : "";
						echo "</td>";

						echo "<td align='right'>";
							$donorId = (int)($row['Donor'] ?? 0);
							echo ($donorId > 0) ? "DA" . $donorId : "";
						echo "</td>";

						echo "<td>";
							echo $comment;
						echo "</td>";

						echo "<td>";
							echo htmlspecialchars($row['Signature']); // Sanitize signature too
						echo "</td>";
						
						echo "<td>";
                            $createdRaw = (string)($row['Created'] ?? '');
                            // treat 0000-00-00 00:00:00 and 1970-01-01 01:00:01 as blank
                            if (in_array($createdRaw, ['0000-00-00 00:00:00', '1970-01-01 01:00:01'], true)) {
                                echo '';
                            } else {
                                echo htmlspecialchars($createdRaw, ENT_QUOTES, 'UTF-8');
                            }
						echo "</td>";
					echo "</tr>";
				} // END foreach

                echo "</tbody>";
			echo "</table>";
		echo "</div>"; // End landscape div

        // Close cursor
        $stmt->closeCursor();

	} // End else (rows_found > 0)
} elseif (!isset($_GET['mode']) || $_GET['mode'] != 'myList') {
    // Message for incorrect mode was already printed
} elseif (isset($list) && count($list) == 0) {
     // Message for empty list was already printed
} elseif (!isset($stmt)) {
     // Message for parameter binding error was already printed
} else {
    // Generic fallback error if SQL failed for other reasons
    echo "<span style='color: red'>Could not generate print view.</span>";
}

?>
