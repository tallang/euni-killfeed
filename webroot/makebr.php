<?php
	require(__DIR__.'/../helpers/auth.inc.php');
	if (!isLoggedIn())
	{
		header('Location: makesession.php');
		die();
	}
	if (isset($_POST['systemname']) && isset($_POST['starttime']) && isset($_POST['endtime']))
	{
		require_once(__DIR__.'/../helpers/database.inc.php');
		try
		{
			$db = killfeedDB();
			$db->beginTransaction();
			$getsystemid = prepareQuery($db,'select solarsystemid from cache_solarsysteminfo where solarSystemName = :name');
			$getsystemid->bindValue(':name',$_POST['systemname'],PDO::PARAM_STR);
			executeQuery($getsystemid);
			if (!$getsystemid->rowCount())
			{
				$error = "Cannot find system '".$_POST['systemname']."'";
				goto render;
			}
			$systemid = +$getsystemid->fetchColumn();
			
			$insertbr = prepareQuery($db,'insert into battlereport_meta (reportid,ownercharacterid,lastrefreshed,reporttype) values (null,:ownerid,null,\'2side\')');
			$insertbr->bindValue(':ownerid',getCurrentUserCharacterId());
			executeQuery($insertbr);
			
			$reportid = $db->lastInsertId();
			$insertsource = prepareQuery($db,'insert into battlereport_sources (reportid,solarsystemid,starttime,endtime) values (:reportid,:systemid,:starttime,:endtime)');
			$insertsource->bindValue(':reportid',$reportid,PDO::PARAM_INT);
			$insertsource->bindValue(':systemid',$systemid,PDO::PARAM_INT);
			$insertsource->bindValue(':starttime',$_POST['starttime'],PDO::PARAM_STR);
			$insertsource->bindValue(':endtime',$_POST['endtime'],PDO::PARAM_STR);
			executeQuery($insertsource);
			$db->commit();
			
			header('Location: br.php?id='.$reportid);
			die();
		}
		catch (RuntimeException $e)
		{
			if (isset($db))
				$db->rollback();
			header('Content-Type: text/plain');
			echo 'Error: ',$e->getMessage();
			die();
		}
	}
	render:
?><html><head></head><body>
<h2>(Debug) Create BR:</h2>
<?php if (isset($error)) { ?>
<div style="color: #ff0000; background: #000000; border: 2px solid #555555;"><?=htmlentities($error);?></div>
<?php } ?>
<form action="" method="POST">
<table>
<tr><td><label for="systemname">Solar system name:</label></td><td><input type="text" id="systemname" name="systemname" value="Jita" /></td></tr>
<tr><td><label for="starttime">Start time:</label></td><td><input type="datetime" id="starttime" name="starttime" value="1970-01-01 00:00:00" /></td></tr>
<tr><td><label for="endtime">End time:</label></td><td><input type="datetime" id="endtime" name="endtime" value="1970-01-01 00:00:00" /></td></tr>
</table>
<input type="submit" value="Generate BR" />
</form>
</body></html>