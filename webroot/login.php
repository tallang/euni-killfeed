<?php
	require(__DIR__.'/../render/setup.inc.php');
	header('Content-Type: text/plain');
	if (!isset($_POST['user']) || !isset($_POST['pass']) || !isset($_POST['remember']))
		die('nok');
	
	require(__DIR__.'/../helpers/session.inc.php');
	require(__DIR__.'/../helpers/auth.inc.php');
	if (isLoggedIn())
		die('ok');
	
	if ($userData = attemptLogin($_POST['user'],$_POST['pass']))
	{
		$_SESSION['userId'] = $userData->userId;
		$_SESSION['characterName'] = $userData->characterName;
		$_SESSION['characterId'] = $userData->characterId;
		$_SESSION['remember'] = +$_POST['remember'];
		echo 'ok';
	}
	else
		echo 'nok';
?>