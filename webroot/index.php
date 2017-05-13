<?php
	require(__DIR__.'/../render/setup.inc.php');
	define('OUR_ALLIANCE_ID',937872513);
	define('CACHE_INTERVAL_SECONDS',30);
	define('CACHE_FILE',__DIR__.'/../cache/index.html');
	// @todo remove this probably
	set_time_limit(0);
	
	require(__DIR__.'/../helpers/session.inc.php');
	$loggedIn = isset($_SESSION['userid']) && $_SESSION['userid'];
	
	if (!$loggedIn)
	{
		$lastCachedTime = file_exists(CACHE_FILE) ? filemtime(CACHE_FILE) : 0;
		if ($lastCachedTime && time()-$lastCachedTime < CACHE_INTERVAL_SECONDS)
		{
			$cacheFile = fopen(CACHE_FILE, 'r');
			if ($cacheFile && flock($cacheFile, LOCK_SH))
			{
				header('Expires: '.gmdate('D, d M Y H:i:s',$lastCachedTime+CACHE_INTERVAL_SECONDS).' GMT');
				fpassthru($cacheFile);
				flock($cacheFile,LOCK_UN);
			}
			else
			{
				doError('Error: Failed to acquire lock on cached index.',500);
				fclose($cacheFile);
				goto render;
			}
			if ($cacheFile)
				fclose($cacheFile);
			return;
		}
		ob_start();
	}
	
	// Build new version
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../render/killListing.inc.php');
	
	try
	{
		$getDailyHistoryQuery = prepareQuery(killfeedDB(),'SELECT DATE_FORMAT(`day`,\'%d %b\') as `day`,DATEDIFF(UTC_TIMESTAMP(),`day`) as `daysAgo`,`totalKillValue`,`topKillId`,`topKillValue`,`topKillShipType` FROM `kill_day_history` WHERE `day` >= DATE_ADD(DATE(UTC_TIMESTAMP()), INTERVAL -55 DAY) ORDER BY `daysAgo` desc');
		$getDailyHistoryQuery->execute();
		$dailyHistory = fetchAll($getDailyHistoryQuery,'DBDailyHistoryEntry');
		
		$getKillQuery = prepareQuery(killfeedDB(),'SELECT
										`kill`.`id` as `killId`,
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
										(`kill`.`victimAllianceId` = ?) as `isLoss`
									FROM `kill_metadata` as `kill`
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
									WHERE `kill`.`valueTotal` > 100000
									ORDER BY `kill`.`id` DESC
									LIMIT 100;');
		$getKillQuery->bindValue(1,OUR_ALLIANCE_ID,PDO::PARAM_INT);
		$getKillQuery->execute();
		$kills = fetchAll($getKillQuery,'DBKillListEntry');
	}
	catch (PDOException $e)
	{
		doError('Database error building index page: '.$e->getMessage(),500);
		ob_end_clean();
		goto render;
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8"/>
		<title>E-UNI Killfeed - Recent Activity</title>
		<link rel="stylesheet" href="css/navbar.css" />
		<link rel="stylesheet" href="css/killfeed.css" />
		<link rel="stylesheet" href="css/listing.css" />
		<link rel="stylesheet" href="css/linechart.css" />
		<link rel="stylesheet" href="css/index.css" />
		<script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
		<script src="js/index.js"></script>
		<meta name="viewport" content="width=1000, initial-scale=1" />
	</head>
	<body>
		<?php include(__DIR__.'/../render/navbar.inc.php'); ?>
		<div id="content">
			<div id="block-top" class="panel">
				<div id="past-days-label">Recent PvP activity</div><?php
					$pastDaysChart = array(); // entries: [day, dx, value, hasLabel, dy, topKillId, topKillShip, topKillValue] (dx/dy rel to chart space, not svg space)
					$hadLabelForWeek = array(); //         0    1   2      3         4   5          6            7
					$topValue = 0;
					
					foreach ($dailyHistory as $dayMeta)
					{
						$week = floor($dayMeta->daysAgo/7);
						$dayofweek = $dayMeta->daysAgo % 7;
						if ($week > 0)
						{
							if (isset($hadLabelForWeek[$week]))
								$hasLabel = false;
							else
							{
								$hasLabel = true;
								$hadLabelForWeek[$week] = true;
							}
							// dx = 480 - 30*6 - 6 - (week-1)*42 - (dayofweek)*6
							$dx = 336 - (42*$week) - (6*$dayofweek);
						}
						else
						{
							$hasLabel = true;
							$dx = 480 - $dayMeta->daysAgo * 30;
						}
						$pastDaysChart[] = array($dayMeta->day,$dx,$dayMeta->totalKillValue,$hasLabel,null,$dayMeta->topKillId,$dayMeta->topKillShipType,$dayMeta->topKillValue);
						if ($dayMeta->totalKillValue > $topValue)
							$topValue = $dayMeta->totalKillValue;
					}
					
					if (!$topValue)
						$topValue = 1; // edge case
					
					$axisPower = pow(10,floor(log($topValue/4,10)));
					$axisDelta = floor(($topValue/7)/$axisPower)*$axisPower;
					if (!$axisDelta)
						$axisDelta = $topValue/7;
					
					
					$lastLabelX = INF;
					$topKillValue = -1;
					$topKillId = 0;
					$topKillShip = 0;
					// do some post-processing on the finished list
					for ($i=count($pastDaysChart)-1;$i>=0;--$i)
					{
						// set dy relative to top value
						$pastDaysChart[$i][4] = ($topValue-$pastDaysChart[$i][2])*(80/$topValue);
						
						// stack top kills into labeled nodes
						if ($pastDaysChart[$i][7] > $topKillValue)
						{
							$topKillValue = $pastDaysChart[$i][7];
							$topKillId = $pastDaysChart[$i][5];
							$topKillShip = $pastDaysChart[$i][6];
						}
						
						if ($pastDaysChart[$i][3])
						{ // remove labels that are too close together
							if ($lastLabelX - $pastDaysChart[$i][1] > 15)
							{
								$lastLabelX = $pastDaysChart[$i][1];
								
								// this is a label node - set top kill data and reset loop vars for top kill
								$pastDaysChart[$i][5] = $topKillId;
								$pastDaysChart[$i][6] = $topKillShip;
								$pastDaysChart[$i][7] = $topKillValue;
								
								$topKillValue = -1; // will make other vars be overwritten on next iteration
							}
							else
								$pastDaysChart[$i][3] = false;
						}
					}
				?> 
				<svg id="past-days-chart" class="linechart" viewBox = "0 0 505 105" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
					<g class="grid">
						<text class="axis-label" x="15" y="8">ISK</text>
						<line class="axis" x1="10" y1="95" x2="500" y2="95" />
						<line class="axis" x1="15" y1="100" x2="15" y2="11" />
						<line class="axis" x1="13" y1="13" x2="15" y2="11" />
						<line class="axis" x1="17" y1="13" x2="15" y2="11" /><?php
						$v = $axisDelta;
						while ($v <= $topValue)
						{
							$thisY = 15+($topValue-$v)*(80/$topValue); ?> 
						<line class="label" x1="15" y1="<?=$thisY?>" x2="500" y2="<?=$thisY?>" />
						<text class="label" x="21" y="<?=$thisY+1?>"><?=formatISKShort($v)?></text><?php
							$v = $v + $axisDelta;
						} ?> 
					</g>
					<g class="chart-lines"><?php
						$lastX = 0;
						$lastY = 0;
						foreach ($pastDaysChart as $dayPoint)
						{
							$thisX = 15+$dayPoint[1];
							$thisY = 15+$dayPoint[4];
							if ($lastX && $lastY)
							{ ?> 
						<line x1="<?=$lastX?>" y1="<?=$lastY?>" x2="<?=$thisX?>" y2="<?=$thisY?>" /><?php
							}
							$lastX = $thisX;
							$lastY = $thisY;
						} ?> 
					</g>
					<g class="topkills"><?php
						foreach ($pastDaysChart as $dayPoint)
						{
							if (!$dayPoint[3]) continue;
							?>  
						<g class="topkill-day"><a xlink:href="kill.php?killID=<?=$dayPoint[5]?>" target="_blank">
							<image class="type" x="<?=8+$dayPoint[1]?>" y="0" width="13" height="13" xlink:href="http://image.eveonline.com/Type/<?=$dayPoint[6]?>_64.png" />
							<rect class="value-bg" x="<?=8+$dayPoint[1]?>" y="8" width="13" height="5" />
							<text class="value" x="<?=15+$dayPoint[1]?>" y="12"><?=formatISKShort($dayPoint[7])?></text>
						</a></g><?php
						} ?> 
					</g>
					<g class="chart-points"><?php
						foreach ($pastDaysChart as $dayPoint)
						{ ?> 
						<g class="chart-point-label">
							<circle cx="<?=15+$dayPoint[1]?>" cy="<?=15+$dayPoint[4]?>" r="<?=$dayPoint[3]?2:1?>" />
							<rect class="hoverlabel-bg" x="<?=5+$dayPoint[1]?>" y="96" width="20" height="7" />
							<text class="label<?php if(!$dayPoint[3]) echo ' hoverlabel'; ?>" x="<?=15+$dayPoint[1]?>" y="101"><?=htmlentities($dayPoint[0])?></text>
							<rect class="hovertext-bg" x="<?=$dayPoint[1]?>" y="<?=7+$dayPoint[4]?>" width="30" height="7" />
							<text class="hovertext" x="<?=15+$dayPoint[1]?>" y="<?=12+$dayPoint[4]?>"><?=formatISKShort($dayPoint[2])?> ISK</text>
						</g><?php
						} ?> 
					</g>
				</svg>
			</div>
			<div id="block-bottom" class="panel">
				<div class="listing-label">Latest kills</div><?php
				renderKillListing($kills); ?> 
			</div>
		</div>
	</body>
</html>
<?php
	render:
	require(__DIR__.'/../render/doRender.inc.php');
	if ($loggedIn || doError(1)) // doError(1) -> had error? boolean
		return;
	ignore_user_abort(true);
	header('Expires: '.gmdate('D, d M Y H:I:s',time()+CACHE_INTERVAL_SECONDS).' GMT');
	header('Content-Length: '.ob_get_length());
	header('Connection: close');
	$cacheContent = ob_get_flush();
	if (session_id()) session_write_close();
	
	$cacheFile = fopen(CACHE_FILE,'c');
	if (!$cacheFile)
	{
		doError('Failed to open cache file for index',200);
		return;
	}
	if (flock($cacheFile, LOCK_EX | LOCK_NB, $wouldBlock))
	{
		ftruncate($cacheFile,0);
		fwrite($cacheFile, $cacheContent);
		fflush($cacheFile);
		flock($cacheFile, LOCK_UN);
	}
	fclose($cacheFile);
?>