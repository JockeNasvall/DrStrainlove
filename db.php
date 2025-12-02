<?php
/** 
 * File: db.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */



$dbuser = 'root';
$dbpassword = '1Lovestrains';
$dbname = 'strains';
$hostname = 'localhost';
$port = 3306;


/*
$dbuser = 'root';
$dbpassword = 'root';
$dbname = 'strains';
$hostname = 'localhost';
$port = 8889;
*/
// Open database connection
try {
	$dbh = new PDO("mysql:host=$hostname;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpassword);
	$pdo = $dbh; // alias for any code expecting $pdo
	$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	// surface DB errors as exceptions so they won't silently produce HTTP 500 without trace
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e) {
	// When debug is enabled in index.php you'll see this in the page; always log server-side
	error_log('[DB] Connection failed: ' . $e->getMessage());
	http_response_code(500);
	// Friendly message for browser; avoid leaking credentials
	echo 'Database connection failure. Check server error log for details.';
	die();
}

?>
