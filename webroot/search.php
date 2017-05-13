<?php
	header('Content-Type: application/json');
	try
	{
		if (!isset($_POST['needle'])) throw new RuntimeException('No search string specified.');
		$doFull = false;
		if (isset($_POST['full'])) $doFull = true;
		
		require(__DIR__.'/../render/setup.inc.php');
		require(__DIR__.'/../helpers/database.inc.php');
		
		$data = new stdClass();
		$data->status = 'ok';
		
		$db = killfeedDB();
		if (!$db) throw new RuntimeException('Database connection failed.');
		if ($doFull)
		{
			$searchQuery = prepareQuery($db,'
			(SELECT "alliance" as `type`, `allianceId` as `id`, `allianceName` as `name` FROM `alliance_metadata` WHERE LOCATE(:needle,`allianceName`)>0 ORDER BY (`killCount`+`lossCount`) DESC)
			UNION
			(SELECT "corporation" as `type`, `corporationId` as `id`, `corporationName` as `name` FROM `corporation_metadata` WHERE LOCATE(:needle,`corporationName`)>0 ORDER BY (`killCount`+`lossCount`) DESC)
			UNION
			(SELECT "character" as `type`, `characterId` as `id`, `characterName` as `name` FROM `character_metadata` WHERE LOCATE(:needle,`characterName`)>0 ORDER BY (`killCount`+`lossCount`) DESC)
			');
			$searchQuery->bindValue(':needle',$_POST['needle'],PDO::PARAM_STR);
		}
		else
		{ // this is faster because it can use the index on the name columns, but it will only find names that start with the search string
			$searchQuery = prepareQuery($db,'
			(SELECT "alliance" as `type`, `allianceId` as `id`, `allianceName` as `name` FROM `alliance_metadata` WHERE `allianceName` LIKE :needle ORDER BY (`killCount`+`lossCount`) DESC)
			UNION
			(SELECT "corporation" as `type`, `corporationId` as `id`, `corporationName` as `name` FROM `corporation_metadata` WHERE `corporationName` LIKE :needle ORDER BY (`killCount`+`lossCount`) DESC)
			UNION
			(SELECT "character" as `type`, `characterId` as `id`, `characterName` as `name` FROM `character_metadata` WHERE `characterName` LIKE :needle ORDER BY (`killCount`+`lossCount`) DESC)
			');
			$searchQuery->bindValue(':needle',str_replace(array('\\','%','_'),array('\\\\','\\%','\\_'),$_POST['needle']).'%',PDO::PARAM_STR);
		}
		executeQuery($searchQuery);
		$data->results = fetchAll($searchQuery, 'DBSearchResult');
		$data->isFull = $doFull;
		
		echo json_encode($data);
	}
	catch (PDOException $e)
	{
		doError('DB error running search: '.$e->getMessage(),500);
		throw new RuntimeException('Database error.');
	}
	catch (RuntimeException $e)
	{
		$data = new stdClass();
		$data->status = 'nok';
		$data->error = $e->getMessage();
		
		echo json_encode($data);
	}
?>