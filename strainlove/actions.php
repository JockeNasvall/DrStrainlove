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


<?php

// DO LOGIN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'login'){

	$uname = $_POST['username'];
	$pword = $_POST['password'];

	// Prepare PDO statement
	$stmt = $dbh->prepare("SELECT * FROM users WHERE Username = :uname AND Password = :pword");
	$stmtCount = $dbh->prepare("SELECT COUNT(*) FROM users WHERE Username = :uname AND Password = :pword");

	// Bind parameters
	$pwordmd5 = md5($pword);
	$stmt->bindParam(":uname", $uname);
	$stmt->bindParam(":pword", $pwordmd5);
	$stmtCount->bindParam(":uname", $uname);
	$stmtCount->bindParam(":pword", $pwordmd5);

	// Execute the statements
	$stmt->execute();
	$stmtCount->execute();

	// Cast the fetched count-column as an integaer
	$count = (int) $stmtCount->fetchColumn();

	if($count > 0){
		// Store the fetched row in a user variable
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		// Store session as logged in
		$_SESSION['login'] = "1";

		// Store user details in session
		$_SESSION['user'] = $user['Username'];
		$_SESSION['Usertype'] = $user['Usertype'];
		$_SESSION['Signature'] = $user['Signature'];

		// Close database cursors
		$stmt->closeCursor();
		$stmtCount->closeCursor();

	} else {
		$_SESSION['login'] = "";

		$errorMessage = "Error logging on";

		// Close database cursors
		$stmt->closeCursor();
		$stmtCount->closeCursor();
	}
}

// DO SEARCH
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'search'){

	// Search button has been pressed
	if($_POST['search-text'] == 'Search' || $_POST['search-list'] == 'Search'){
		$_SESSION['sign1'] = $_POST['sign1'];

		// Search is text
		if($_POST['search-text'] == 'Search') {
			$_SESSION['term1'] = $_POST['term1'];
			$_SESSION['term2'] = $_POST['term2'];
			$_SESSION['term3'] = $_POST['term3'];
			$_SESSION['term4'] = $_POST['term4'];
			$_SESSION['notterm1'] = $_POST['notterm1'];
			$_SESSION['notterm2'] = $_POST['notterm2'];
			$_SESSION['notterm3'] = $_POST['notterm3'];
			$_SESSION['notterm4'] = $_POST['notterm4'];
			$_SESSION['genotype'] = $_POST['check']['genotype'];
			$_SESSION['comment'] = $_POST['check']['comment'];
			if(!empty($_POST['check']['genotype'])){
				$_SESSION['searchgenotype'] = 1;
			} else {
				$_SESSION['searchgenotype'] = 0;
			}
			if(!empty($_POST['check']['comment'])){
				$_SESSION['searchcomment'] = 1;
			} else {
				$_SESSION['searchcomment'] = 0;
			}

			// If all search boxes are empty, redirect to error
			if(empty($_POST['term1']) &&
				empty($_POST['term2']) &&
				empty($_POST['term3']) &&
				empty($_POST['term4']) &&
				empty($_POST['sign1'])
			) {
				header("Location: index.php?mode=search&error=error");
			}
		}

		// Search is number/list
		if($_POST['search-list'] == 'Search') {
			$_SESSION['minNum'] = $_POST['minNum'];
			$_SESSION['maxNum'] = $_POST['maxNum'];
		}
	}

	// The reset button clears all submitted variables from all forms
	if($_POST['reset-list'] == 'Reset' OR $_POST['reset-word'] == 'Reset'){
		//Search:
		$_SESSION['total_records'] = '';
		$_SESSION['word'] = '';
		$_SESSION['num'] = '';
		$_SESSION['check'] = '';
		$_SESSION['genotype'] = '';
		$_SESSION['comment'] = '';
		$_SESSION['term1'] = '';
		$_SESSION['term1'] = '';
		$_SESSION['term2'] = '';
		$_SESSION['term3'] = '';
		$_SESSION['term4'] = '';
		$_SESSION['notterm1'] = '';
		$_SESSION['notterm2'] = '';
		$_SESSION['notterm3'] = '';
		$_SESSION['notterm4'] = '';
		$_SESSION['sign1'] = '';
		$_SESSION['minNum'] = '';
		$_SESSION['maxNum'] = '';

		if($_POST['reset-list'] == 'Reset'){
			header("Location: index.php?mode=search&type=number");
		}
		elseif($_POST['reset-word'] == 'Reset'){
			header("Location: index.php?mode=search&type=word");
		}
	}

}

