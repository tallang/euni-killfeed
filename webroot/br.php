<?php
	require(__DIR__.'/../render/setup.inc.php');
	
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../helpers/cache.inc.php');
	require(__DIR__.'/../helpers/format.inc.php');
	require(__DIR__.'/../helpers/auth.inc.php');
	
	$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	$firstLoad = $invalidLoad = false;
	if (!$reportId)
	{
		doError('No BR ID specified.',400);
		goto render;
	};
	
	$db = killfeedDB();
	if (!$db)
	{
		doError('Database connection failed.',500);
		die();
	}
	
	try
	{
		$getMetaQuery = prepareQuery($db,'SELECT
											`meta`.`reportId` as `reportId`,
											`meta`.`ownerCharacterId` as `ownerCharacterId`,
											`char`.`characterName` as `ownerCharacterName`,
											TIME_TO_SEC(TIMEDIFF(UTC_TIMESTAMP(),`meta`.`lastRefreshed`)) as `lastRefreshed`,
											`meta`.`reportType` as `reportType`
										FROM `battlereport_meta` as `meta`
										LEFT JOIN `character_metadata` as `char`
											ON `meta`.`ownerCharacterId`=`char`.`characterId`
										WHERE `meta`.`reportId` = :reportId');
		$getMetaQuery->bindValue(':reportId',$reportId,PDO::PARAM_INT);
		if (!$getMetaQuery->execute())
			throw new PDOException($getMetaQuery->errorInfo()[2]);
		
		if (!$getMetaQuery->rowCount())
		{
			doError("Battle report $reportId not found.",404);
			goto render;
		}
		$reportMetadata = $getMetaQuery->fetchObject('DBReportMetadata');
		$isOwner = (isLoggedIn() && (getCurrentUserCharacterId() === $reportMetadata->ownerCharacterId || currentUserIsAdmin()));
		if ($reportMetadata->lastRefreshed === null)
		{
			if ($isOwner)
				$firstLoad = true;
			else
			{
				doError("Battle report $reportId not found.",404);
				goto render;
			}
		}
		
		if (!$firstLoad)
			switch ($reportMetadata->reportType)
			{
				case '2side':
					$getSideMetaQuery = prepareQuery($db,'SELECT
															`side`.`sideId` as `sideId`,
															`side`.`killValue` as `killValue`,
															`side`.`effectiveKillValue` as `effectiveKillValue`,
															`side`.`lossValue` as `lossValue`,
															`side`.`mainAllianceId` as `mainAllianceId`,
															`alli`.`allianceName` as `mainAllianceName`,
															`side`.`mainCorporationId` as `mainCorporationId`,
															`corp`.`corporationName` as `mainCorporationName`,
															`side`.`primaryDDShipTypeId` as `primaryDDShipTypeId`,
															`side`.`primaryDDShipCount` as `primaryDDShipCount`,
															`side`.`secondaryDDShipTypeId` as `secondaryDDShipTypeId`,
															`side`.`secondaryDDShipCount` as `secondaryDDShipCount`,
															`side`.`primaryLogiShipTypeId` as `primaryLogiShipTypeId`,
															`side`.`primaryLogiShipCount` as `primaryLogiShipCount`
														FROM `battlereport_sides` as `side`
														LEFT JOIN `alliance_metadata` as `alli`
															ON `side`.`mainAllianceId` = `alli`.`allianceId`
														LEFT JOIN `corporation_metadata` as `corp`
															ON `side`.`mainCorporationId` = `corp`.`corporationId`
														WHERE `side`.`reportId` = :reportId
															AND `side`.`sideId` BETWEEN 1 AND 2
														ORDER BY `side`.`sideId` ASC');
					$getSideMetaQuery->bindValue(':reportId',$reportId,PDO::PARAM_INT);
					if (!$getSideMetaQuery->execute())
						throw new PDOException($getSideMetaQuery->errorInfo()[2]);
					if ($getSideMetaQuery->rowCount() != 2)
					{
						$invalidLoad = true;
						break;
					}
					$leftSideMeta = $getSideMetaQuery->fetchObject('DBReportSideMeta');
					$rightSideMeta = $getSideMetaQuery->fetchObject('DBReportSideMeta');
					$leftSideMeta->numSummaryShips = 0;
					if ($leftSideMeta->primaryDDShipTypeId)
						++$leftSideMeta->numSummaryShips;
					if ($leftSideMeta->secondaryDDShipTypeId)
						++$leftSideMeta->numSummaryShips;
					if ($leftSideMeta->primaryLogiShipTypeId)
						++$leftSideMeta->numSummaryShips;
					$rightSideMeta->numSummaryShips = 0;
					if ($rightSideMeta->primaryDDShipTypeId)
						++$rightSideMeta->numSummaryShips;
					if ($rightSideMeta->secondaryDDShipTypeId)
						++$rightSideMeta->numSummaryShips;
					if ($rightSideMeta->primaryLogiShipTypeId)
						++$rightSideMeta->numSummaryShips;
					$numSummaryShips = max($leftSideMeta->numSummaryShips, $rightSideMeta->numSummaryShips);
					$sumEffectiveKill = $leftSideMeta->effectiveKillValue + $rightSideMeta->effectiveKillValue;
					if ($sumEffectiveKill)
					{
						$leftSideMeta->iskEfficiency = $leftSideMeta->effectiveKillValue/$sumEffectiveKill;
						$rightSideMeta->iskEfficiency = $rightSideMeta->effectiveKillValue/$sumEffectiveKill;
					}
					else
						$leftSideMeta->iskEfficiency = $rightSideMeta->iskEfficiency = 0.5;
					
					$getSideInvolvedQuery = prepareQuery($db,'SELECT
																`data`.`sideId` as `sideId`,
																`data`.`characterId` as `characterId`,
																`char`.`characterName` as `characterName`,
																`data`.`corporationId` as `corporationId`,
																`corp`.`corporationName` as `corporationName`,
																`data`.`allianceId` as `allianceId`,
																`alli`.`allianceName` as `allianceName`,
																`data`.`shipTypeId` as `shipTypeId`,
																`data`.`shipValue` as `shipValue`,
																`data`.`relatedKillId` as `relatedKillId`
															FROM `battlereport_involved` as `data`
															LEFT JOIN `character_metadata` as `char`
																ON `data`.`characterId` = `char`.`characterId`
															LEFT JOIN `corporation_metadata` as `corp`
																ON `data`.`corporationId` = `corp`.`corporationId`
															LEFT JOIN `alliance_metadata` as `alli`
																ON `data`.`allianceId` = `alli`.`allianceId`
															WHERE `data`.`reportId` = :reportId
																AND `data`.`sideId` = :sideId
															ORDER BY `data`.`shipValue` DESC, `data`.`numInvolved` DESC');
					$getSideInvolvedQuery->bindValue(':reportId',$reportId,PDO::PARAM_INT);
					
					$getSideInvolvedQuery->bindValue(':sideId',1,PDO::PARAM_INT);
					if (!$getSideInvolvedQuery->execute())
						throw new PDOException($getSideInvolvedQuery->errorInfo()[2]);
					$leftSideInvolved = fetchAll($getSideInvolvedQuery,'DBReportInvolved');
					
					$getSideInvolvedQuery->bindValue(':sideId',2,PDO::PARAM_INT);
					if (!$getSideInvolvedQuery->execute())
						throw new PDOException($getSideInvolvedQuery->errorInfo()[2]);
					$rightSideInvolved = fetchAll($getSideInvolvedQuery,'DBReportInvolved');
					
					$getInvolvedFittingsQuery = prepareQuery($db,'SELECT
																	`fit`.`weaponTypeId` as `weaponTypeId`,
																	`fit`.`numOccurrence` as `numOccurrence`
																FROM `battlereport_involved_fittings` as `fit`
																WHERE `fit`.`reportId` = :reportId
																	AND `fit`.`sideId` = :sideId
																	AND `fit`.`characterId` = :characterId
																	AND `fit`.`shipTypeId` = :shipTypeId
																ORDER BY `fit`.`numOccurrence` DESC');
					$getInvolvedFittingsQuery->bindValue(':reportId',$reportId,PDO::PARAM_INT);
					break;
			}
	}
	catch (PDOException $e)
	{
		doError('Database error getting report metadata: '.$e->getMessage(),500);
		die();
	}
?>
<!DOCTYPE html> 
<html>
	<head>
		<meta charset="utf-8"/>
		<title>E-UNI Killfeed - Battle Report</title>
		<link rel="stylesheet" href="css/navbar.css" />
		<link rel="stylesheet" href="css/killfeed.css" />
		<link rel="stylesheet" href="css/br.css" />
		<script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
		<script src="js/br.js"></script><?php if($firstLoad) echo '<script>window.isFirstLoad = true;</script>'; else if ($isOwner && (+$reportMetadata->lastRefreshed < 300)) echo '<script>window.refreshTimeout = ',+(300-$reportMetadata->lastRefreshed),';</script>'; ?>
		<meta name="viewport" content="width=1000, initial-scale=1" />
	</head>
	<body>
		<?php include(__DIR__.'/../render/navbar.inc.php'); ?>
		<div id="content"><?php
			if ($isOwner)
			{ ?> 
			<div id="refresh-button">
				<div id="refresh-text">
					<img src="img/refresh.png" />
					Refresh
				</div>
				<div id="reassign-text">
					<img src="img/reassign.png" />
					Reassign
				</div>
			</div><?php
			}
			if (!$firstLoad && !$invalidLoad)
				if ($reportMetadata->reportType == '2side')
				{ ?> 
			<div id="block-top">
				<div id="iskwar-background" style="height: <?=max(180,160+25*$numSummaryShips)?>px;">
					<div id="iskwar-background-leftlogo"></div>
					<div id="iskwar-background-rightlogo"></div>
					<div id="iskwar-background-bar"></div>
					<div id="iskwar-background-leftlabel1"></div>
					<div id="iskwar-background-leftlabel2"></div>
					<div id="iskwar-background-leftlabel3"></div>
					<div id="iskwar-background-leftlabel4"></div>
					<div id="iskwar-background-rightlabel1"></div>
					<div id="iskwar-background-rightlabel2"></div>
					<div id="iskwar-background-rightlabel3"></div>
					<div id="iskwar-background-rightlabel4"></div>
					<div id="iskwar-background-leftlabel2-1"></div>
					<div id="iskwar-background-leftlabel2-2"></div>
					<div id="iskwar-background-leftlabel2-3"></div>
					<div id="iskwar-background-leftlabel2-4"></div>
					<div id="iskwar-background-rightlabel2-1"></div>
					<div id="iskwar-background-rightlabel2-2"></div>
					<div id="iskwar-background-rightlabel2-3"></div>
					<div id="iskwar-background-rightlabel2-4"></div><?php
					if ($leftSideMeta->numSummaryShips)
					{ ?> 
					<div id="iskwar-background-leftsummary1"></div>
					<div id="iskwar-background-leftsummary2" style="height: <?=5 + $leftSideMeta->numSummaryShips*25?>px;"></div>
					<div id="iskwar-background-leftsummary3"></div>
					<div id="iskwar-background-leftsummary4"></div><?php
					}
					if ($rightSideMeta->numSummaryShips)
					{ ?> 
					<div id="iskwar-background-rightsummary1"></div>
					<div id="iskwar-background-rightsummary2" style="height: <?=5 + $rightSideMeta->numSummaryShips*25?>px;"></div>
					<div id="iskwar-background-rightsummary3"></div>
					<div id="iskwar-background-rightsummary4"></div><?php
					} ?> 
					<div id="iskwar"><?php
						$leftLogoSrc = null;
						$leftLabel = 'Green side';
						if ($leftSideMeta->mainAllianceId)
						{
							$leftLogoSrc = 'http://image.eveonline.com/Alliance/'.$leftSideMeta->mainAllianceId.'_128.png';
							$leftLabel = $leftSideMeta->mainAllianceName;
						}
						elseif ($leftSideMeta->mainCorporationId)
						{
							$leftLogoSrc = 'http://image.eveonline.com/Corporation/'.$leftSideMeta->mainCorporationId.'_128.png';
							$leftLabel = $leftSideMeta->mainCorporationName;
						}
						elseif ($leftSideMeta->primaryLogiShipCount > $leftSideMeta->primaryDDShipCount)
							$leftLogoSrc = 'http://image.eveonline.com/Render/'.$leftSideMeta->primaryLogiShipTypeId.'_128.png';
						elseif ($leftSideMeta->primaryDDShipTypeId)
							$leftLogoSrc = 'http://image.eveonline.com/Render/'.$leftSideMeta->primaryDDShipTypeId.'_128.png';
						
						$rightLogoSrc = null;
						$rightLabel = 'Red side';
						if ($rightSideMeta->mainAllianceId)
						{
							$rightLogoSrc = 'http://image.eveonline.com/Alliance/'.$rightSideMeta->mainAllianceId.'_128.png';
							$rightLabel = $rightSideMeta->mainAllianceName;
						}
						elseif ($rightSideMeta->mainCorporationId)
						{
							$rightLogoSrc = 'http://image.eveonline.com/Corporation/'.$rightSideMeta->mainCorporationId.'_128.png';
							$rightLabel = $rightSideMeta->mainCorporationName;
						}
						elseif ($rightSideMeta->primaryLogiShipCount > $rightSideMeta->primaryDDShipCount)
							$rightLogoSrc = 'http://image.eveonline.com/Render/'.$rightSideMeta->primaryLogiShipTypeId.'_128.png';
						elseif ($rightSideMeta->primaryDDShipTypeId)
							$rightLogoSrc = 'http://image.eveonline.com/Render/'.$rightSideMeta->primaryDDShipTypeId.'_128.png';
					?> 
						<div id="bar-left">
							<div id="bar-left-body" style="width: <?=35 + $leftSideMeta->iskEfficiency*1110?>px;"></div>
							<div id="logo-background-left"><?php
							if ($leftLogoSrc !== null)
							{ ?> 
								<div id="logo-left">
									<img class="fill" src="<?=$leftLogoSrc?>" />
								</div><?php
							} ?> 
							</div>
						</div>
						<div id="bar-left-label"><?=htmlentities($leftLabel)?></div>
						<div id="bar-left-label2"><?=formatISKShort($leftSideMeta->effectiveKillValue)?> ISK killed</div><?php
						function summarycontainer($typeId,$num)
						{
							if ($typeId)
								echo '<div class="summary-entry"><div class="summary-icon"><img class="fill" src="http://image.eveonline.com/Type/',$typeId,'_32.png" /></div><div class="summary-text">',$num,'x ',htmlspecialchars(getCachedItemName($typeId)),'</div></div>';
						}
						if ($leftSideMeta->numSummaryShips)
						{ ?> 
						<div id="summary-left-container"><?php
							summarycontainer($leftSideMeta->primaryDDShipTypeId,$leftSideMeta->primaryDDShipCount);
							summarycontainer($leftSideMeta->secondaryDDShipTypeId,$leftSideMeta->secondaryDDShipCount);
							summarycontainer($leftSideMeta->primaryLogiShipTypeId,$leftSideMeta->primaryLogiShipCount);
						?></div><?php
						} ?> 
						<div id="bar-right">
							<div id="bar-right-body" style="width: <?=35 + $rightSideMeta->iskEfficiency*1110?>px;"></div>
							<div id="logo-background-right"><?php
							if ($rightLogoSrc !== null)
							{ ?>
								<div id="logo-right">
									<img class="fill" src="<?=$rightLogoSrc?>" />
								</div><?php
							} ?>
							</div>
						</div>
						<div id="bar-right-label"><?=htmlentities($rightLabel)?></div>
						<div id="bar-right-label2"><?=formatISKShort($rightSideMeta->effectiveKillValue)?> ISK killed</div><?php
						if ($rightSideMeta->numSummaryShips)
						{ ?> 
						<div id="summary-right-container"><?php
							summarycontainer($rightSideMeta->primaryDDShipTypeId,$rightSideMeta->primaryDDShipCount);
							summarycontainer($rightSideMeta->secondaryDDShipTypeId,$rightSideMeta->secondaryDDShipCount);
							summarycontainer($rightSideMeta->primaryLogiShipTypeId,$rightSideMeta->primaryLogiShipCount);
						?></div><?php
						} ?> 
					</div>
				</div>
				<div id="involved" class="panel">
					<div id="involved-left"><?php
					foreach ($leftSideInvolved as &$involved)
					{ ?> 
						<div class="involved-entry<?php if ($involved->relatedKillId) echo ' loss"><a href="kill.php?killID=',$involved->relatedKillId; ?>">
							<div class="involved-shipicon">
								<img class="fill" src="http://image.eveonline.com/Type/<?=$involved->shipTypeId?>_32.png" />
							</div>
							<div class="involved-name"><?=htmlentities($involved->characterName ? $involved->characterName : getCachedItemName($involved->shipTypeId))?></div>
							<div class="involved-corp"><?=htmlentities($involved->corporationName)?><?php if ($involved->allianceId) echo ' (',htmlentities($involved->allianceName),')'; ?></div>
							<div class="involved-ship"><?=htmlentities($involved->characterName ? getCachedItemName($involved->shipTypeId) : '')?></div>
							<div class="involved-fittings"><?php
								$getInvolvedFittingsQuery->bindValue(':sideId',1,PDO::PARAM_INT);
								$getInvolvedFittingsQuery->bindValue(':characterId',$involved->characterId,PDO::PARAM_INT);
								$getInvolvedFittingsQuery->bindValue(':shipTypeId',$involved->shipTypeId,PDO::PARAM_INT);
								if ($getInvolvedFittingsQuery->execute())
								{
									while ($fitting = $getInvolvedFittingsQuery->fetchObject('DBReportInvolvedFitting'))
										echo '<div class="involved-fitting" title="',htmlspecialchars(getCachedItemName($fitting->weaponTypeId)),'"><img class="fill" src="http://image.eveonline.com/Type/',$fitting->weaponTypeId,'_32.png" /><div class="involved-fitting-count">',$fitting->numOccurrence,'x</div></div>';
								}
								else
								{
									doError('DB error getting BR involved_fittings: '.$getInvolvedFittingsQuery->errorInfo()[2],200);
									die();
								}
							?></div>
						<?php if ($involved->relatedKillId) echo '</a>'; ?></div><?php
					} ?> 
					</div>
					<div id="involved-right"><?php
					foreach ($rightSideInvolved as &$involved)
					{ ?>
						<div class="involved-entry<?php if ($involved->relatedKillId) echo ' loss"><a href="kill.php?killID=',$involved->relatedKillId; ?>">
							<div class="involved-shipicon">
								<img class="fill" src="http://image.eveonline.com/Type/<?=$involved->shipTypeId?>_32.png" />
							</div>
							<div class="involved-name"><?=htmlentities($involved->characterName ? $involved->characterName : getCachedItemName($involved->shipTypeId))?></div>
							<div class="involved-corp"><?=htmlentities($involved->corporationName)?><?php if ($involved->allianceId) echo ' (',htmlentities($involved->allianceName),')'; ?></div>
							<div class="involved-ship"><?=htmlentities($involved->characterName ? getCachedItemName($involved->shipTypeId) : '')?></div>
							<div class="involved-fittings"><?php
								$getInvolvedFittingsQuery->bindValue(':sideId',2,PDO::PARAM_INT);
								$getInvolvedFittingsQuery->bindValue(':characterId',$involved->characterId,PDO::PARAM_INT);
								$getInvolvedFittingsQuery->bindValue(':shipTypeId',$involved->shipTypeId,PDO::PARAM_INT);
								if ($getInvolvedFittingsQuery->execute())
								{
									while ($fitting = $getInvolvedFittingsQuery->fetchObject('DBReportInvolvedFitting'))
										echo '<div class="involved-fitting" title="',htmlspecialchars(getCachedItemName($fitting->weaponTypeId)),'"><img class="fill" src="http://image.eveonline.com/Type/',$fitting->weaponTypeId,'_32.png" /><div class="involved-fitting-count">',$fitting->numOccurrence,'x</div></div>';
								}
								else
									doError('DB error getting BR involved_fittings: '.$getInvolvedFittingsQuery->errorInfo()[2],200);
							?></div>
						<?php if ($involved->relatedKillId) echo '</a>'; ?></div><?php
					} ?>
					</div>
				</div>
			</div><?php
				} ?> 
		</div>
	</body>
</html><?php render: require(__DIR__.'/../render/doRender.inc.php'); ?>