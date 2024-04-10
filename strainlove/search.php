/*****************************************************************************
 *
 * MIT License
 *
 * Copyright (c) 2024 Joakim Nasvall
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *******************************************************************************/


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
}

// Set variables for min and max number as well as signature, in order Get, Post, Session.
if($_GET['search'] == 1){
	$minNum = $_GET['minNum'];
	$maxNum = $_GET['maxNum'];
	$sign1 = $_GET['sign1'];

	$term1 = $_GET['term1'];
	$term2 = $_GET['term2'];
	$term3 = $_GET['term3'];
	$term4 = $_GET['term4'];

	$notterm1 = $_GET['notterm1'];
	$notterm2 = $_GET['notterm2'];
	$notterm3 = $_GET['notterm3'];
	$notterm4 = $_GET['notterm4'];

	$searchgenotype = $_GET['searchgenotype'];
	$searchcomment  = $_GET['searchcomment'];

	if(!isset($_GET['searchgenotype']) && !isset($_GET['searchcomment'])){
		$searchgenotype = 1;
	}

} else {
	if(isset($_POST['minNum'])) {
		$minNum = $_POST['minNum'];
	}
	else {
		$minNum = $_SESSION['minNum'];
	}

	if(isset($_POST['maxNum'])) {
		$maxNum = $_POST['maxNum'];
	}
	else {
		$maxNum = $_SESSION['maxNum'];
	}

	if(isset($_POST['sign1'])) {
		$sign1 = $_POST['sign1'];
	}
	else {
		$sign1 = $_SESSION['sign1'];
	}

	// Terms
	if(isset($_POST['term1'])) {
		$term1 = $_POST['term1'];
	}
	else {
		$term1 = $_SESSION['term1'];
	}

	if(isset($_POST['term2'])) {
		$term2 = $_POST['term2'];
	}
	else {
		$term2 = $_SESSION['term2'];
	}

	if(isset($_POST['term3'])) {
		$term3 = $_POST['term3'];
	}
	else {
		$term3 = $_SESSION['term3'];
	}

	if(isset($_POST['term4'])) {
		$term4 = $_POST['term4'];
	}
	else {
		$term4 = $_SESSION['term4'];
	}

	// Not-terms
	if(isset($_POST['notterm1'])) {
		$notterm1 = $_POST['notterm1'];
	}
	else {
		$notterm1 = $_SESSION['notterm1'];
	}

	if(isset($_POST['notterm2'])) {
		$notterm2 = $_POST['notterm2'];
	}
	else {
		$notterm2 = $_SESSION['notterm2'];
	}

	if(isset($_POST['notterm3'])) {
		$notterm3 = $_POST['notterm3'];
	}
	else {
		$notterm3 = $_SESSION['notterm3'];
	}

	if(isset($_POST['notterm4'])) {
		$notterm4 = $_POST['notterm4'];
	}
	else {
		$notterm4 = $_SESSION['notterm4'];
	}

	// Checkboxes
	if(isset($_POST['check']['genotype'])){
		$searchgenotype = 1;
	}
	else {
		$searchgenotype = $_SESSION['searchgenotype'];
	}

	if(isset($_POST['check']['comment'])){
		$searchcomment = 1;
	}
	else {
		$searchcomment = $_SESSION['searchcomment'];
	}
}

?>
<form name="search" id="search" action="index.php?mode=search&amp;type=<?php echo $_GET['type'];?>" method="POST">

	<?php

	//Are we in the 'Search' tab?
	if($_GET['type'] == 'word' || !isset($_GET['type'])) {

		// Set Genotype as default or remember the status of the checkboxes in the previous search:
		$helpmessage = "Select which field(s) to search in and type a keyword in at least one of the input boxes.<br>When you click 'Search', your keywords are remembered until you click 'Reset'.";

		if($searchgenotype && !$searchcomment) {
			$check1 = 'checked="checked"';
			$check2 = '';
		}
		elseif($searchgenotype && $searchcomment) {
			$check1 = 'checked="checked"';
			$check2 = 'checked="checked"';
		}
		elseif(!$searchgenotype && $searchcomment) {
			$check1 = '';
			$check2 = 'checked="checked"';
		} else {
			$check1 = 'checked="checked"';
			$check2 = '';
		}
		?>

		<p>Search in:</p>
		<label for="checkboxGenotype"><input type="checkbox" name="check[genotype]" id="checkboxGenotype" title="Select which fields to search in" value="genotype" <?php echo ($check1); ?>>Genotype</label><br>
		<label for="checkboxComment"><input type="checkbox" name="check[comment]" id="checkboxComment" title="Select which fields to search in" value="comment" <?php echo ($check2); ?>>Comment</label><br>
		<br>


		<table width="20" border="0">
			<tr>
				<td colspan="4">Include:</td>
			</tr>
			<tr>
				<td><input type="text" title="Tip: use % as wildcard" name="term1" value="<?php echo $term1; ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="term2" value="<?php echo $term2; ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="term3" value="<?php echo $term3; ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="term4" value="<?php echo $term4; ?>"></td>
			</tr>

			<tr>
				<td colspan="4">Exclude:</td>
			</tr>
			<tr>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm1" value="<?php echo $notterm1; ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm2" value="<?php echo $notterm2; ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm3" value="<?php echo $notterm3; ?>"></td>
				<td><input type="text" title="Tip: use % as wildcard" name="notterm4" value="<?php echo $notterm4; ?>"></td>
			</tr>
			<tr>
				<td colspan="2">Limit to strains frozen by:</td>
				<td colspan="2">Show:</td>
			</tr>
			<tr>
				<td colspan="2">
					<input type="text" name="sign1" value="<?php echo $sign1; ?>">
				</td>
				<td colspan="2">
					<input type="text" name="limit" size=4 maxlength="5" value="<?php echo $limit; ?>">
					<input type="hidden" name="startval" value="<?php echo $startval; ?>">
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

	<?php
	}

	//Are we in the 'List' tab?
	if($_GET['type'] == 'number' or $_GET['type'] == 'advanced') {
		$helpmessage = "To list a range of strain numbers, use both of the input boxes. To find a single strain, use only one of the input boxes.<br>When you click 'Search', the values are remembered until you click 'Reset'.";
	?>
		<p>List strain numbers:</p>

	    <table width="20" border="0">
			<tr>
				<td colspan="2">Between:</td>
			</tr>
	    	<tr>
	    		<td colspan="2"><input type="text" name="minNum" maxlength="5" value="<?php echo $minNum; ?>"></td>
	    	</tr>
			<tr>
				<td colspan="2">And:</td>
			</tr>
	    	<tr>
	    		<td colspan="2"><input type="text" name="maxNum" maxlength="5" value="<?php echo $maxNum; ?>"></td>
	    	</tr>
			<tr>
				<td>Limit to strains frozen by:</td>
				<td>Show:</td>
			</tr>
			<tr>
				<td><input type="text" name="sign1" value="<?php echo $sign1; ?>"></td>
				<td>
					<input type="text" name="limit" size=4 maxlength="5" value="<?php echo $limit; ?>">
					<input type="hidden" name="startval" value="<?php echo $startval; ?>">
					<input type="hidden" name="page" value="1">
				</td>
			</tr>

			<tr>
				<td colspan="2">
				    <input type="hidden" name="isPosted" value="TRUE">
		        	<input type='hidden' name='form-type' id='form-type' value="search">
				    <input type="submit" name="search-list" value="Search">
					<input type="submit" title="Reset all fields and clear the results table" name="reset-list" value="Reset">
				</td>
			</tr>
		</table>

	<?php
	}
	?>

</form>
