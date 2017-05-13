<?php
	header('Content-Type: text/plain');
	if (!isset($_POST['killID']) || !isset($_POST['comment']))
		die('nok');
	
	require(__DIR__.'/../helpers/session.inc.php');
	require(__DIR__.'/../helpers/auth.inc.php');
	if (!isLoggedIn())
		die('nok');
	
	require_once(__DIR__.'/../helpers/database.inc.php');
	$query = prepareQuery(killfeedDB(),'INSERT INTO `kill_comments` (`killId`,`commentDate`,`commenter`,`comment`) VALUES (:killId,UTC_TIMESTAMP(),:characterId,:commentText);');
	if (!$query)
		die('nok');
	$query->bindValue(':killId',+$_POST['killID'],PDO::PARAM_INT);
	$query->bindValue(':characterId',getCurrentUserCharacterId(),PDO::PARAM_INT);
	$query->bindValue(':commentText',$_POST['comment'],PDO::PARAM_STR);
	if (!$query->execute())
		die('nok');
	echo 'ok';
?>