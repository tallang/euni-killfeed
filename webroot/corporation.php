<?php
	require(__DIR__.'/../render/setup.inc.php');
	if (isset($_GET['corporationID']))
		$corporationId = (int)$_GET['corporationID'];
	else
		$corporationId = 0;
	
	if (!$corporationId)
	{
		doError('No corporation specified.',400);
		goto render;
	}
	
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../render/piechart.inc.php');
	require(__DIR__.'/../render/killListing.inc.php');
	
	try
	{
		$getMetadataQuery = prepareQuery(killfeedDB(),'SELECT
			`corp`.`corporationId` as `corporationId`,
			`corp`.`corporationName` as `corporationName`,
			`corp`.`allianceId` as `allianceId`,
			`alliance`.`allianceName` as `allianceName`,
			`corp`.`killCount` as `killCount`,
			`corp`.`lossCount` as `lossCount`,
			`corp`.`killValue` as `killValue`,
			`corp`.`effectiveKillValue` as `effectiveKillValue`,
			`corp`.`lossValue` as `lossValue`,
			`corp`.`averageFriendCount` as `averageFriendCount`,
			`corp`.`averageKillValue` as `averageKillValue`,
			`corp`.`averageEnemyCount` as `averageEnemyCount`,
			`corp`.`averageLossValue` as `averageLossValue`
		FROM `corporation_metadata` as `corp`
		LEFT JOIN `alliance_metadata` as `alliance`
			ON `corp`.`allianceId` = `alliance`.`allianceId`
		WHERE `corp`.`corporationId` = ?;');
		$getMetadataQuery->bindValue(1,$corporationId,PDO::PARAM_INT);
		$getMetadataQuery->execute();
		if (!$getMetadataQuery->rowCount())
		{
			doError('Specified corporation not found.',404);
			goto render;
		}
		$corporationMetadata = $getMetadataQuery->fetchObject('DBEntityMetadata');
		
		$getKillQuery = prepareQuery(killfeedDB(),'SELECT
										`index`.`killid` as `killId`,
										`kill`.`victimCharacterId` as `victimCharacterId`,
										`kill`.`victimCorporationId` as `victimCorporationId`,
										`kill`.`victimAllianceId` as `victimAllianceId`,
										`kill`.`shipTypeId` as `victimShipTypeId`,
										`kill`.`solarSystemId` as `solarSystemId`,
										DATE_FORMAT(`kill`.`killTime`,\'%d %b %Y\') as `killDate`,
										DATEDIFF(UTC_TIMESTAMP(),`kill`.`killTime`) as `daysAgo`,
										TIME_FORMAT(`kill`.`killTime`,\'%H:%i\') as `killTime`,
										`kill`.`valueTotal` as `valueTotal`,
										`kill`.`numKillers` as `numKillers`,
										`kill`.`mostCommonKillerShip` as `mostCommonKillerShip`,
										`kill`.`secondMostCommonKillerShip` as `secondMostCommonKillerShip`,
										`char`.`characterName` as `victimCharacterName`,
										`corp`.`corporationName` as `victimCorporationName`,
										`alliance`.`allianceName` as `victimAllianceName`,
										`finalBlow`.`killerCharacterId` AS `killerCharacterId`,
										`finalBlow`.`killerCharacterName` as `killerNpcCharacterName`,
										`finalBlowChar`.`characterName` as `killerCharacterName`,
										`finalBlow`.`killerCorporationId` as `killerCorporationId`,
										`finalBlowCorp`.`corporationName` as `killerCorporationName`,
										`finalBlow`.`killerAllianceId` as `killerAllianceId`,
										`finalBlowAlliance`.`allianceName` as `killerAllianceName`,
										`finalBlow`.`killerShipTypeId` as `killerShipTypeId`,
										(`kill`.`victimCorporationId` = :corporationId) as `isLoss`
									FROM `corporation_kill_history` as `index`
									LEFT JOIN `kill_metadata` as `kill`
										ON `kill`.`id` = `index`.`killid`
									LEFT JOIN `character_metadata` as `char`
										ON `kill`.`victimCharacterId` = `char`.`characterId`
									LEFT JOIN `corporation_metadata` as `corp`
										ON `kill`.`victimCorporationId` = `corp`.`corporationId`
									LEFT JOIN `alliance_metadata` as `alliance`
										ON `kill`.`victimAllianceId` = `alliance`.`allianceId`
									LEFT JOIN `kill_killers` as `finalBlow`
										ON `kill`.`id` = `finalBlow`.`killId` and `finalBlow`.`finalBlow` = 1
									LEFT JOIN `character_metadata` as `finalBlowChar`
										ON `finalBlow`.`killerCharacterId` = `finalBlowChar`.`characterId`
									LEFT JOIN `corporation_metadata` as `finalBlowCorp`
										ON `finalBlow`.`killerCorporationId` = `finalBlowCorp`.`corporationId`
									LEFT JOIN `alliance_metadata` as `finalBlowAlliance`
										ON `finalBlow`.`killerAllianceId` = `finalBlowAlliance`.`allianceId`
									WHERE `index`.`corporationId` = :corporationId AND `kill`.`valueTotal` > 100000
									ORDER BY `kill`.`id` DESC
									LIMIT 30;');
		$getKillQuery->bindValue(':corporationId',$corporationId,PDO::PARAM_INT);
		$getKillQuery->execute();
		$kills = fetchAll($getKillQuery,'DBKillListEntry');
	}
	catch (PDOException $e)
	{
		doError('Database error fetching corporation info: '.$e->getMessage(),500);
		die();
	}
?>
<!DOCTYPE html> 
<html>
	<head>
		<meta charset="utf-8"/>
		<title><?=$corporationMetadata->corporationName?> - E-UNI Killfeed</title>
		<link rel="stylesheet" href="css/navbar.css" />
		<link rel="stylesheet" href="css/killfeed.css" />
		<link rel="stylesheet" href="css/listing.css" />
		<link rel="stylesheet" href="css/piechart.css" />
		<link rel="stylesheet" href="css/character.css" />
		<link rel="stylesheet" href="css/corporation.css" />
		<script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
		<script src="js/corporation.js"></script>
		<meta name="viewport" content="width=1000, initial-scale=1" />
	</head>
	<body>
		<?php include(__DIR__.'/../render/navbar.inc.php'); ?>
		<div id="content">
			<div id="block-top">
				<div id="quickinfo" class="panel">
					<div id="quickinfo-avatar">
						<img src="http://image.eveonline.com/Corporation/<?=$corporationMetadata->corporationId?>_256.png" class="fill" />
					</div>
					<div id="quickinfo-text">
						<div class="quickinfo-block">
							<div class="quickinfo-left">Corp:</div>
							<div class="quickinfo-right"><?=$corporationMetadata->corporationName?></div>
						</div><?php
						if ($corporationMetadata->allianceId)
						{ ?> 
						<div class="quickinfo-block">
							<div class="quickinfo-left">Alliance:</div>
							<div class="quickinfo-right"><a href="alliance.php?allianceID=<?=$corporationMetadata->allianceId?>"><?=$corporationMetadata->allianceName?></a></div>
						</div><?php
						} ?> 
					</div><?php
						$killValue = $corporationMetadata->effectiveKillValue;
						$lossValue = $corporationMetadata->lossValue;
						$totalValue = $killValue + $lossValue;
						if ($totalValue)
							$fractKill = round($killValue*100 / $totalValue, 1);
						else
							$fractKill = 50; // fallback, this should almost never happen
					?>
					<div id="quickinfo-stats">
						<div id="quickinfo-bar" class="bar-container">
							<div class="bar-left" style="right: <?=100-$fractKill?>%;">
								<div class="bar-label-absolute noselect"><?=formatISKShort($killValue)?></div>
								<div class="bar-label-relative noselect"><?=ceil($fractKill)?>%</div>
								<div class="bar-label-label noselect">Killed</div>
							</div>
							<div class="bar-right" style="left: <?=$fractKill?>%;">
								<div class="bar-label-absolute noselect"><?=formatISKShort($lossValue)?></div>
								<div class="bar-label-relative noselect"><?=floor(100-$fractKill)?>%</div>
								<div class="bar-label-label noselect">Lost</div>
							</div>
						</div>
					</div>
				</div>
				<div id="stats" class="panel">
					<div id="stats-1" class="stats-entry">
						<div class="label">Lifetime stats</div>
						<?php piechart('greenred',$corporationMetadata->killCount,'kills',$corporationMetadata->lossCount,'losses'); ?> 
					</div>
					<div id="stats-2" class="stats-entry">
						<div class="label">Average engagement size</div>
						<?php piechart('greenred',$corporationMetadata->averageFriendCount,'friendlies',$corporationMetadata->averageEnemyCount,'hostiles'); ?> 
					</div>
					<div id="stats-3" class="stats-entry">
						<div class="label">Average damage contribution</div>
						<?php
							$contributionSelf = $corporationMetadata->effectiveKillValue*100 / $corporationMetadata->killValue;
							piechart('greenred',$contributionSelf,'% by self',100-$contributionSelf,'% by others');
						?> 
					</div>
				</div>
			</div>
			<div id="block-bottom" class="panel">
				<div class="listing-label">Recent activity</div><?php
				renderKillListing($kills); ?>
			</div>
		</div>
	</body>
</html><?php render: require(__DIR__.'/../render/doRender.inc.php'); ?>