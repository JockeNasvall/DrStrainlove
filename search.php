<?PHP
// Limit the number of entries shown in the table:
if(isset($_GET['limit'])){
	$limit = $_GET['limit'];
}
elseif(isset($_POST['limit'])) {
	$limit = $_POST['limit'];
}
elseif(isset($_SESSION['limit'])) {
	$limit = $_SESSION['limit'];
}
else {
	$limit = 100;
}

if (isset($_GET['page'])) {
	$page = ($_GET['page']);
} else {
	$page = 1;
	$startval = 0;
}
// Calculate startval based on page and limit, even if page comes from GET
$startval = ($page - 1) * $limit;

// Use session values as defaults. index.php now manages REQUEST → SESSION bridging,
// so this file simply reads the session for pre-filled form values.
$minNum = $_SESSION['minNum'] ?? '';
$maxNum = $_SESSION['maxNum'] ?? '';
$sign1  = $_SESSION['sign1']  ?? '';
$term1  = $_SESSION['term1']  ?? '';
$term2  = $_SESSION['term2']  ?? '';
$term3  = $_SESSION['term3']  ?? '';
$term4  = $_SESSION['term4']  ?? '';
$notterm1 = $_SESSION['notterm1'] ?? '';
$notterm2 = $_SESSION['notterm2'] ?? '';
$notterm3 = $_SESSION['notterm3'] ?? '';
$notterm4 = $_SESSION['notterm4'] ?? '';
$searchgenotype = $_SESSION['searchgenotype'] ?? 1;
$searchcomment  = $_SESSION['searchcomment'] ?? 0;


// Determine checkbox states based on variables
$check1 = $searchgenotype ? 'checked="checked"' : '';
$check2 = $searchcomment ? 'checked="checked"' : '';

// Set help message
$helpmessage = "Enter keywords and/or a strain number range. Use % as a wildcard. Search criteria are remembered until you click 'Reset'.";

?>
<form name="search" id="search" action="index.php?mode=search&amp;type=word" method="POST">

		<p>Search in:</p>
		<label for="checkboxGenotype"><input type="checkbox" name="check[genotype]" id="checkboxGenotype" title="Select which fields to search in" value="genotype" <?php
require_once __DIR__ . '/lib/input_sanitize.php';
 

/* __SEARCH_ERRORS_BLOCK__ */
$__search_errors = [];
$__min = sanitize_num6($_GET['minNum'] ?? null);
$__max = sanitize_num6($_GET['maxNum'] ?? null);
$__limit = sanitize_num6($_GET['limit'] ?? null);
if (isset($_GET['minNum']) && $__min === null && ($_GET['minNum'] !== '')) $__search_errors[] = "Strain # (min) must be 1–6 digits.";
if (isset($_GET['maxNum']) && $__max === null && ($_GET['maxNum'] !== '')) $__search_errors[] = "Strain # (max) must be 1–6 digits.";
if ($__min !== null && $__max !== null && $__min > $__max) $__search_errors[] = "Strain # range is invalid: min must be ≤ max.";
if (isset($_GET['limit']) && $__limit === null && ($_GET['limit'] !== '')) $__search_errors[] = "Limit must be 1–6 digits.";
if ($__limit !== null && $__limit > 1000000) $__search_errors[] = "Limit too large; please enter ≤ 1000000.";
echo ($check1); ?>>Genotype</label><br>
		<label for="checkboxComment"><input type="checkbox" name="check[comment]" id="checkboxComment" title="Select which fields to search in" value="comment" <?php echo ($check2); ?>>Comment</label><br>
		<br>


		<table width="20" border="0">
			<tr>
				<td colspan="4">Include Keywords:</td>
			</tr>
			<tr>
				<td><input type="text" title="Tip: use % as wildcard" name="term1" value="<?php echo htmlspecialchars($term1); ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="term2" value="<?php echo htmlspecialchars($term2); ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="term3" value="<?php echo htmlspecialchars($term3); ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="term4" value="<?php echo htmlspecialchars($term4); ?>"></td>
			</tr>

			<tr>
				<td colspan="4">Exclude Keywords:</td>
			</tr>
			<tr>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm1" value="<?php echo htmlspecialchars($notterm1); ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm2" value="<?php echo htmlspecialchars($notterm2); ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm3" value="<?php echo htmlspecialchars($notterm3); ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm4" value="<?php echo htmlspecialchars($notterm4); ?>"></td>
			</tr>
            <tr>
                <td colspan="2">And Strain Number Between:</td>
                 <td colspan="2">And:</td>
           </tr>
           <tr>
  <td colspan="2">
    <input
      type="text"
      name="minNum"
      size="6"
      maxlength="6"
      value="<?php echo htmlspecialchars($minNum); ?>"
      inputmode="numeric">
  </td>
  <td colspan="2">
    <input
      type="text"
      name="maxNum"
      size="6"
      maxlength="6"
      value="<?php echo htmlspecialchars($maxNum); ?>"
      inputmode="numeric">
  </td>
</tr>

			<tr>
				<td colspan="2">Limit to strains frozen by:</td>
				<td colspan="2">Show:</td>
			</tr>
			<tr>
				<td colspan="2">
					<input type="text" name="sign1" value="<?php echo htmlspecialchars($sign1); ?>">
				</td>
				<td colspan="2">
					<!-- Results limit -->
					<input type="text" name="limit" size="4" maxlength="6"
       						value="<?php echo htmlspecialchars($limit ?? ''); ?>"
       						pattern="^\d{1,6}$" inputmode="numeric">
					<input type="hidden" name="startval" value="<?php echo htmlspecialchars($startval); ?>">
					<input type="hidden" name="page" value="1">
				</td>
			</tr>

			<tr>
				<td colspan="4">
				    <input type="hidden" name="isPosted" value="TRUE">
		        	<input type='hidden' name='form-type' id='form-type' value="search">
				    <input type="submit" name="search-text" value="Search">
					<input type="submit" title="Reset all fields and clear the results table" name="reset-word" value="Reset">
					</td>
			</tr>
		</table>

</form>

<div style="margin-top: 15px; padding: 10px; border: 1px dashed #ccc;">
    <?php
        $stored_count = isset($_SESSION['stored_strains']) ? count($_SESSION['stored_strains']) : 0;
        echo "Strains currently in memory: <strong>" . $stored_count . "</strong>.";
        if ($stored_count > 0) {
            // Display the Clear Memory button only if there are stored strains
            echo '<form style="display: inline; margin-left: 20px;" name="clear_memory_form" action="index.php?mode=search&type=word" method="POST">';
            echo '<input type="hidden" name="form-type" value="search">'; // Keep consistent form type if needed by actions.php
            echo '<input type="submit" title="Clear all strains stored in memory" name="clear_memory" value="Clear memory">';
            echo '</form>';
        }
    ?>
</div>
