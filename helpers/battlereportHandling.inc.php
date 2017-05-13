<?php
	require_once(__DIR__.'/database.inc.php');
	require_once(__DIR__.'/iskvalue.inc.php');
	require_once(__DIR__.'/slotinfo.inc.php');
	
	function updateAssignmentsForReport($reportId,$assignments)
	{
		try
		{
			$db = killfeedDB();			
			$queryInsertAssignment = prepareQuery($db,'INSERT INTO `battlereport_side_assignments` (`reportId`,`entityType`,`entityId`,`sideId`) VALUES (:reportId,:entityType,:entityId,:sideId)');
			$queryInsertAssignment->bindValue(':reportId',$reportId);
			$queryUpdateAssignment = prepareQuery($db,'UPDATE `battlereport_side_assignments` SET `sideId`=:sideId WHERE `reportId`=:reportId AND `entityType`=:entityType AND `entityId`=:entityId');
			$queryUpdateAssignment->bindValue(':reportId',$reportId);
			$queryDeleteAssignment = prepareQuery($db,'DELETE FROM `battlereport_side_assignments` WHERE `reportId`=:reportId AND `entityType`=:entityType AND `entityId`=:entityId');
			$queryDeleteAssignment->bindValue(':reportId',$reportId);
			
			foreach ($assignments as &$data)
			{ // [entityType, entityId, sideSelected, originalSide]
				if ($data[2] === $data[3])
					continue;
				if ($data[2] === null)
				{ // delete
					$queryDeleteAssignment->bindValue(':entityType',$data[0],PDO::PARAM_STR);
					$queryDeleteAssignment->bindValue(':entityId',+$data[1],PDO::PARAM_INT);
					executeQuery($queryDeleteAssignment);
				}
				elseif ($data[3] === null)
				{ // insert
					$queryInsertAssignment->bindValue(':entityType',$data[0],PDO::PARAM_STR);
					$queryInsertAssignment->bindValue(':entityId',$data[1],PDO::PARAM_INT);
					$queryInsertAssignment->bindValue(':sideId',$data[2],PDO::PARAM_INT);
					executeQuery($queryInsertAssignment);
				}
				else
				{ // update
					$queryUpdateAssignment->bindValue(':entityType',$data[0],PDO::PARAM_STR);
					$queryUpdateAssignment->bindValue(':entityId',$data[1],PDO::PARAM_INT);
					$queryUpdateAssignment->bindValue(':sideId',$data[2],PDO::PARAM_INT);
					executeQuery($queryUpdateAssignment);
				}
			}
			unset($data);
		}
		catch (PDOException $e)
		{
			doError('DB error updating assignments: '.$e->getMessage(), 500);
			throw new RuntimeException('Database Error');
		}
	}
	
	function getKillIdListForReport($reportId)
	{ // returns string: id,id,id,id,id
		$killIds = array();
		$db = killfeedDB();
		try
		{
			$queryGetReportSources = prepareQuery($db,'SELECT `solarSystemId`,`startTime`,`endTime` FROM `battlereport_sources` WHERE `reportId`=:reportId');
			$queryGetReportSources->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			executeQuery($queryGetReportSources);
			$queryGetKillsForSource = prepareQuery($db,'SELECT
															`lookup`.`killId` as `killId`
														FROM `solarsystem_kill_history` as `lookup`
														LEFT JOIN `kill_metadata` as `meta`
															ON `lookup`.`killId`=`meta`.`id`
														WHERE `lookup`.`solarSystemId` = :solarSystemId
														AND `lookup`.`killId` BETWEEN
															ifnull((SELECT `day1`.`firstKillId` FROM `kill_day_history` as `day1` WHERE `day1`.`day`=DATE(:startTime)),0) AND
															ifnull((SELECT `day2`.`lastKillId` FROM `kill_day_history` as `day2` WHERE `day2`.`day`=DATE(:endTime)),4294967295)
														AND `meta`.`killTime` BETWEEN :startTime AND :endTime');
			while ($source = $queryGetReportSources->fetchObject())
			{
				$queryGetKillsForSource->bindValue(':solarSystemId',+$source->solarSystemId,PDO::PARAM_INT);
				$queryGetKillsForSource->bindValue(':startTime',$source->startTime,PDO::PARAM_STR);
				$queryGetKillsForSource->bindValue(':endTime',$source->endTime,PDO::PARAM_STR);
				executeQuery($queryGetKillsForSource);
				while ($kill = $queryGetKillsForSource->fetchObject())
					$killIds[+$kill->killId] = true;
			}
			$queryGetSourceListForReport = prepareQuery($db,'SELECT `killId`,`isWhitelist` FROM `battlereport_source_lists` WHERE `reportId`=:reportId ORDER BY `isWhiteList` asc');
			$queryGetSourceListForReport->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			executeQuery($queryGetSourceListForReport);
			while ($listEntry = $queryGetSourceListForReport->fetchObject())
			{
				if ($listEntry->isWhitelist === '1')
					$killIds[+$listEntry->killId] = true;
				else
					unset($killIds[+$listEntry->killId]);
			}
		}
		catch (PDOException $e)
		{
			doError("Error getting kill ID list for $reportId: ".$e->getMessage(), 500);
			return '0';
		}
		if (empty($killIds))
			return '0';
		else
			return implode(',',array_keys($killIds));
	}
	
	function getAssignmentsForReport($reportId)
	{
		$db = killfeedDB();
		
		$assignments = new stdClass();
		$assignments->character = array();
		$assignments->corporation = array();
		$assignments->alliance = array();
		try
		{
			static $getReportSidesQuery = null;
			if (!$getReportSidesQuery)
				$getReportSidesQuery = prepareQuery($db,'SELECT `entityType`,`entityId`,`sideId` FROM `battlereport_side_assignments` WHERE `reportId` = :reportId');
			$getReportSidesQuery->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			if (!$getReportSidesQuery->execute())
				throw new PDOException($getReportSidesQuery->errorInfo()[2]);
			while ($entity = $getReportSidesQuery->fetchObject())
				$assignments->{$entity->entityType}[+$entity->entityId] = +$entity->sideId;
		}
		catch (PDOException $e)
		{
			doError('DB error getting assignments for report: '.$e->getMessage(),500);
			die();
		}
		return $assignments;
	}
	
	function verifyAssignmentsForReport($killList,$assignments)
	{
		$db = killfeedDB();
		
		$verified = new stdClass();
		$verified->assigned = $assignments;
		try
		{
			foreach ($db->query('SELECT `victimCharacterId` as `characterId`, `victimCorporationId` as `corporationId`, `victimAllianceId` as `allianceId` FROM `kill_metadata` WHERE `id` IN ('.$killList.') UNION DISTINCT SELECT `killerCharacterId` as `characterId`, `killerCorporationId` as `corporationId`, `killerAllianceId` as `allianceId` FROM `kill_killers` WHERE `killId` IN ('.$killList.')',PDO::FETCH_CLASS,'DBEntityMetadata') as $character)
			{
				if (!$character->characterId)
					continue;
				$hasAllianceAssignment = $character->allianceId && isset($assignments->alliance[$character->allianceId]);
				$hasCorporationAssignment = $character->corporationId && isset($assignments->corporation[$character->corporationId]);
				$hasCharacterAssignment = isset($assignments->character[$character->characterId]);
				if ($hasAllianceAssignment || $hasCorporationAssignment || $hasCharacterAssignment)
				{
					if (!isset($verified->inherited) && (!$hasAllianceAssignment || !$hasCorporationAssignment || !$hasCharacterAssignment))
					{
						$verified->inherited = new stdClass();
						$verified->inherited->character = array();
						$verified->inherited->corporation = array();
						$verified->inherited->alliance = array();
					}
					if (!$hasAllianceAssignment && $character->allianceId)
						$verified->inherited->alliance[$character->allianceId] = true;
					if (!$hasCorporationAssignment && $character->corporationId)
						$verified->inherited->corporation[$character->corporationId] = true;
					if (!$hasCharacterAssignment)
						$verified->inherited->character[$character->characterId] = true;
					continue;
				}
				if (!isset($verified->unassigned))
				{
					$verified->unassigned = new stdClass();
					$verified->unassigned->character = array();
					$verified->unassigned->corporation = array();
					$verified->unassigned->alliance = array();
				}
				$verified->unassigned->character[$character->characterId] = true;
				if ($character->corporationId)
					$verified->unassigned->corporation[$character->corporationId] = true;
				if ($character->allianceId)
					$verified->unassigned->alliance[$character->allianceId] = true;
			}
		}
		catch (Exception $e)
		{
			doError('Error while verifying assignments: '.$db->errorInfo()[2],200);
			unset($verified->unassigned);
		}
		return $verified;
	}
	
	function getSideAssignmentFor($assignments, $characterId, $corporationId, $allianceId)
	{
		if (isset($assignments->character[$characterId]))
			return $assignments->character[$characterId];
		if (isset($assignments->corporation[$corporationId]))
			return $assignments->corporation[$corporationId];
		if (isset($assignments->alliance[$allianceId]))
			return $assignments->alliance[$allianceId];
		return 0;
	}
	
	class BattleReportSide
	{
		public $mainAllianceId = 0;
		public $mainCorporationId = 0;
		public $killValue = 0;
		public $effectiveKillValue = 0;
		public $lossValue = 0;
		public $ddShips = array();
		public $logiShips = array();
	}
	
	function updateBattleReport($reportId, $killList, $assignments)
	{
		$db = killfeedDB();
		try
		{
			$queryDeleteInvolved = prepareQuery($db, 'DELETE FROM `battlereport_involved` WHERE `reportId` = :reportId');
			$queryDeleteInvolved->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			executeQuery($queryDeleteInvolved);
			$queryDeleteInvolvedFittings = prepareQuery($db, 'DELETE FROM `battlereport_involved_fittings` WHERE `reportId` = :reportId');
			$queryDeleteInvolvedFittings->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			executeQuery($queryDeleteInvolvedFittings);
			
			$queryGetExistingSides = prepareQuery($db, 'SELECT `sideId`,`mainAllianceId`,`mainCorporationId` FROM `battlereport_sides` WHERE `reportId`=:reportId');
			$queryGetExistingSides->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			executeQuery($queryGetExistingSides);
			
			$sides = array();
			while ($obj = $queryGetExistingSides->fetchObject('DBReportSideMeta'))
			{
				$sides[$obj->sideId] = new BattleReportSide();
				$sides[$obj->sideId]->mainAllianceId = $obj->mainAllianceId;
				$sides[$obj->sideId]->mainCorporationId = $obj->mainCorporationId;
			}
			$queryDeleteExistingSides = prepareQuery($db, 'DELETE FROM `battlereport_sides` WHERE `reportId` = :reportId');
			$queryDeleteExistingSides->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			executeQuery($queryDeleteExistingSides);
			
			$sideHadCreditForKill = array();
			$involved = array();
			$queryGetKillers = $db->query('SELECT
											`killer`.`killId` as `killId`,
											`killer`.`killerCharacterId` as `killerCharacterId`,
											`killer`.`killerShipTypeId` as `killerShipTypeId`,
											`killer`.`killerWeaponTypeId` as `killerWeaponTypeId`,
											`killer`.`killerCorporationId` as `killerCorporationId`,
											`killer`.`killerAllianceId` as `killerAllianceId`,
											`kill`.`valueTotal` as `killValue`,
											`killer`.`damageFractional` as `damageFractional`,
											`kill`.`victimCharacterId` as `victimCharacterId`,
											`kill`.`victimCorporationId` as `victimCorporationId`,
											`kill`.`victimAllianceId` as `victimAllianceId`,
											`kill`.`shipTypeId` as `victimShipTypeId`
										FROM `kill_killers` as `killer`
										LEFT JOIN `kill_metadata` as `kill`
											ON `killer`.`killId` = `kill`.`id`
										WHERE `killer`.`killId` IN ('.$killList.')');
			while ($killer = $queryGetKillers->fetchObject('DBKillKiller'))
			{
				$killerSide = getSideAssignmentFor($assignments, $killer->killerCharacterId, $killer->killerCorporationId, $killer->killerAllianceId);
				$victimSide = getSideAssignmentFor($assignments, +$killer->victimCharacterId, +$killer->victimCorporationId, +$killer->victimAllianceId);
				if (!$killerSide || !$victimSide)
					continue;
				// we only count this for most player-specific statistics if it's not team killing (or pod kills, discourage whoring)
				$countKill = (($killerSide != $victimSide) && !isShipTrivial(+$killer->victimShipTypeId));
				// make sure our arrays are initialized
				if (!isset($sides[$killerSide]))
					$sides[$killerSide] = new BattleReportSide();
				if (!isset($involved[$killer->killerCharacterId]))
					$involved[$killer->killerCharacterId] = array();
				if (!isset($involved[$killer->killerCharacterId][$killer->killerShipTypeId]))
				{
					$involved[$killer->killerCharacterId][$killer->killerShipTypeId] = array('hadLoss' => false, 'side' => $killerSide, 'corporationId' => $killer->killerCorporationId, 'allianceId' => $killer->killerAllianceId, 'count' => 0, 'fittings' => array());
					// if this is the first the player+ship combo shows up on this team, add ship type to side data
					if (isShipLogistics($killer->killerShipTypeId))
					{
						if (!isset($sides[$killerSide]->logiShips[$killer->killerShipTypeId]))
							$sides[$killerSide]->logiShips[$killer->killerShipTypeId] = 1;
						else
							++$sides[$killerSide]->logiShips[$killer->killerShipTypeId];
					}
					elseif (!isShipTrivial($killer->killerShipTypeId))
					{
						if (!isset($sides[$killerSide]->ddShips[$killer->killerShipTypeId]))
							$sides[$killerSide]->ddShips[$killer->killerShipTypeId] = 1;
						else
							++$sides[$killerSide]->ddShips[$killer->killerShipTypeId];
					}
				}
				// don't count team kills or pods
				if ($countKill && !isShipTrivial(+$killer->victimShipTypeId))
					++$involved[$killer->killerCharacterId][$killer->killerShipTypeId]['count'];
				// fitting info
				if ($killer->killerWeaponTypeId && $killer->killerWeaponTypeId != $killer->killerShipTypeId)
				{
					if (!isset($involved[$killer->killerCharacterId][$killer->killerShipTypeId]['fittings'][$killer->killerWeaponTypeId]))
						$involved[$killer->killerCharacterId][$killer->killerShipTypeId]['fittings'][$killer->killerWeaponTypeId] = 0;
					if ($countKill)
						++$involved[$killer->killerCharacterId][$killer->killerShipTypeId]['fittings'][$killer->killerWeaponTypeId];
				}
				// add value to side info
				if ($killerSide != $victimSide)
				{
					if (!isset($sideHadCreditForKill[$killerSide]))
						$sideHadCreditForKill[$killerSide] = array();
					if (!isset($sideHadCreditForKill[$killerSide][$killer->killId]))
					{
						$sides[$killerSide]->killValue += +$killer->killValue;
						$sideHadCreditForKill[$killerSide][$killer->killId] = true;
					}
					$sides[$killerSide]->effectiveKillValue += (+$killer->killValue * ($killer->damageFractional/100));
				}
			}
			
			$queryInsertInvolvedEntry = prepareQuery($db,'INSERT INTO `battlereport_involved` (`reportId`,`sideId`,`characterId`,`corporationId`,`allianceId`,`shipTypeId`,`shipValue`,`relatedKillId`,`numInvolved`) VALUES (:reportId,:sideId,:characterId,:corporationId,:allianceId,:shipTypeId,:shipValue,:relatedKillId,:numInvolved)');
			$queryInsertInvolvedEntry->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			$queryGetShipLosses = $db->query('SELECT `id`,`victimCharacterId`,`victimCorporationId`,`victimAllianceId`,`shipTypeId`,`valueTotal` FROM `kill_metadata` WHERE `id` IN ('.$killList.')');
			while ($kill = $queryGetShipLosses->fetchObject('DBKillMetadata'))
			{
				$side = getSideAssignmentFor($assignments, $kill->victimCharacterId, $kill->victimCorporationId, $kill->victimAllianceId);
				if (!$side)
					continue;
				if (!isset($sides[$side]))
					$sides[$side] = new BattleReportSide();
				// losses count regardless of who killed them
				$sides[$side]->lossValue += $kill->valueTotal;
				
				// if it didn't get counted in killer loop, add ship to side statistics now
				// also add ship if this is the second time its type shows up in a loss mail for the player (reships)
				if (!isset($involved[$kill->victimCharacterId][$kill->shipTypeId]) || $involved[$kill->victimCharacterId][$kill->shipTypeId]['hadLoss'])
				{
					if (isShipLogistics($kill->shipTypeId))
					{
						if (!isset($sides[$side]->logiShips[$kill->shipTypeId]))
							$sides[$side]->logiShips[$kill->shipTypeId] = 1;
						else
							++$sides[$side]->logiShips[$kill->shipTypeId];
					}
					elseif (!isShipTrivial($kill->shipTypeId))
					{
						if (!isset($sides[$side]->ddShips[$kill->shipTypeId]))
							$sides[$side]->ddShips[$kill->shipTypeId] = 1;
						else
							++$sides[$side]->ddShips[$kill->shipTypeId];
					}
				}
				else // otherwise mark it so we don't generate a non-loss involved entry for it
					$involved[$kill->victimCharacterId][$kill->shipTypeId]['hadLoss'] = true;
					
				// insert loss involved entry into DB
				$queryInsertInvolvedEntry->bindValue(':sideId',$side,PDO::PARAM_INT);
				$queryInsertInvolvedEntry->bindValue(':characterId',$kill->victimCharacterId,PDO::PARAM_INT);
				$queryInsertInvolvedEntry->bindValue(':corporationId',$kill->victimCorporationId,PDO::PARAM_INT);
				$queryInsertInvolvedEntry->bindValue(':allianceId',$kill->victimAllianceId,PDO::PARAM_INT);
				$queryInsertInvolvedEntry->bindValue(':shipTypeId',$kill->shipTypeId,PDO::PARAM_INT);
				$queryInsertInvolvedEntry->bindValue(':shipValue',$kill->valueTotal,PDO::PARAM_STR);
				$queryInsertInvolvedEntry->bindValue(':relatedKillId',$kill->id,PDO::PARAM_INT);
				if (isset($involved[$kill->victimCharacterId][$kill->shipTypeId]))
					$queryInsertInvolvedEntry->bindValue(':numInvolved',$involved[$kill->victimCharacterId][$kill->shipTypeId]['count'],PDO::PARAM_INT);
				else
					$queryInsertInvolvedEntry->bindValue(':numInvolved',0,PDO::PARAM_INT);
				executeQuery($queryInsertInvolvedEntry);
			}
			
			$queryInsertInvolvedFitting = prepareQuery($db,'INSERT INTO `battlereport_involved_fittings` (`reportId`,`sideId`,`characterId`,`shipTypeId`,`weaponTypeId`,`numOccurrence`) VALUES (:reportId,:sideId,:characterId,:shipTypeId,:weaponTypeId,:numOccurrence)');
			$queryInsertInvolvedFitting->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			foreach ($involved as $characterId => &$characterData)
				foreach ($characterData as $shipTypeId => &$shipData)
				{
					if (!$shipData['hadLoss'])
					{
						$queryInsertInvolvedEntry->bindValue(':sideId',$shipData['side'],PDO::PARAM_INT);
						$queryInsertInvolvedEntry->bindValue(':characterId',$characterId,PDO::PARAM_INT);
						$queryInsertInvolvedEntry->bindValue(':corporationId',$shipData['corporationId'],PDO::PARAM_INT);
						$queryInsertInvolvedEntry->bindValue(':allianceId',$shipData['allianceId'],PDO::PARAM_INT);
						$queryInsertInvolvedEntry->bindValue(':shipTypeId',$shipTypeId,PDO::PARAM_INT);
						$queryInsertInvolvedEntry->bindValue(':shipValue',getValueForType($shipTypeId),PDO::PARAM_STR);
						$queryInsertInvolvedEntry->bindValue(':relatedKillId',0,PDO::PARAM_INT);
						$queryInsertInvolvedEntry->bindValue(':numInvolved',$shipData['count'],PDO::PARAM_INT);
						executeQuery($queryInsertInvolvedEntry);
					}
					$queryInsertInvolvedFitting->bindValue(':sideId',$shipData['side'],PDO::PARAM_INT);
					$queryInsertInvolvedFitting->bindValue(':characterId',$characterId,PDO::PARAM_INT);
					$queryInsertInvolvedFitting->bindValue(':shipTypeId',$shipTypeId,PDO::PARAM_INT);
					foreach ($shipData['fittings'] as $weaponTypeId => &$count)
					{
						$queryInsertInvolvedFitting->bindValue(':weaponTypeId',$weaponTypeId,PDO::PARAM_INT);
						$queryInsertInvolvedFitting->bindValue(':numOccurrence',$count,PDO::PARAM_INT);
						executeQuery($queryInsertInvolvedFitting);
					}
				}
			unset($queryInsertInvolvedEntry);
			unset($queryInsertInvolvedFitting);
			
			$queryInsertSide = prepareQuery($db,'INSERT INTO `battlereport_sides` (`reportId`,`sideId`,`killValue`,`effectiveKillValue`,`lossValue`,`mainAllianceId`,`mainCorporationId`,`primaryDDShipTypeId`,`primaryDDShipCount`,`secondaryDDShipTypeId`,`secondaryDDShipCount`,`primaryLogiShipTypeId`,`primaryLogiShipCount`) VALUES (:reportId,:sideId,:killValue,:effectiveKillValue,:lossValue,:mainAllianceId,:mainCorporationId,:primaryDDShipTypeId,:primaryDDShipCount,:secondaryDDShipTypeId,:secondaryDDShipCount,:primaryLogiShipTypeId,:primaryLogiShipCount)');
			$queryInsertSide->bindValue(':reportId',$reportId,PDO::PARAM_INT);
			foreach ($sides as $sideId => &$sideData)
			{
				$ddtotal = $logitotal = 0;
				$ddship = $ddshipcount = $ddship2 = $ddship2count = $logiship = $logishipcount = 0;
				foreach ($sideData->ddShips as $shipTypeId => &$shipCount)
				{
					$ddtotal += $shipCount;
					if ($shipCount > $ddshipcount)
					{
						$ddship2 = $ddship;
						$ddship2count = $ddshipcount;
						$ddship = $shipTypeId;
						$ddshipcount = $shipCount;
					}
					elseif ($shipCount > $ddship2count)
					{
						$ddship2 = $shipTypeId;
						$ddship2count = $shipCount;
					}
				}
				unset($shipCount);
				foreach ($sideData->logiShips as $shipTypeId => &$shipCount)
				{
					$logitotal += $shipCount;
					if ($shipCount > $logishipcount)
					{
						$logiship = $shipTypeId;
						$logishipcount = $shipCount;
					}
				}
				$shiptotal = $ddtotal + $logitotal;
				
				if ($ddshipcount < $ddtotal * 0.25)
					$ddshipcount = $ddship = $ddship2count = $ddship2 = 0;
				if ($ddship2count < $ddtotal * 0.20)
					$ddship2count = $ddship2 = 0;
				if ($logishipcount < $logitotal * 0.4)
					$logishipcont = $logiship = 0;
				
				$queryInsertSide->bindValue(':sideId',$sideId,PDO::PARAM_INT);
				$queryInsertSide->bindValue(':killValue',$sideData->killValue,PDO::PARAM_STR);
				$queryInsertSide->bindValue(':effectiveKillValue',$sideData->effectiveKillValue,PDO::PARAM_STR);
				$queryInsertSide->bindValue(':lossValue',$sideData->lossValue,PDO::PARAM_STR);
				$queryInsertSide->bindValue(':mainAllianceId',$sideData->mainAllianceId,PDO::PARAM_INT);
				$queryInsertSide->bindValue(':mainCorporationId',$sideData->mainCorporationId,PDO::PARAM_INT);
				if ($shiptotal >= 10)
				{ // >= 10 show dd + logi summary ships
					$queryInsertSide->bindValue(':primaryDDShipTypeId',$ddship,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':primaryDDShipCount',$ddshipcount,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':primaryLogiShipTypeId',$logiship,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':primaryLogiShipCount',$logishipcount,PDO::PARAM_INT);
					if ($shiptotal >= 20)
					{ // >= 20 show secondary dd ship
						$queryInsertSide->bindValue(':secondaryDDShipTypeId',$ddship2,PDO::PARAM_INT);
						$queryInsertSide->bindValue(':secondaryDDShipCount',$ddship2count,PDO::PARAM_INT);
					}
					else
					{
						$queryInsertSide->bindValue(':secondaryDDShipTypeId',0,PDO::PARAM_INT);
						$queryInsertSide->bindValue(':secondaryDDShipCount',0,PDO::PARAM_INT);
					}
				}
				else
				{
					$queryInsertSide->bindValue(':primaryDDShipTypeId',0,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':primaryDDShipCount',0,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':primaryLogiShipTypeId',0,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':primaryLogiShipCount',0,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':secondaryDDShipTypeId',0,PDO::PARAM_INT);
					$queryInsertSide->bindValue(':secondaryDDShipCount',0,PDO::PARAM_INT);
				}
				executeQuery($queryInsertSide);
			}
		}
		catch (PDOException $e)
		{
			doError("Error updating battle report $reportId: ".$e->getMessage());
			throw new RuntimeException('Database Error');
		}
	}
?>