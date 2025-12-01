<?php
/** 
 * File: functions.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */





// CHECK IF USER EXISTS
/**
 * user_exists — function documentation.
 *
 * @param mixed $user
 * @param mixed $field
 * @return mixed
 */

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
/**
 * strain_exists — function documentation.
 *
 * @param mixed $strain
 * @return mixed
 */

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
	if (!function_exists('e')) {
/**
 * e — function documentation.
 *
 * @param mixed $s
 * @return mixed
 */

    function e(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('csrf_token')) {
/**
 * csrf_token — function documentation.
 *
 * @return mixed
 */

    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('verify_csrf')) {
/**
 * verify_csrf — function documentation.
 *
 * @param mixed $t
 * @return mixed
 */

    function verify_csrf(?string $t): void {
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($t) || !hash_equals($expected, $t)) { http_response_code(419); exit('CSRF token mismatch'); }
    }
}

}