// DO EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'edit'){

	// Check user rights
	if($_SESSION['Usertype'] == 'Superuser') {
		// Find out how many records there are to update
		$size = count($_POST['Genotype']);

		// Begin database transaction
		$dbh->beginTransaction();

		// Create SQL query
		$sql  = "UPDATE strains ";
			$sql .= "SET Genotype = :genotype, ";
			$sql .= "Donor = :donor, ";
			$sql .= "Recipient = :recipient, ";
			$sql .= "Comment = :comment, ";
			$sql .= "Signature = :signature, ";
			$sql .= "Created = :created ";
		$sql .= "WHERE Strain = :strain LIMIT 1";

		// Prepare statement
		$stmt = $dbh->prepare($sql);

		// Bind parameters
		$stmt->bindParam(":genotype", $genotype);
		$stmt->bindParam(":donor", $donor);
		$stmt->bindParam(":recipient", $recipient);
		$stmt->bindParam(":comment", $comment);
		$stmt->bindParam(":signature", $signature);
		$stmt->bindParam(":created", $created);
		$stmt->bindParam(":strain", $strain);

		for($i = 0; $i < $size; $i++){

			// Define variables
			$genotype  = str_replace(array("\r", "\r\n", "\n"), " ", $_POST['Genotype'][$i]);
			$donor     = intval($_POST['Donor'][$i]);
			$recipient = intval($_POST['Recipient'][$i]);
			$comment   = str_replace(array("\r", "\r\n", "\n"), " ", $_POST['Comment'][$i]);
			$signature = $_POST['Signature'][$i];
			$strain    = $_POST['Strain'][$i];

			// If the checkbox to update created is checked, use that value, otherwise use old value
			if(empty($_POST['Created'][$i])) {
				$created = $_POST['CreatedDate'][$i];
			} else {
				$created = $_POST['Created'][$i];
			}

			$selected[$i] = $strain;

			// Execute statement
			$stmt->execute();

			// Check error codes
			if($stmt->errorCode() === '00000'){
				$success = TRUE;
			} else {
				$success = FALSE;
				break;
			}
		}

		// Commit if all were successful
		unset($transaction_fail);
		if($success){
			$dbh->commit();
			$transaction_fail = FALSE;
		} else {
			$dbh->rollBack();
			$transaction_fail = TRUE;
		}
	}
}

