<?php
/** 
 * File: db.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */



$dbuser = 'USERNAME';
$dbpassword = 'PASSWORD';
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
	//$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e) {
	echo $e->getMessage();
	die();
}

?>
