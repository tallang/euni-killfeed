<?php
	$isCLI = (defined('STDIN') || (php_sapi_name() === 'cli') || (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) || !isset($_SERVER['REQUEST_METHOD']));
	
	// status display
	// this will only output in CLI, not in server context
	// used for killmail parsing, among others
	function doStatus()
	{
		global $isCLI;
		if ($isCLI)
			foreach (func_get_args() as $arg)
				echo $arg;
	}
	// error handler function - this is assumed to have been included everywhere
	// ALWAYS include this file in webroot php scripts
	// numeric $text fields are used to fetch the static variables
	// $code should be the numeric http response code to use
	// Standard codes are:
	// 500 for server-side failures (database etc) - these messages are not sent to user, just logged to DB
	// 400/403/404 - bad request/forbidden/not found, error message will be sent to user
	// 200 - info logs that should not cause change in user-facing behavior
	function doError($text,$code = 500,$logToFile = null)
	{
		global $isCLI;
		static $hadError = false;
		static $errorText = null;
		if ($text === 1)
			return $hadError;
		elseif ($text === 2)
			return $errorText;
		
		if ($isCLI)
			printf("ERROR %d: %s\n",$code,$text);
		
		if ($logToFile === true ||
			($logToFile === null && (($code === 200) || $code == 500)))
		{
			$errorFile = fopen(__DIR__.'/../error.log','a');
			if ($errorFile)
			{
				if (flock($errorFile, LOCK_EX))
				{
					fwrite($errorFile, sprintf("== Error #%d ==\n%s %s %s\n%s\n\n",$code,@$_SERVER['REQUEST_METHOD'],@$_SERVER['REQUEST_URI'],@$_SERVER['PHP_SELF'],$text));
					fflush($errorFile);
					flock($errorFile, LOCK_UN);
				}
				else
					die('Something has gone horribly wrong.');
				fclose($errorFile);
			}
			else
				die ('Something has gone horribly wrong.');
		}
		
		if ($isCLI)
			return;
		
		switch ($code)
		{
			case 200:
				break;
			case 400:
				header('HTTP/1.1 400 Bad Request');
				$hadError = true;
				$errorText = $text;
				break;
			case 403:
				header('HTTP/1.1 403 Forbidden');
				$hadError = true;
				$errorText = $text;
				break;
			case 404:
				header('HTTP/1.1 404 Not Found');
				$hadError = true;
				$errorText = $text;
				break;
			case 500:
				header('HTTP/1.1 500 Internal Server Error');
				$hadError = true;
				break;
		}
	}
	if (!$isCLI)
		ob_start();
?>