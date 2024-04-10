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
//session_start();
//if (!(isset($_SESSION['login']) && $_SESSION['login'] != '')) {
	//header ("Location: login.php");
//}

//set the session variable to 1, if the user signs up. That way, they can use the site straight away
//do you want to send the user a confirmation email?
//does the user need to validate an email address, before they can use the site?
//do you want to display a message for the user that a particular username is already taken?
//test to see if the u and p are long enough
//you might also want to test if the users is already logged in. That way, they can't sign up repeatedly without closing down the browser
//other login methods - set a cookie, and read that back for every page
//collect other information: date and time of login, ip address, etc
//don't store passwords without encrypting them

$helpmessage = "See help text next to each field, describing limitations.";

if($_POST['usertype'] == "User"){
	$select['superuser'] = "";
	$select['user'] = "selected";
}

// Sett validation classes
$validation_class = array("validation-fail", "");

if (!$insert_success) {
	echo "<p class='validation-fail'>" . $errorMessage . "</p>";
?>
	<form class="signup-form" name="form1" method="POST" action="index.php?mode=addUser">
		<input type="hidden" name="form-type" value="new-user">
		<div class="form-group">
			<label for="username">Username:</label>
			<input class="<?php echo $validation_class[$validation['username']]; ?>" type="text" id="username" name="username" placeholder="Username" value="<?php echo $_POST['username'] ?>">
			<span class="helptext">Has to be at least 5 characters long</span>
		</div>
		<div class="form-group">
			<label for="password">Password:</label>
			<input class="<?php echo $validation_class[$validation['password']]; ?>" type="password" id="password" name="password" placeholder="Password">
			<span class="helptext">Has to be at least 8 characters long</span>
		</div>
		<div class="form-group">
			<label for="signature">Signature:</label>
			<input class="<?php echo $validation_class[$validation['signature']]; ?>" type="signature" id="signature" name="signature" placeholder="Signature" value="<?php echo $_POST['signature'] ?>">
			<span class="helptext">This will be shown in strain lists, has to be between 2 and 15 characters</span>
		</div>
		<div class="form-group">
			<label for="usertype">User type:</label>
			<select id="usertype" name="usertype">
				<option value="Superuser" <?php echo $select['superuser'] ?>>Superuser</option>
				<option value="User" <?php echo $select['user'] ?>>User</option>
			</select>
			<span class="helptext">Superusers can insert, edit, and delete strains as well as add new users</span>
		</div>
		<input type="submit" name="Submit" value="Register">
	</form>

<?php 
} else {
	echo "<p><span style='font-weight: bold'>Success!</span><br>The new user was successfully added.</p>";
	echo "<p><a href='index.php?mode=addUser'>Add another user</a></p>";
	$helpmessage = "To log in as the new user, <a href='index.php?logout=1'>log out " . $_SESSION['user'] . "</a>.";
}

?>