// DO ADD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'add' && $_POST['submit']){

	// Check user rights
	if($_SESSION['Usertype'] == 'Superuser') {
		$test = array();
		$num_lines = count($_POST["txtGenotype"]);

		$testresult = 0;

		if($num_lines == 1) {
			$plural = '';
		} else {
			$plural = 's';
		}

		$_SESSION["Line"] = $num_lines;

		unset($_SESSION['saveFail']);

		// Save sessions and validate
		for($i = 1; $i <= $num_lines; $i++) {
			$_SESSION["txtGenotype"][$i] = $_POST["txtGenotype"][$i];
			$_SESSION["txtDonor"][$i] = $_POST["txtDonor"][$i];
			$_SESSION["txtRecipient"][$i] = $_POST["txtRecipient"][$i];
			$_SESSION["txtComment"][$i] = $_POST["txtComment"][$i];

			// Validate fields
			if(!empty($_POST["txtGenotype"][$i])){
				$validate['Genotype'] = 1;
			} else {
				$validate['Genotype'] = 0;
			}

			if(strain_exists($_POST["txtDonor"][$i]) || empty($_POST["txtDonor"][$i])){
				$validate['Donor'] = 1;
			} else {
				$validate['Donor'] = 0;
			}

			if(strain_exists($_POST["txtRecipient"][$i]) || empty($_POST["txtRecipient"][$i])){
				$validate['Recipient'] = 1;
			} else {
				$validate['Recipient'] = 0;
			}

			// If all fields are empty
			if(empty($_POST["txtDonor"][$i]) && empty($_POST['txtRecipient'][$i]) && empty($_POST['txtComment'][$i])){
				$emptyRow = TRUE;
			} else {
				$emptyRow = FALSE;
			}

			// If all validates pass
			if(array_sum($validate) == count($validate)){
				$test[$i] = 'PASS';
			} else {
				// If ALL fields are empty, set test to empty
				if($emptyRow){
					$test[$i] = 'EMPTY';
				} else {
					$test[$i] = 'FAIL';
					$_SESSION['saveFail'][$i] = $validate;
				}
			}
		}

		if (count($_SESSION['saveFail']) > 0) {
			$goAhead = FALSE;;
		} else {
			$goAhead = TRUE;
			unset($_SESSION['saveFail']);
		}

		if($goAhead) {
			// Create SQL query
			$sql = "INSERT INTO strains (Genotype,Donor,Recipient,Comment,Signature) VALUES (:genotype, :donor, :recipient, :comment, :signature)";

			// Begin database transaction
			$dbh->beginTransaction();

			// Prepare statement
			$stmt = $dbh->prepare($sql);

			unset($inserted);

			// Loop through all rows to be added
			for($i = 1; $i <= $num_lines; $i++) {
				if($test[$i] != "EMPTY"){
					// Prepare variables
					$genotype = str_replace(array("\r", "\r\n", "\n"), " ", $_POST["txtGenotype"][$i]);
					$donor = intval($_POST["txtDonor"][$i]);
					$recipient = intval($_POST["txtRecipient"][$i]);
					$comment = str_replace(array("\r", "\r\n", "\n"), " ", $_POST["txtComment"][$i]);
					$signature = $_POST["txtSignature"];

					// Bind parameters
					$stmt->bindParam(":genotype", $genotype);
					$stmt->bindParam(":donor", $donor);
					$stmt->bindParam(":recipient", $recipient);
					$stmt->bindParam(":comment", $comment);
					$stmt->bindParam(":signature", $signature);

					// Execute statement
					$stmt->execute();

					// Check error codes
					if($stmt->errorCode() === '00000'){
						$success = TRUE;
					} else {
						$success = FALSE;
						break;
					}

					// Save ID of last inserted row
					$inserted[] = $dbh->lastInsertId();

					// Clear the session
					unset($_SESSION["txtGenotype"][$i]);
					unset($_SESSION["txtDonor"][$i]);
					unset($_SESSION["txtRecipient"][$i]);
					unset($_SESSION["txtComment"][$i]);
				}
			}

			// Commit if all were successful
			unset($transaction_fail);
			if($success){
				$dbh->commit();
				$transaction_fail = FALSE;
				unset($_SESSION["Line"]);
			} else {
				$dbh->rollBack();
				$transaction_fail = TRUE;
			}
		}
		else{
			header("Location: index.php?mode=add&Line=" . $_SESSION["Line"]);
		}
	}
}

// DO UPDATE LINES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'add' && $_POST['update_lines']){
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
}

// DO RESET INPUT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'add' && $_POST['reset']){

	unset($_SESSION["txtGenotype"]);
	unset($_SESSION["txtDonor"]);
	unset($_SESSION["txtRecipient"]);
	unset($_SESSION["txtComment"]);
	$_SESSION['Line'] = 1;

	unset($_SESSION['saveFail']);

	header("Location: index.php?mode=add&Line=1");
}

// DO ADD NEW USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['form-type'] == 'new-user'){

	// Save post variables
	$username  = $_POST['username'];
    $usertype  = $_POST['usertype'];
	$password  = $_POST['password'];
	$signature = $_POST['signature'];

	// Get length of input
	$uLength = strlen($username);
	$pLength = strlen($password);
	$sLength = strlen($signature);

	// Validate
	$goAhead = TRUE;
	unset($validation);

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

	if ($pLength < 8) {
		$goAhead = FALSE;
		$errorMessage .= "Password must be at least 8 characters long<br>";
		$validation['password'] = 0;
	}

	// Check that type is one of 'User' or 'Superuser'
	if (!($usertype == 'Superuser' OR $usertype == 'User')) {
		$goAhead = FALSE;
		$errorMessage .= "User Type can only be User or Superuser<br>";
	}

	// Check if username already exists
	if(user_exists($username, "username")){
		$goAhead = FALSE;
		$errorMessage .= "User name already taken!<br>";
		$validation['username'] = 0;
	}

	// Check if username already exists
	if(user_exists($username, "signature")){
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
		$password = md5($password);

		// Bind parameters
		$stmt->bindParam(":username", $username);
		$stmt->bindParam(":usertype", $usertype);
		$stmt->bindParam(":password", $password);
		$stmt->bindParam(":signature", $signature);

		// Execute statement
		if($stmt->execute()){
			$insert_success = TRUE;
		} else {
			$insert_success = FALSE;
		}
	}
}

?>
