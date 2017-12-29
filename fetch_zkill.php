<?php
  require(__DIR__.'/render/setup.inc.php');
	require(__DIR__.'/helpers/database.inc.php');
	require(__DIR__.'/helpers/http.inc.php');
  require(__DIR__.'/helpers/esi.inc.php');
	require(__DIR__.'/helpers/killmailHandling.inc.php');
	require(__DIR__.'/helpers/profiling.inc.php');
  
  $db = killfeedDB(false);
  $queryUpdateAPIURLData = prepareQuery($db,'UPDATE `api_urls` SET `latestKill`=:latestKill WHERE `urlId`=:urlId;');
  $queryUpdateAPIURLData->bindParam(':latestKill',$latestKillId,PDO::PARAM_INT);
  $queryUpdateAPIURLData->bindParam(':urlId',$urlID,PDO::PARAM_INT);
  
  try
  {
    $db->beginTransaction();
    $availableURLs = $db->query('SELECT `urlId`,`url`,`latestKill` FROM `api_urls`');
    
    $totalAPITime = 0;
    $totalMetadataTime = 0;
    $totalStatisticsTime = 0;
    $numKills = 0;
    $metadataCharacter = array();
    $metadataCorporation = array();
    $metadataAlliance = array();
    while ($urlInfo = $availableURLs->fetchObject())
    {
      $urlID = +$urlInfo->urlId;
      $baseUrl = $urlInfo->url.'orderDirection/desc/page/';
      $cutoffKillId = +$urlInfo->latestKill;
      $latestKillId = 0;
      $cutoffPageId = 0;
      
      echo "Now processing URL '", $urlInfo->url,"'...\n";
      
      $pageId = 1;
      while (!$cutoffPageId || $pageId <= $cutoffPageId)
      {
        echo "Requesting page ",$pageId,"...";
        $time1 = PROFILE_time();
        $text = httpRequestWithRetries($baseUrl.$pageId.'/',5);
        $data = json_decode($text);
        if (!$data)
        {
          doError("Failed to decode API response from page.\n",500);
          break;
        }
        $time2 = PROFILE_time();
        $elapsed1 = PROFILE_elapsed($time1,$time2);
        $totalAPITime += $elapsed1;
        echo "Done (",round($elapsed1,5)," sec)\n";
        
        if (empty($data))
        {
          echo "No metadata returned. Done with this URL.\n";
          break;
        }
        
        echo "Now processing kill metadata...\n";
        foreach ($data as $kill)
        {
          $killId = +$kill->killmail_id;
          if (!$cutoffPageId && ($killId <= $cutoffKillId))
            $cutoffPageId = $pageId+2; // arbitrary limit to try and catch mails being posted later
          
          ++$numKills;
          if (importKillmail($kill,MODE_ZKILLBOARD,$metadataCharacter,$metadataCorporation,$metadataAlliance) && $killId > $latestKillId)
            $latestKillId = $killId;
        }
        $elapsed2 = PROFILE_ELAPSED($time2);
        $totalMetadataTime += $elapsed2;
        echo "Done (",round($elapsed2,5)," sec)\n";
        ++$pageId;
      }
      $queryUpdateAPIURLData->execute();
    }
      
    echo "Fetch complete. We asked CREST for item data $CRESTPullCount times, and retrieved value data for $EVECentralPullCount types from eve-central.com.\n";
		echo "$numKills kills successfully inserted.\n";
		echo "We spent ",round($totalAPITime,5)," seconds fetching data from the zKillboard API and ",round($totalMetadataTime,5)," seconds inserting metadata.\n\n";
		$time3 = PROFILE_time();
		echo "Now updating statistics...";
    $querySelectExistingCharacter = prepareQuery($db,'SELECT `characterName`, `killCount`, `lossCount`, `killValue`, `effectiveKillValue`, `lossValue`, `averageFriendCount`, `averageEnemyCount` FROM `character_metadata` WHERE `characterId` = :characterId FOR UPDATE');
		$queryUpdateExistingCharacter = prepareQuery($db,'UPDATE `character_metadata` SET `corporationId`=:corporationId, `allianceId`=:allianceId, `killCount`=:killCount, `lossCount`=:lossCount, `killValue`=:killValue, `effectiveKillValue`=:effectiveKillValue, `lossValue`=:lossValue, `averageFriendCount`=:averageFriendCount, `averageEnemyCount`=:averageEnemyCount, `averageKillValue`=CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, `averageLossValue`=CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END WHERE `characterId`=:characterId');
		$queryInsertNewCharacter = prepareQuery($db,'INSERT INTO `character_metadata` (`characterId`,`characterName`,`corporationId`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (:characterId, :characterName, :corporationId, :allianceId, :killCount, :lossCount, :killValue, :effectiveKillValue, :lossValue, :averageFriendCount, CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, :averageEnemyCount, CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END)');
		foreach ($metadataCharacter as &$characterMeta)
		{
			$querySelectExistingCharacter->bindValue(':characterId',$characterMeta->characterId,PDO::PARAM_INT);
			if (!$querySelectExistingCharacter->execute())
				echo "DB error updating metadata for P'",$characterMeta->Id,"' (",$characterMeta->characterId,"): ",$querySelectExistingCharacter->errorInfo()[2],"\n";
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
						echo "DB error updating metadata for P'",$characterMeta->characterId,"' (",$characterMeta->characterId,"): ",$queryUpdateExistingCharacter->errorInfo()[2],"\n";
          else
            echo "Character #",$characterMeta->characterId,": ",$existingCharacter->characterName."\n";
				}
				else
				{
          $characterName = getCharacterName($characterMeta->characterId);
					$queryInsertNewCharacter->bindValue(':characterId',$characterMeta->characterId,PDO::PARAM_INT);
					$queryInsertNewCharacter->bindValue(':characterName',$characterName,PDO::PARAM_STR);
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
						echo "DB error inserting metadata for P'",$characterMeta->characterId,"' (",$characterMeta->characterId,"): ",$queryInsertNewCharacter->errorInfo()[2],"\n";
          else
            echo "Character #",$characterMeta->characterId,": ",$characterName,"\n";
				}
			}
		}
		
		$querySelectExistingCorporation = prepareQuery($db,'SELECT `corporationName`, `killCount`, `lossCount`, `killValue`, `effectiveKillValue`, `lossValue`, `averageFriendCount`, `averageEnemyCount` FROM `corporation_metadata` WHERE `corporationId` = :corporationId FOR UPDATE');
		$queryUpdateExistingCorporation = prepareQuery($db,'UPDATE `corporation_metadata` SET `killCount`=:killCount, `lossCount`=:lossCount, `killValue`=:killValue, `effectiveKillValue`=:effectiveKillValue, `lossValue`=:lossValue, `averageFriendCount`=:averageFriendCount, `averageEnemyCount`=:averageEnemyCount, `averageKillValue`=CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, `averageLossValue`=CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END WHERE `corporationId`=:corporationId');
		$queryInsertNewCorporation = prepareQuery($db,'INSERT INTO `corporation_metadata` (`corporationId`,`corporationName`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (:corporationId, :corporationName, :allianceId, :killCount, :lossCount, :killValue, :effectiveKillValue, :lossValue, :averageFriendCount, CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, :averageEnemyCount, CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END)');
		foreach ($metadataCorporation as &$corporationMeta)
		{
			$querySelectExistingCorporation->bindValue(':corporationId',$corporationMeta->corporationId,PDO::PARAM_INT);
			if (!$querySelectExistingCorporation->execute())
				echo "DB error updating metadata for C'",$corporationMeta->corporationId,"' (",$corporationMeta->corporationId,"): ",$querySelectExistingCorporation->errorInfo()[2],"\n";
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
						echo "DB error updating metadata for C'",$corporationMeta->corporationId,"' (",$corporationMeta->corporationId,"): ",$queryUpdateExistingCorporation->errorInfo()[2],"\n";
          else
            echo "Corporation #",$corporationMeta->corporationId,": ",$existingCorporation->corporationName,"\n";
				}
				else
				{
          $corporationName = getCorporationName($corporationMeta->corporationId);
					$queryInsertNewCorporation->bindValue(':corporationId',$corporationMeta->corporationId,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':corporationName',$corporationName,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':allianceId',$corporationMeta->allianceId,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':killCount',$corporationMeta->killCount,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':lossCount',$corporationMeta->lossCount,PDO::PARAM_INT);
					$queryInsertNewCorporation->bindValue(':killValue',$corporationMeta->killValue,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':lossValue',$corporationMeta->lossValue,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':effectiveKillValue',$corporationMeta->effectiveKillValue,PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':averageFriendCount',($corporationMeta->killCount ? $corporationMeta->friendCount/$corporationMeta->killCount : 0), PDO::PARAM_STR);
					$queryInsertNewCorporation->bindValue(':averageEnemyCount',($corporationMeta->lossCount ? $corporationMeta->enemyCount/$corporationMeta->lossCount : 0), PDO::PARAM_STR);
					if (!$queryInsertNewCorporation->execute())
						echo "DB error inserting metadata for C'",$corporationMeta->corporationId,"' (",$corporationMeta->corporationId,"): ",$queryInsertNewCorporation->errorInfo()[2],"\n";
          else
            echo "Corporation #",$corporationMeta->corporationId,": ",$corporationName,"\n";
				}
			}
		}
		
		$querySelectExistingAlliance = prepareQuery($db,'SELECT `allianceName`, `killCount`, `lossCount`, `killValue`, `effectiveKillValue`, `lossValue`, `averageFriendCount`, `averageEnemyCount` FROM `alliance_metadata` WHERE `allianceId` = :allianceId FOR UPDATE');
		$queryUpdateExistingAlliance = prepareQuery($db,'UPDATE `alliance_metadata` SET `killCount`=:killCount, `lossCount`=:lossCount, `killValue`=:killValue, `effectiveKillValue`=:effectiveKillValue, `lossValue`=:lossValue, `averageFriendCount`=:averageFriendCount, `averageEnemyCount`=:averageEnemyCount, `averageKillValue`=CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, `averageLossValue`=CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END WHERE `allianceId`=:allianceId');
		$queryInsertNewAlliance = prepareQuery($db,'INSERT INTO `alliance_metadata` (`allianceId`,`allianceName`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (:allianceId, :allianceName, :killCount, :lossCount, :killValue, :effectiveKillValue, :lossValue, :averageFriendCount, CASE :killCount WHEN 0 THEN 0 ELSE :killValue/:killCount END, :averageEnemyCount, CASE :lossCount WHEN 0 THEN 0 ELSE :lossValue/:lossCount END)');
		foreach ($metadataAlliance as &$allianceMeta)
		{
			$querySelectExistingAlliance->bindValue(':allianceId',$allianceMeta->allianceId,PDO::PARAM_INT);
			if (!$querySelectExistingAlliance->execute())
				echo "DB error updating metadata for A'",$allianceMeta->allianceId,"' (",$allianceMeta->allianceId,"): ",$querySelectExistingAlliance->errorInfo()[2],"\n";
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
						echo "DB error updating metadata for A'",$allianceMeta->allianceId,"' (",$allianceMeta->allianceId,"): ",$queryUpdateExistingAlliance->errorInfo()[2],"\n";
          else
            echo "Alliance #",$allianceMeta->allianceId,": ",$existingAlliance->allianceName,"\n";
				}
				else
				{
          $allianceName = getAllianceName($allianceMeta->allianceId);
					$queryInsertNewAlliance->bindValue(':allianceId',$allianceMeta->allianceId,PDO::PARAM_INT);
					$queryInsertNewAlliance->bindValue(':allianceName',$allianceName,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':killCount',$allianceMeta->killCount,PDO::PARAM_INT);
					$queryInsertNewAlliance->bindValue(':lossCount',$allianceMeta->lossCount,PDO::PARAM_INT);
					$queryInsertNewAlliance->bindValue(':killValue',$allianceMeta->killValue,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':lossValue',$allianceMeta->lossValue,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':effectiveKillValue',$allianceMeta->effectiveKillValue,PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':averageFriendCount',($allianceMeta->killCount ? $allianceMeta->friendCount/$allianceMeta->killCount : 0), PDO::PARAM_STR);
					$queryInsertNewAlliance->bindValue(':averageEnemyCount',($allianceMeta->lossCount ? $allianceMeta->enemyCount/$allianceMeta->lossCount : 0), PDO::PARAM_STR);
					if (!$queryInsertNewAlliance->execute())
						echo "DB error inserting metadata for A'",$allianceMeta->allianceId,"' (",$allianceMeta->allianceId,"): ",$queryInsertNewAlliance->errorInfo()[2],"\n";
          else
            echo "Alliance #",$allianceMeta->allianceId,": ",$allianceName,"\n";
				}
			}
		}
		$elapsed3 = PROFILE_elapsed($time3);
		echo "Done (",round($elapsed3,5)," sec)\n";
		$db->commit();
  }
  catch (PDOException $e)
  {
    echo 'DB error while fetching: ',$e->getMessage();
    $db->rollback();
  }
  finally
  {
    $db->query('SET unique_checks=1');
  }
?>