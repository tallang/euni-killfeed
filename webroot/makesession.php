<!-- this is a debug page, obviously --><?php
	require(__DIR__.'/../helpers/session.inc.php');
	require(__DIR__.'/../helpers/auth.inc.php');
	if (isLoggedIn())
	{
		Header('Location: makebr.php');
		die();
	}
	if (isset($_POST['userid']) && isset($_POST['charactername']) && isset($_POST['characterid']))
	{
		$_SESSION['userId'] = $_POST['userid'];
		$_SESSION['characterName'] = $_POST['charactername'];
		$_SESSION['characterId'] = +$_POST['characterid'];
		$_SESSION['remember'] = 1;
		header('Location: makebr.php');
		die();
	}
?><html><head></head><body>
<h2>Log in as yourself:</h2>
<form action="" method="POST">
<table>
<tr><td><label for="userid">Forum user ID (from profile page URL):</label></td><td><input type="text" id="userid" name="userid" /></td></tr>
<tr><td><label for="charactername">Character name:</label></td><td><input type="text" id="charactername" name="charactername" /></td></tr>
<tr><td><label for="characterid">Character ID (from evewho character image URL):</label></td><td><input type="text" id="characterid" name="characterid" /></td></tr>
</table>
<input type="submit" value="OK, make me a session!" />
</form>

<h2>Log in as Tertius Tallang:</h2>
<form action="" method="POST">
<input type="hidden" name="userid" value="57954" />
<input type="hidden" name="charactername" value="Tertius Tallang" />
<input type="hidden" name="characterid" value="93328111" />
<input type="submit" value="Just let me test stuff!" />
</form>
</body></html>