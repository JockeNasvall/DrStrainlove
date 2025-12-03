<?php
/** 
 * File: insert.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */


require_once __DIR__ . '/permissions.php';


// Only allow insert if the user can add strains
if (can('add_strains')) {

	$helpmessage = "Genotype field can not be empty.";

	// Preserve debug flag (if present in URL) so subsequent POST+redirects can retain debug mode.
	$__debug_val = htmlspecialchars($_REQUEST['debug'] ?? '', ENT_QUOTES, 'UTF-8');
	echo '<form action="index.php?mode=add3" name="frmAdd" method="post">';
	echo '<input type="hidden" name="debug" value="' . $__debug_val . '">';
		echo "<p>";
			echo "Number of strains to add: ";
			echo '<select class="droplist" name="menu1">';

				// Determine the current number of lines, default to 1
				$current_line_count = isset($_GET["Line"]) ? (int)$_GET["Line"] : (isset($_SESSION["Line"]) ? (int)$_SESSION["Line"] : 1);
				if ($current_line_count <= 0) {
					$current_line_count = 1;
				}

				for($i = 1; $i <= 50; $i++) {
					// Check against the determined current line count
					if($current_line_count == $i){
						$sel = "selected";
					} else {
						$sel = "";
					}

					echo '<option value="' . $i . '" ' . $sel . '>' . $i . '</option>';
				}

			echo "</select>";
			echo '<input type="submit" name="update_lines" value="Update">';
		echo "</p>";
		echo "<input type='hidden' name='form-type' id='form-type' value='add'>";
?>
<p Title='Move the mouse pointer over the field you need help with.'>For help, please see the mouseover tooltips and read the <a href="index.php?mode=guidelines" target="_blank">Guidelines</a>.</br></br>
	<?php
		// Per-field failure details are kept in $_SESSION['saveFail'].
		// The page-wide banner is rendered centrally by index.php from
		// $_SESSION['feedback_message'] / feedback_type so we don't echo it here.
		$failedRows = !empty($_SESSION['saveFail']) && is_array($_SESSION['saveFail']) ? $_SESSION['saveFail'] : [];

		// Print table header
		echo "<table class='insert'>";
			echo "<tr>";
				echo "<th>Genotype</th>";
				echo "<th></th>";
				echo "<th>Comment</th>";
			echo "</tr>";

			// Use the determined line count
			$line = $current_line_count;
			$_SESSION["Line"] = $line;

			// Loop through all rows
			for($i = 1; $i <= $line; $i++){
				$failClass = array(); // Initialize $failClass for this row

				// Specify classes for failed fields
				if(array_key_exists($i, $failedRows)){
					if(!$failedRows[$i]['Genotype']){
						$failClass['genotype'] = "validation-fail";
					}

					if(!$failedRows[$i]['Donor']){
						$failClass['donor'] = "validation-fail";
					}

					if(!$failedRows[$i]['Recipient']){
						$failClass['recipient'] = "validation-fail";
					}
				}

				// Print input fields
				echo "<tr id='line-" . $i . "'>";
					echo "<td>";
						echo '<textarea class="' . ($failClass['genotype'] ?? '') . '" Title="An indication of species, strain or isolate, followed by a complete list of mutations compared to the wildtype strain. Use correct genetic nomenclature, and be consistent." rows="5" cols="45" type="text" style="font-size: 14px;" wrap=soft name="txtGenotype[' . $i . '] ">';
							echo $_SESSION["txtGenotype"][$i] ?? '';
						echo "</textarea>";
					echo "</td>";

					echo "<td>";
						echo "<table>";

							echo "<tr>";
								echo "<td align='center'>";
									echo "<div>Parental</br>(Recipient):</div>";
									echo '<input  class="' . ($failClass['recipient'] ?? '') . '" Title="This is the parental strain used in your construction or the recipient strain used in transduction, transformation or conjugation." type="text" name="txtRecipient[' . $i . ']" size="5" value="' . ($_SESSION["txtRecipient"][$i] ?? '') . '" />';
								echo "</td>";
							echo "</tr>";
							echo "<tr>";
								echo "<td align='center'>";
									echo "<div>Donor:</div>";
									echo '<input  class="' . ($failClass['donor'] ?? '') . '"   Title="This is only used for strain used as source of any DNA that has been transferred into the parental strain. Leave this field empty if no DNA has been transferred from another strain." type="text" name="txtDonor[' . $i . ']" size="5" value="' . ($_SESSION["txtDonor"][$i] ?? '') . '" />';
								echo "</td>";
							echo "</tr>";
						echo "</table>";
					echo '</td>';

					echo "<td>";
						echo '<textarea Title="Everything that does not go into any of the other fields goes here. Should indicate if transduction, conjugation, (linear) transformation or direct selection/experimental evolution was used to construct the strain. If the strain came from another lab, this should be indicated here, including references if possible." rows="5" cols="45" type="text" style="font-size: 14px;" wrap=soft name="txtComment[' . $i . ']">';
							echo $_SESSION["txtComment"][$i] ?? '';
						echo "</textarea>";
					echo "</td>";
				echo "</tr>";
			}
		echo "</table>";

		echo "<p>These will be added under the signature <strong>" . $_SESSION['Signature'] . "</strong>.</p>";
		echo '<input type="hidden" name="txtSignature" value="' . $_SESSION['Signature'] . '">';
		echo "<br />";

		echo '<input type="submit" class="savebutton" name="submit" value="Save">';
		echo '<input type="submit" title="Reset all fields and clear memory" name="reset" value="Clear fields">';
	echo "</form>";
}
else {
	echo "<span style='color: red; font-weight: bold;'>You are not trusted to add new strains!</span>";
}
?>
