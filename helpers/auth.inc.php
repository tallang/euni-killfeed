<?php
	require_once(__DIR__.'/database.inc.php');
	require_once(__DIR__.'/session.inc.php');
	
	function attemptLogin($username,$password)
	{
		try
		{
			$db = authDB();
			
			$passwordQuery = prepareQuery($db,'SELECT
												`user`.`user_id` as `userId`,
												`user`.`username` as `characterName`,
												`user`.`user_password` as `password`,
												`profile`.`pf_characterid` as `characterId`
												FROM `phpbb_users` as `user`
												LEFT JOIN `phpbb_profile_fields_data` as `profile`
													ON `user`.`user_id` = `profile`.`user_id`
												WHERE `user`.`username_clean` = LCASE(:username)
												LIMIT 1');
			$passwordQuery->bindValue(':username',$username,PDO::PARAM_STR);
			executeQuery($passwordQuery);
			if (!$passwordQuery->rowCount())
				return null; // user not found
			$userData = $passwordQuery->fetchObject();
			
			require_once('hash.inc.php');
			$hasher = new PasswordHash(8,TRUE);
			$success = $hasher->CheckPassword(trim(htmlspecialchars($password)),$userData->password);
			unset($hasher);
			$data = new stdClass();
			$data->userId = +$userData->userId;
			$data->characterName = $userData->characterName;
			$data->characterId = +$userData->characterId;
			if ($success)
				return $data;
			else
				return null;
		}
		catch (RuntimeException $e)
		{
			doError("Error while verifying login for '$username': ".$e->getMessage(),200);
			return null; // unknown error
		}
	}
	
	function isLoggedIn()
	{
		return isset($_SESSION['userId']) && $_SESSION['userId'];
	}
	
	function currentUserIsAdmin()
	{
		// @todo stub
		return false;
	}
	
	function getCurrentUserName()
	{
		if (isLoggedIn())
			return $_SESSION['characterName'];
		else
			return '';
	}
	
	function getCurrentUserCharacterId()
	{
		if (isLoggedIn())
			return $_SESSION['characterId'];
		else
			return 0;
	}
?>