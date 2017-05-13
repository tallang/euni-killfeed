<?php
	require(__DIR__.'/../render/setup.inc.php');
	require(__DIR__.'/../helpers/http.inc.php');
	require(__DIR__.'/../helpers/killmailHandling.inc.php');
	
	if (array_key_exists('url',$_POST))
		$crestUrl = $_POST['url'];
	else
	{
		doError('No CREST URL specified.',400);
		goto error;
	}
	if (strncmp($crestUrl,'https://crest-tq.eveonline.com/killmails/',41) !== 0)
	{
		doError('No CREST URL specified.',400);
		goto error;
	}
	
	$crestStr = httpRequestWithRetries($crestUrl,5);
	if (!$crestStr)
	{
		doError("Failed to connect to specified CREST URL",404);
		goto error;
	}
	$crestData = json_decode($crestStr);
	if (!$crestData)
	{
		doError("Failed to read CREST response from $crestUrl - unknown error?",500);
		goto error;
	}
	
	if ($killId = importKillmail($crestData,MODE_CREST))
	{
		header("Location: kill.php?killID=$killId");
		ob_end_clean();
		return;
	}
	
	error:
	require(__DIR__.'/../render/doRender.inc.php');
?>