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
