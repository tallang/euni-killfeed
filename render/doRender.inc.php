<?php
	if ($isCLI)
		return;
	// we output buffer our entire output to be able to send 500 in case of errors
	// now we flush that output buffer
	if (doError(1)) // boolean: hadError
	{
		ob_end_clean(); // don't output partially generated content
		if (($errorText = doError(2)) !== null) // string: error text (or null)
		{ ?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8"/>
		<title>E-UNI Killfeed - Error</title>
		<link rel="stylesheet" href="css/navbar.css" />
		<link rel="stylesheet" href="css/killfeed.css" />
		<link rel="stylesheet" href="css/error.css" />
		<script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
		<meta name="viewport" content="width=1000, initial-scale=1" />
	</head>
	<body>
		<?php include(__DIR__.'/navbar.inc.php'); ?>
		<div id="content">
			<div id="error" class="panel">The page you were trying to access could not be generated. The server returned the following message:
				<pre><?=$errorText?></pre>
			The error has been logged and will be dealt with as appropriate. Sorry!</div>
		</div>
	</body>
</html><?php
		}
	}
	else
		ob_end_flush();
?>