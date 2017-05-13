<?php
	require(__DIR__.'/render/setup.inc.php');
	require(__DIR__.'/helpers/database.inc.php');
	require(__DIR__.'/helpers/http.inc.php');
	require(__DIR__.'/helpers/killmailHandling.inc.php');
	require(__DIR__.'/helpers/profiling.inc.php');

	$db = killfeedDB(false);
	$queryUpdateAPIKeyData = prepareQuery($db,'UPDATE `api_keys` SET `latestKill`=?, `cacheTime`=? WHERE `keyID`=?;');
	$queryUpdateAPIKeyData->bindParam(1,$latestKillId,PDO::PARAM_INT);
	$queryUpdateAPIKeyData->bindParam(2,$cacheTime,PDO::PARAM_STR);
	$queryUpdateAPIKeyData->bindParam(3,$keyID,PDO::PARAM_INT);
	
	try
	{
		// find out which API keys are available to use right now
		try
		{
      $db->query('SET unique_checks=0;'); // we check uniqueness on kill_metadata, trust this to certify uniqueness
			$db->beginTransaction();
			$availableAPIKeys = $db->query('SELECT `keyID`,`vCode`,`isCorporate`,`characterID`,`latestKill`,`cacheTime` FROM `api_keys` WHERE `cacheTime` < UTC_TIMESTAMP() FOR UPDATE');
		}
		catch (PDOException $e)
		{
			die('Failed to query available API keys: '.$e->getMessage());
		}
		
		if (!$availableAPIKeys->rowCount())
			die("All API keys are currently waiting for cache expiry. Exiting...\n");
		
		$totalAPITime = 0;
		$totalMetadataTime = 0;
		$totalStatisticsTime = 0;
		$numKills = 0;
		$metadataCharacter = array();
		$metadataCorporation = array();
		$metadataAlliance = array();
		while ($apiKeyInfo = $availableAPIKeys->fetchObject())
		{
			$keyID = $apiKeyInfo->keyID;
			doStatus("Now processing: API key $keyID\n");
			$latestKillId = $apiKeyInfo->latestKill;
			unset($seekBack);
			if ($apiKeyInfo->isCorporate)
			{
				$keyType = 'corp';
				$characterIdPart = '';
			}
			else
			{
				$keyType = 'char';
				$characterIdPart = '&characterID='.$apiKeyInfo->characterID;
			}

			while (true) // main loop, keep seeking back as long as we get new mails
			{
				$time1 = PROFILE_time();
				if (isset($seekBack))
					$return = httpRequest(sprintf('https://api.eveonline.com/%s/KillLog.xml.aspx?keyID=%s&vCode=%s%s&beforeKillID=%s', $keyType, $keyID, $apiKeyInfo->vCode, $characterIdPart, $seekBack));
				else
					$return = httpRequest(sprintf('https://api.eveonline.com/%s/KillLog.xml.aspx?keyID=%s&vCode=%s%s', $keyType, $keyID, $apiKeyInfo->vCode, $characterIdPart));
				$xml = simplexml_load_string($return);
				
				/*if (isset($seekBack))
					break;
				$xml = simplexml_load_file('KillLog.xml.aspx');*/
				
				echo "Fetching API data for key $keyID...";
				if (!$xml)
				{
					echo "Error!\n";
					doError("Failed to parse API return data:\n".$return,500);
					die();
				}
				$cacheTime = $xml->cachedUntil;
				if ($xml->error)
				{
					echo "Error!\n";
					doError("API request for the kill log returned an error:\n".$xml->error."\n",500);
					break;
				}
				$time2 = PROFILE_time();
				$elapsed1 = PROFILE_elapsed($time1,$time2);
				$totalAPITime += $elapsed1;
				echo "Done (",round($elapsed1,5)," sec)\n";
				
				echo "Now processing kill metadata...\n";
				foreach ($xml->result->rowset->row as $kill)
				{
					$killId = (int)$kill['killID'];
					if ($killId <= $apiKeyInfo->latestKill)
					{
						$elapsed2 = PROFILE_elapsed($time2);
						$totalMetadataTime += $elapsed2;
						echo "Done (",round($elapsed2,5)," sec)\n";
						break 2; // we are done with this key!
					}
					
					++$numKills;
					if (importKillmail($kill,MODE_XMLAPI,$metadataCharacter,$metadataCorporation,$metadataAlliance) && $killId > $latestKillId)
						$latestKillId = $killId;
					$seekBack = $killId;
				}
				$elapsed2 = PROFILE_elapsed($time2);
				$totalMetadataTime += $elapsed2;
				echo "Done (",round($elapsed2,5)," sec)\n";
			}
			
			// Update API key information in DB (latestKill and cacheTime)
			$queryUpdateAPIKeyData->execute();
		}
		
		echo "Fetch complete. We asked CREST for item data $CRESTPullCount times, and retrieved value data for $EVECentralPullCount types from eve-central.com.\n";
		echo "$numKills kills successfully inserted.\n";
		echo "We spent ",round($totalAPITime,5)," seconds fetching data from the XML API and ",round($totalMetadataTime,5)," seconds inserting metadata.\n\n";
		$time3 = PROFILE_time();
		echo "Now updating statistics...";
		$querySelectExistingCharacter = prepareQuery($db,'SELECT `killCount`, `lossCount`, `killValue`, `effectiveKillValue`, `lossValue`, `averageFriendCount`, `averageEnemyCount` FROM `character_metadata` WHERE `characterId` = :characterId FOR UPDATE');
		$queryUpdateExistingCharacter = prepareQuery($db,'UPDATE `character_metadata` SET `corporationId`=:corporationId, `allianceId`=:allianceId, `killCount`=:killCount, `lossCount`=:lossCount, `killValue`=:killValue, `effectiveKillValue`=:effectiveKillValue, `lossValue`=:lossValue, `averageFriendCount`=:averageFriendCount, `averageEnemyCount`=:averageEnemyCount, `averageKillValue`=CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, `averageLossValue`=CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END WHERE `characterId`=:characterId');
		$queryInsertNewCharacter = prepareQuery($db,'INSERT INTO `character_metadata` (`characterId`,`characterName`,`corporationId`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (:characterId, :characterName, :corporationId, :allianceId, :killCount, :lossCount, :killValue, :effectiveKillValue, :lossValue, :averageFriendCount, CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, :averageEnemyCount, CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END)');
		foreach ($metadataCharacter as &$characterMeta)
		{
			$querySelectExistingCharacter->bindValue(':characterId',$characterMeta->characterId,PDO::PARAM_INT);
			if (!$querySelectExistingCharacter->execute())
				echo "DB error updating metadata for P'",$characterMeta->characterName,"' (",$characterMeta->characterId,"): ",$querySelectExistingCharacter->errorInfo()[2],"\n";
			else
			{
				if ($querySelectExistingCharacter->rowCount())
				{
					$existingCharacter = $querySelectExistingCharacter->fetchObject('DBEntityMetadata');
					$friendCount = $existingCharacter->averageFriendCount * $existingCharacter->killCount;
					$enemyCount = $existingCharacter->averageEnemyCount * $existingCharacter->lossCount;
					$existingCharacter->killCount += $characterMeta->killCount;
					$existingCharacter->lossCount += $characterMeta->lossCount;
					$existingCharacter->killValue += $characterMeta->killValue;
					$existingCharacter->effectiveKillValue += $characterMeta->effectiveKillValue;
					$existingCharacter->lossValue += $characterMeta->lossValue;
					$friendCount += $characterMeta->friendCount;
					$enemyCount += $characterMeta->enemyCount;
					$queryUpdateExistingCharacter->bindValue(':characterId',$characterMeta->characterId,PDO::PARAM_INT);
					$queryUpdateExistingCharacter->bindValue(':corporationId',$characterMeta->corporationId,PDO::PARAM_INT);
					$queryUpdateExistingCharacter->bindValue(':allianceId',$characterMeta->allianceId,PDO::PARAM_INT);
					$queryUpdateExistingCharacter->bindValue(':killCount',$existingCharacter->killCount,PDO::PARAM_INT);
					$queryUpdateExistingCharacter->bindValue(':lossCount',$existingCharacter->lossCount,PDO::PARAM_INT);
					$queryUpdateExistingCharacter->bindValue(':killValue',$existingCharacter->killValue,PDO::PARAM_STR);
					$queryUpdateExistingCharacter->bindValue(':effectiveKillValue',$existingCharacter->effectiveKillValue,PDO::PARAM_STR);
					$queryUpdateExistingCharacter->bindValue(':lossValue',$existingCharacter->lossValue,PDO::PARAM_STR);
					$queryUpdateExistingCharacter->bindValue(':averageFriendCount',($existingCharacter->killCount ? $friendCount/$existingCharacter->killCount : 0),PDO::PARAM_STR);
					$queryUpdateExistingCharacter->bindValue(':averageEnemyCount',($existingCharacter->lossCount ? $enemyCount/$existingCharacter->lossCount : 0),PDO::PARAM_STR);
					if (!$queryUpdateExistingCharacter->execute())
						echo "DB error updating metadata for P'",$characterMeta->characterName,"' (",$characterMeta->characterId,"): ",$queryUpdateExistingCharacter->errorInfo()[2],"\n";
				}
				else
				{
					$queryInsertNewCharacter->bindValue(':characterId',$characterMeta->characterId,PDO::PARAM_INT);
					$queryInsertNewCharacter->bindValue(':characterName',$characterMeta->characterName,PDO::PARAM_STR);
					$queryInsertNewCharacter->bindValue(':corporationId',$characterMeta->corporationId,PDO::PARAM_INT);
					$queryInsertNewCharacter->bindValue(':allianceId',$characterMeta->allianceId,PDO::PARAM_INT);
					$queryInsertNewCharacter->bindValue(':killCount',$characterMeta->killCount,PDO::PARAM_INT);
					$queryInsertNewCharacter->bindValue(':lossCount',$characterMeta->lossCount,PDO::PARAM_INT);
					$queryInsertNewCharacter->bindValue(':killValue',$characterMeta->killValue,PDO::PARAM_STR);
					$queryInsertNewCharacter->bindValue(':lossValue',$characterMeta->lossValue,PDO::PARAM_STR);
					$queryInsertNewCharacter->bindValue(':effectiveKillValue',$characterMeta->effectiveKillValue,PDO::PARAM_STR);
					$queryInsertNewCharacter->bindValue(':averageFriendCount',($characterMeta->killCount ? $characterMeta->friendCount/$characterMeta->killCount : 0), PDO::PARAM_STR);
					$queryInsertNewCharacter->bindValue(':averageEnemyCount',($characterMeta->lossCount ? $characterMeta->enemyCount/$characterMeta->lossCount : 0), PDO::PARAM_STR);
					if (!$queryInsertNewCharacter->execute())
						echo "DB error inserting metadata for P'",$characterMeta->characterName,"' (",$characterMeta->characterId,"): ",$queryInsertNewCharacter->errorInfo()[2],"\n";
				}
			}
		}
		
		$querySelectExistingCorporation = prepareQuery($db,'SELECT `killCount`, `lossCount`, `killValue`, `effectiveKillValue`, `lossValue`, `averageFriendCount`, `averageEnemyCount` FROM `corporation_metadata` WHERE `corporationId` = :corporationId FOR UPDATE');
		$queryUpdateExistingCorporation = prepareQuery($db,'UPDATE `corporation_metadata` SET `killCount`=:killCount, `lossCount`=:lossCount, `killValue`=:killValue, `effectiveKillValue`=:effectiveKillValue, `lossValue`=:lossValue, `averageFriendCount`=:averageFriendCount, `averageEnemyCount`=:averageEnemyCount, `averageKillValue`=CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, `averageLossValue`=CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END WHERE `corporationId`=:corporationId');
		$queryInsertNewCorporation = prepareQuery($db,'INSERT INTO `corporation_metadata` (`corporationId`,`corporationName`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (:corporationId, :corporationName, :allianceId, :killCount, :lossCount, :killValue, :effectiveKillValue, :lossValue, :averageFriendCount, CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, :averageEnemyCount, CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END)');
		foreach ($metadataCorporation as &$corporationMeta)
		{
			$querySelectExistingCorporation->bindValue(':corporationId',$corporationMeta->corporationId,PDO::PARAM_INT);
			if (!$querySelectExistingCorporation->execute())
				echo "DB error updating metadata for C'",$corporationMeta->corporationName,"' (",$corporationMeta->corporationId,"): ",$querySelectExistingCorporation->errorInfo()[2],"\n";
			else
			{
				if ($querySelectExistingCorporation->rowCount())
				{
					$existingCorporation = $querySelectExistingCorporation->fetchObject('DBEntityMetadata');
					$friendCount = $existingCorporation->averageFriendCount * $existingCorporation->killCount;
					$enemyCount = $existingCorporation->averageEnemyCount * $existingCorporation->lossCount;
					$existingCorporation->killCount += $corporationMeta->killCount;
					$existingCorporation->lossCount += $corporationMeta->lossCount;
					$existingCorporation->killValue += $corporationMeta->killValue;
					$existingCorporation->effectiveKillValue += $corporationMeta->effectiveKillValue;
					$existingCorporation->lossValue += $corporationMeta->lossValue;
					$friendCount += $corporationMeta->friendCount;
					$enemyCount += $corporationMeta->enemyCount;
					$queryUpdateExistingCorporation->bindValue(':corporationId',$corporationMeta->corporationId,PDO::PARAM_INT);
					$queryUpdateExistingCorporation->bindValue(':allianceId',$corporationMeta->allianceId,PDO::PARAM_INT);
					$queryUpdateExistingCorporation->bindValue(':killCount',$existingCorporation->killCount,PDO::PARAM_INT);
					$queryUpdateExistingCorporation->bindValue(':lossCount',$existingCorporation->lossCount,PDO::PARAM_INT);
					$queryUpdateExistingCorporation->bindValue(':killValue',$existingCorporation->killValue,PDO::PARAM_STR);
					$queryUpdateExistingCorporation->bindValue(':effectiveKillValue',$existingCorporation->effectiveKillValue,PDO::PARAM_STR);
					$queryUpdateExistingCorporation->bindValue(':lossValue',$existingCorporation->lossValue,PDO::PARAM_STR);
					$queryUpdateExistingCorporation->bindValue(':averageFriendCount',($existingCorporation->killCount ? $friendCount/$existingCorporation->killCount : 0),PDO::PARAM_STR);
					$queryUpdateExistingCorporation->bindValue(':averageEnemyCount',($existingCorporation->lossCount ? $enemyCount/$existingCorporation->lossCount : 0),PDO::PARAM_STR);
					if (!$queryUpdateExistingCorporation->execute())
						echo "DB error updating metadata for C'",$corporationMeta->corporationName,"' (",$corporationMeta->corporationId,"): ",$queryUpdateExistingCorporation->errorInfo()[2],"\n";
				}
				else
				{
					$queryInsertNewCorporation->bindValue(':corporationId',$corporationMeta->corporationId,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':corporationName',$corporationMeta->corporationName,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':allianceId',$corporationMeta->allianceId,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':killCount',$corporationMeta->killCount,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':lossCount',$corporationMeta->lossCount,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':killValue',$corporationMeta->killValue,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':lossValue',$corporationMeta->lossValue,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':effectiveKillValue',$corporationMeta->effectiveKillValue,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':averageFriendCount',($corporationMeta->killCount ? $corporationMeta->friendCount/$corporationMeta->killCount : 0), PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':averageEnemyCount',($corporationMeta->lossCount ? $corporationMeta->enemyCount/$corporationMeta->lossCount : 0), PDO::PARAM_STR);
					if (!$queryInsertNewCorporation->execute())
						echo "DB error inserting metadata for C'",$corporationMeta->corporationName,"' (",$corporationMeta->corporationId,"): ",$queryInsertNewCorporation->errorInfo()[2],"\n";
				}
			}
		}
		
		$querySelectExistingAlliance = prepareQuery($db,'SELECT `killCount`, `lossCount`, `killValue`, `effectiveKillValue`, `lossValue`, `averageFriendCount`, `averageEnemyCount` FROM `alliance_metadata` WHERE `allianceId` = :allianceId FOR UPDATE');
		$queryUpdateExistingAlliance = prepareQuery($db,'UPDATE `alliance_metadata` SET `killCount`=:killCount, `lossCount`=:lossCount, `killValue`=:killValue, `effectiveKillValue`=:effectiveKillValue, `lossValue`=:lossValue, `averageFriendCount`=:averageFriendCount, `averageEnemyCount`=:averageEnemyCount, `averageKillValue`=CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, `averageLossValue`=CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END WHERE `allianceId`=:allianceId');
		$queryInsertNewAlliance = prepareQuery($db,'INSERT INTO `alliance_metadata` (`allianceId`,`allianceName`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (:allianceId, :allianceName, :killCount, :lossCount, :killValue, :effectiveKillValue, :lossValue, :averageFriendCount, CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, :averageEnemyCount, CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END)');
		foreach ($metadataAlliance as &$allianceMeta)
		{
			$querySelectExistingAlliance->bindValue(':allianceId',$allianceMeta->allianceId,PDO::PARAM_INT);
			if (!$querySelectExistingAlliance->execute())
				echo "DB error updating metadata for A'",$allianceMeta->allianceName,"' (",$allianceMeta->allianceId,"): ",$querySelectExistingAlliance->errorInfo()[2],"\n";
			else
			{
				if ($querySelectExistingAlliance->rowCount())
				{
					$existingAlliance = $querySelectExistingAlliance->fetchObject('DBEntityMetadata');
					$friendCount = $existingAlliance->averageFriendCount * $existingAlliance->killCount;
					$enemyCount = $existingAlliance->averageEnemyCount * $existingAlliance->lossCount;
					$existingAlliance->killCount += $allianceMeta->killCount;
					$existingAlliance->lossCount += $allianceMeta->lossCount;
					$existingAlliance->killValue += $allianceMeta->killValue;
					$existingAlliance->effectiveKillValue += $allianceMeta->effectiveKillValue;
					$existingAlliance->lossValue += $allianceMeta->lossValue;
					$friendCount += $allianceMeta->friendCount;
					$enemyCount += $allianceMeta->enemyCount;
					$queryUpdateExistingAlliance->bindValue(':allianceId',$allianceMeta->allianceId,PDO::PARAM_INT);
					$queryUpdateExistingAlliance->bindValue(':killCount',$existingAlliance->killCount,PDO::PARAM_INT);
					$queryUpdateExistingAlliance->bindValue(':lossCount',$existingAlliance->lossCount,PDO::PARAM_INT);
					$queryUpdateExistingAlliance->bindValue(':killValue',$existingAlliance->killValue,PDO::PARAM_STR);
					$queryUpdateExistingAlliance->bindValue(':effectiveKillValue',$existingAlliance->effectiveKillValue,PDO::PARAM_STR);
					$queryUpdateExistingAlliance->bindValue(':lossValue',$existingAlliance->lossValue,PDO::PARAM_STR);
					$queryUpdateExistingAlliance->bindValue(':averageFriendCount',($existingAlliance->killCount ? $friendCount/$existingAlliance->killCount : 0),PDO::PARAM_STR);
					$queryUpdateExistingAlliance->bindValue(':averageEnemyCount',($existingAlliance->lossCount ? $enemyCount/$existingAlliance->lossCount : 0),PDO::PARAM_STR);
					if (!$queryUpdateExistingAlliance->execute())
						echo "DB error updating metadata for A'",$allianceMeta->allianceName,"' (",$allianceMeta->allianceId,"): ",$queryUpdateExistingAlliance->errorInfo()[2],"\n";
				}
				else
				{
					$queryInsertNewAlliance->bindValue(':allianceId',$allianceMeta->allianceId,PDO::PARAM_INT);
					$queryInsertNewAlliance->bindValue(':allianceName',$allianceMeta->allianceName,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':killCount',$allianceMeta->killCount,PDO::PARAM_INT);
					$queryInsertNewAlliance->bindValue(':lossCount',$allianceMeta->lossCount,PDO::PARAM_INT);
					$queryInsertNewAlliance->bindValue(':killValue',$allianceMeta->killValue,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':lossValue',$allianceMeta->lossValue,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':effectiveKillValue',$allianceMeta->effectiveKillValue,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':averageFriendCount',($allianceMeta->killCount ? $allianceMeta->friendCount/$allianceMeta->killCount : 0), PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':averageEnemyCount',($allianceMeta->lossCount ? $allianceMeta->enemyCount/$allianceMeta->lossCount : 0), PDO::PARAM_STR);
					if (!$queryInsertNewAlliance->execute())
						echo "DB error inserting metadata for A'",$allianceMeta->allianceName,"' (",$allianceMeta->allianceId,"): ",$queryInsertNewAlliance->errorInfo()[2],"\n";
				}
			}
		}
		$elapsed3 = PROFILE_elapsed($time3);
		echo "Done (",round($elapsed3,5)," sec)\n";
		$db->commit();
	}
	catch (PDOException $e)
	{
		echo 'DB Error in fetch: ',$e->getMessage();
		$db->rollback();
	}
	finally
	{
		$db->query('SET unique_checks=1');
	}
?>