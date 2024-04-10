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




// CHECK IF USER EXISTS
function user_exists($user = NULL, $field = "id"){
	global $dbh;

	// Prepare PDO statement
	if($field == "id"){
		$stmt = $dbh->prepare("SELECT * FROM users WHERE Id = :user");
	}
	elseif ($field == "username") {
		$stmt = $dbh->prepare("SELECT * FROM users WHERE Username = :user");
	}
	elseif ($field == "signature") {
		$stmt = $dbh->prepare("SELECT * FROM users WHERE Signature = :user");
	}

	// Bind parameter
	$stmt->bindParam(":user", $user);

	// Execute statement
	$stmt->execute();

	// Check how many rows were found
	$rows_found = $stmt->rowCount();

	// Return based on result
	if($rows_found > 0){
		return TRUE;
	} else {
		return FALSE;
	}
}

// CHECK IF STRAIN EXISTS
function strain_exists($strain = NULL){
	global $dbh;

	if($strain !== NULL && is_numeric($strain)){
		// Prepare PDO statement
		$stmt = $dbh->prepare("SELECT * FROM strains WHERE Strain = :strain");

		// Bind parameter
		$stmt->bindParam(":strain", $strain);

		// Execute statement
		$stmt->execute();

		// Check how many rows were found
		$rows_found = $stmt->rowCount();

		// Return based on result
		if($rows_found > 0){
			return TRUE;
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}
