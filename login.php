<?php
/** 
 * File: login.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */


$helpmessage = "You need to log in to access this page.";

?>
<form name="guestlogin" id='login' method="POST" action="index.php?mode=search&amp;type=word">
	<p>
		<input type='hidden' name='username' value='Guest'>
		<input type='hidden' name='password' value='password'>
		<input type='hidden' name='form-type' id='form-type' value="login">
	</p>

	<p><input type="Submit" Name="Submit2" value="Login as Guest"></p>
</form>

<br/>
<form name="login" id='login' method="POST" action="index.php?mode=search&amp;type=word">
	<p>
		Username: <input type='text' name='username'>
		Password: <input type='password' name='password'>
		          <input type='hidden' name='form-type' id='form-type' value="login">
	</p>

	<p><input type="Submit" Name="Submit1" value="Login"></p>
</form>
