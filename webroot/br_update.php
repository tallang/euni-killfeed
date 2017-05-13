<?php
	header('Content-Type: application/json');
	try
	{
		require(__DIR__.'/../render/setup.inc.php');
		$reportId = isset($_POST['reportId']) ? (int)$_POST['reportId'] : 0;
		if (!$reportId)
			throw new RuntimeException('no id specified');
		$doReassign = isset($_POST['reassign']);
		
		require(__DIR__.'/../helpers/database.inc.php');
		require(__DIR__.'/../helpers/session.inc.php');
		require(__DIR__.'/../helpers/auth.inc.php');
		
		if (!isLoggedIn())
			throw new RuntimeException('Not Authorized');
		
		$db = killfeedDB();
		$db->beginTransaction();
		$queryGetReportMeta = prepareQuery($db,'SELECT `ownerCharacterId`,IFNULL(TIME_TO_SEC(TIMEDIFF(UTC_TIMESTAMP(),`lastRefreshed`)),3600) as `secondsSinceRefresh` FROM `battlereport_meta` WHERE `reportId`=:reportId FOR UPDATE');
		$queryGetReportMeta->bindValue(':reportId',$reportId,PDO::PARAM_INT);
		executeQuery($queryGetReportMeta);
		if (!$queryGetReportMeta->rowCount())
			throw new RuntimeException('Not Authorized');
		$reportMeta = $queryGetReportMeta->fetchObject();
		if ((+$reportMeta->ownerCharacterId !== getCurrentUserCharacterId()) && !currentUserIsAdmin())
			throw new RuntimeException('Not Authorized');
		if ((+$reportMeta->secondsSinceRefresh <= 300)) // 5 minutes
		{
			$refreshTimer = (300-(+$reportMeta->secondsSinceRefresh));
			throw new RuntimeException();
		}
		
		require(__DIR__.'/../helpers/battlereportHandling.inc.php');
		if (isset($_POST['data']))
			updateAssignmentsForReport($reportId,json_decode($_POST['data']));

		$killList = getKillIdListForReport($reportId);
		$verifiedAssignments = verifyAssignmentsForReport($killList,getAssignmentsForReport($reportId));
		
		$data = new stdClass();
		if ($doReassign || isset($verifiedAssignments->unassigned))
		{
			$data->status = 'assign-request';
			$queryGetCharacterName = prepareQuery($db,'SELECT `characterName` FROM `character_metadata` WHERE `characterId`=:characterId');
			$queryGetCorporationName = prepareQuery($db,'SELECT `corporationName` FROM `corporation_metadata` WHERE `corporationId`=:corporationId');
			$queryGetAllianceName = prepareQuery($db,'SELECT `allianceName` FROM `alliance_metadata` WHERE `allianceId`=:allianceId');
			
			if (isset($verifiedAssignments->unassigned))
			{
				$data->unassigned = new stdClass();
				$data->unassigned->character = array();
				$data->unassigned->corporation = array();
				$data->unassigned->alliance = array();			
				foreach ($verifiedAssignments->unassigned->character as $charId => &$dummy)
				{
					$obj = new stdClass();
					$obj->characterId = $charId;
					$queryGetCharacterName->bindValue(':characterId',$charId,PDO::PARAM_INT);
					executeQuery($queryGetCharacterName);
					$obj->characterName = $queryGetCharacterName->fetchColumn();
					$data->unassigned->character[] = $obj;
				}
				unset($dummy);
				foreach ($verifiedAssignments->unassigned->corporation as $corpId => &$dummy)
				{
					$obj = new stdClass();
					$obj->corporationId = $corpId;
					$queryGetCorporationName->bindValue(':corporationId',$corpId,PDO::PARAM_INT);
					executeQuery($queryGetCorporationName);
					$obj->corporationName = $queryGetCorporationName->fetchColumn();
					$data->unassigned->corporation[] = $obj;
				}
				unset($dummy);
				foreach ($verifiedAssignments->unassigned->alliance as $allianceId => &$dummy)
				{
					$obj = new stdClass();
					$obj->allianceId = $allianceId;
					$queryGetAllianceName->bindValue(':allianceId',$allianceId,PDO::PARAM_INT);
					executeQuery($queryGetAllianceName);
					$obj->allianceName = $queryGetAllianceName->fetchColumn();
					$data->unassigned->alliance[] = $obj;
				}
				unset($dummy);
			}
			
			$data->assigned = new stdClass();
			$data->assigned->character = array();
			$data->assigned->corporation = array();
			$data->assigned->alliance = array();
			foreach ($verifiedAssignments->assigned->character as $charId => &$sideId)
			{
				$obj = new stdClass();
				$obj->characterId = $charId;
				$queryGetCharacterName->bindValue(':characterId',$charId,PDO::PARAM_INT);
				executeQuery($queryGetCharacterName);
				$obj->characterName = $queryGetCharacterName->fetchColumn();
				$obj->sideId = $sideId;
				$data->assigned->character[] = $obj;
			}
			unset($sideId);
			foreach ($verifiedAssignments->assigned->corporation as $corpId => &$sideId)
			{
				$obj = new stdClass();
				$obj->corporationId = $corpId;
				$queryGetCorporationName->bindValue(':corporationId',$corpId,PDO::PARAM_INT);
				executeQuery($queryGetCorporationName);
				$obj->corporationName = $queryGetCorporationName->fetchColumn();
				$obj->sideId = $sideId;
				$data->assigned->corporation[] = $obj;
			}
			unset($sideId);
			foreach ($verifiedAssignments->assigned->alliance as $allianceId => &$sideId)
			{
				$obj = new stdClass();
				$obj->allianceId = $allianceId;
				$queryGetAllianceName->bindValue(':allianceId',$allianceId,PDO::PARAM_INT);
				executeQuery($queryGetAllianceName);
				$obj->allianceName = $queryGetAllianceName->fetchColumn();
				$obj->sideId = $sideId;
				$data->assigned->alliance[] = $obj;
			}
			unset($sideId);
			
			if (isset($verifiedAssignments->inherited))
			{
				foreach ($verifiedAssignments->inherited->character as $charId => &$dummy)
				{
					$obj = new stdClass();
					$obj->characterId = $charId;
					$queryGetCharacterName->bindValue(':characterId',$charId,PDO::PARAM_INT);
					executeQuery($queryGetCharacterName);
					$obj->characterName = $queryGetCharacterName->fetchColumn();
					$data->assigned->character[] = $obj;
				}
				unset($dummy);
				foreach ($verifiedAssignments->inherited->corporation as $corpId => &$dummy)
				{
					$obj = new stdClass();
					$obj->corporationId = $corpId;
					$queryGetCorporationName->bindValue(':corporationId',$corpId,PDO::PARAM_INT);
					executeQuery($queryGetCorporationName);
					$obj->corporationName = $queryGetCorporationName->fetchColumn();
					$data->assigned->corporation[] = $obj;
				}
				unset($dummy);
				foreach ($verifiedAssignments->inherited->alliance as $allianceId => &$dummy)
				{
					$obj = new stdClass();
					$obj->allianceId = $allianceId;
					$queryGetAllianceName->bindValue(':allianceId',$allianceId,PDO::PARAM_INT);
					executeQuery($queryGetAllianceName);
					$obj->allianceName = $queryGetAllianceName->fetchColumn();
					$data->assigned->alliance[] = $obj;
				}
				unset($dummy);
			}
		}
		else
		{
			$data->status = 'done';
			updateBattleReport($reportId, $killList, $verifiedAssignments->assigned);
			$queryUpdateRefreshTime = prepareQuery($db,'UPDATE `battlereport_meta` SET `lastRefreshed`=UTC_TIMESTAMP() WHERE `reportId`=:reportId');
			$queryUpdateRefreshTime->bindValue(':reportId',$reportId);
			executeQuery($queryUpdateRefreshTime);
		}
		$db->commit();
		echo json_encode($data);
	}
	catch (PDOException $e)
	{
		doError('DB error updating battle report: '.$e->getMessage(),500);
		throw new RuntimeException('Database Error');
	}
	catch (RuntimeException $e)
	{
		if (isset($db))
			$db->rollback();
		
		$data = new stdClass();
		$data->status = 'nok';
		if (isset($refreshTimer))
			$data->refreshDelay = $refreshTimer;
		else
			$data->error = $e->getMessage();
		
		echo json_encode($data);
	}
?>