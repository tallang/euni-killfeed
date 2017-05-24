<?php
	require(__DIR__.'/../render/setup.inc.php');
  if (isset($_GET['characterName']))
  {
    require(__DIR__.'/../helpers/database.inc.php');
    $getCharacterQuery = prepareQuery(killfeedDB(),'SELECT `characterId` as `id` FROM `character_metadata` WHERE LCASE(`characterName`) = LCASE(:needle) LIMIT 1;');
    $getCharacterQuery->bindValue(':needle',$_GET['characterName'],PDO::PARAM_STR);
    executeQuery($getCharacterQuery);
    if ($character = $getCharacterQuery->fetchObject())
    {
      header('HTTP/1.1 301 Moved Permanently');
      header('Location: character.php?characterID='.(+$character->id));
      die();
    }
    else
    {
      doError('No character by that exact name. Try using search!',400,false);
      goto render;
    }
  }
	if (isset($_GET['characterID']))
		$characterId = (int)$_GET['characterID'];
	else
		$characterId = 0;
	
	if (!$characterId)
	{
		doError('No character specified.',400);
		goto render;
	}
	
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../helpers/format.inc.php');
	require(__DIR__.'/../render/piechart.inc.php');
	require(__DIR__.'/../render/killListing.inc.php');
	
	try
	{
		$getMetadataQuery = prepareQuery(killfeedDB(),'SELECT
			`char`.`characterId` as `characterId`,
			`char`.`characterName` as `characterName`,
			`char`.`corporationId` as `corporationId`,
			`corp`.`corporationName` as `corporationName`,
			`char`.`allianceId` as `allianceId`,
			`alliance`.`allianceName` as `allianceName`,
			`char`.`killCount` as `killCount`,
			`char`.`lossCount` as `lossCount`,
			`char`.`killValue` as `killValue`,
			`char`.`effectiveKillValue` as `effectiveKillValue`,
			`char`.`lossValue` as `lossValue`,
			`char`.`averageFriendCount` as `averageFriendCount`,
			`char`.`averageKillValue` as `averageKillValue`,
			`char`.`averageEnemyCount` as `averageEnemyCount`,
			`char`.`averageLossValue` as `averageLossValue`
		FROM `character_metadata` as `char`
		LEFT JOIN `corporation_metadata` as `corp`
			ON `char`.`corporationId` = `corp`.`corporationId`
		LEFT JOIN `alliance_metadata` as `alliance`
			ON `char`.`allianceId` = `alliance`.`allianceId`
		WHERE `char`.`characterId` = ?;');
		$getMetadataQuery->bindValue(1,$characterId,PDO::PARAM_INT);
		$getMetadataQuery->execute();
		if (!$getMetadataQuery->rowCount())
		{
			doError('Specified character not found.',404);
			goto render;
		}
		$characterMetadata = $getMetadataQuery->fetchObject('DBEntityMetadata');
	}
	catch (PDOException $e)
	{
		doError('Database error fetching character info: '.$e->getMessage(),500);
		die();
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8"/>
		<title><?=$characterMetadata->characterName?> - E-UNI Killfeed</title>
		<link rel="stylesheet" href="css/navbar.css" />
		<link rel="stylesheet" href="css/killfeed.css" />
		<link rel="stylesheet" href="css/listing.css" />
		<link rel="stylesheet" href="css/piechart.css" />
		<link rel="stylesheet" href="css/character.css" />
		<script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
    <script src="js/listing.js"></script>
		<script src="js/character.js"></script>
		<meta name="viewport" content="width=1000, initial-scale=1" />
	</head>
	<body>
		<?php include(__DIR__.'/../render/navbar.inc.php'); ?>
		<div id="content">
			<div id="block-top">
				<div id="quickinfo" class="panel">
					<div id="quickinfo-avatar">
						<img src="http://image.eveonline.com/Character/<?=$characterMetadata->characterId?>_256.jpg" class="fill" />
					</div>
					<div id="quickinfo-text">
						<div class="quickinfo-block">
							<div class="quickinfo-left">Pilot:</div>
							<div class="quickinfo-right"><?=$characterMetadata->characterName?></div>
						</div><?php
						if ($characterMetadata->corporationId)
						{ ?>
						<div class="quickinfo-block">
							<div class="quickinfo-left">Corp:</div>
							<div class="quickinfo-right"><a href="corporation.php?corporationID=<?=$characterMetadata->corporationId?>"><?=$characterMetadata->corporationName?></a></div>
						</div><?php
						}
						if ($characterMetadata->allianceId)
						{ ?> 
						<div class="quickinfo-block">
							<div class="quickinfo-left">Alliance:</div>
							<div class="quickinfo-right"><a href="alliance.php?allianceID=<?=$characterMetadata->allianceId?>"><?=$characterMetadata->allianceName?></a></div>
						</div><?php
						} ?> 
					</div><?php
						$killValue = $characterMetadata->killValue;
						$lossValue = $characterMetadata->lossValue;
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
						<?php piechart('greenred',$characterMetadata->killCount,'kills',$characterMetadata->lossCount,'losses'); ?> 
					</div>
					<div id="stats-2" class="stats-entry">
						<div class="label">Average engagement size</div>
						<?php piechart('greenred',$characterMetadata->averageFriendCount,'friendlies',$characterMetadata->averageEnemyCount,'hostiles'); ?> 
					</div>
					<div id="stats-3" class="stats-entry">
						<div class="label">Average kill value</div>
						<?php piechart('greenred',$characterMetadata->averageKillValue,' ø killed',$characterMetadata->averageLossValue,' ø lost',formatISKShort($characterMetadata->averageKillValue),formatISKShort($characterMetadata->averageLossValue)); ?>
					</div>
				</div>
			</div>
			<div id="block-bottom" class="panel">
				<div class="listing-label">Recent activity</div>
        <div id="listing-selectors" class="listing-selected-all">
          (<span id="listing-selector-all">All</span> | <span id="listing-selector-kills">Kills</span> | <span id="listing-selector-losses">Losses</span>)
        </div>
        <div id="listing-container" data-type="character" data-typeid="<?=$characterId?>"></div>
			</div>
		</div>
	</body>
</html><?php render: require(__DIR__.'/../render/doRender.inc.php'); ?>