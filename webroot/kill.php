<?php
	require(__DIR__.'/../render/setup.inc.php');
	// @todo remove this from production
	set_time_limit(0);
	
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../helpers/cache.inc.php');
	require(__DIR__.'/../helpers/slotinfo.inc.php');
	require(__DIR__.'/../helpers/format.inc.php');
	
	$errorStr = null;
	if (!array_key_exists('killID',$_GET) || !$_GET['killID'])
	{
		$errorStr = 'Invalid killID specified.';
		goto render;
	}
	$killId = (int)$_GET['killID'];
	
	$db = killfeedDB();
	if (!$db)
	{
		doError('Database connection failed.',500);
		die();
	}
	
	try
	{
		$getKillQuery = prepareQuery($db,'SELECT
											`kill`.`victimCharacterId` as `victimCharacterId`,
											`kill`.`victimCorporationId` as `victimCorporationId`,
											`kill`.`victimAllianceId` as `victimAllianceId`,
											`kill`.`shipTypeId` as `shipTypeId`,
											`kill`.`solarSystemId` as `solarSystemId`,
											`kill`.`killTime` as `killTime`,
											`kill`.`damageTaken` as `damageTaken`,
											`kill`.`valueTotal` as `valueTotal`,
											`kill`.`valueDropped` as `valueDropped`,
											`kill`.`valueDestroyed` as `valueDestroyed`,
											`kill`.`valueHull` as `valueHull`,
											`kill`.`closestLocationId` as `closestLocationId`,
											`kill`.`closestLocationDistance` as `closestLocationDistance`,
											`char`.`characterName` as `victimCharacterName`,
											`corp`.`corporationName` as `victimCorporationName`,
											`alliance`.`allianceName` as `victimAllianceName`,
											`kill`.`mostCommonKillerShip` as `mostCommonKillerShip`,
											`kill`.`secondMostCommonKillerShip` as `secondMostCommonKillerShip`
										FROM `kill_metadata` as `kill`
										LEFT JOIN `character_metadata` as `char`
											ON `kill`.`victimCharacterId` = `char`.`characterId`
										LEFT JOIN `corporation_metadata` as `corp`
											ON `kill`.`victimCorporationId` = `corp`.`corporationId`
										LEFT JOIN `alliance_metadata` as `alliance`
											ON `kill`.`victimAllianceId` = `alliance`.`allianceId`
										WHERE `kill`.`id` = ?;');
		$getKillQuery->bindValue(1,$killId,PDO::PARAM_INT);
		if (!$getKillQuery->execute())
			throw new RuntimeException('DB error getting kill metadata for '.$killId.': '.$getKillQuery->errorInfo()[2]);
		if (!$getKillQuery->rowCount())
		{
			doError("Kill $killId not found.",404);
			goto render;
		}
		$killMetadata = $getKillQuery->fetchObject('DBKillMetadata'); // KILL METADATA IS HERE
		
		// do some display logic once here and then never again
		if ($killMetadata->victimCharacterId)
			$displayVictimName = $killMetadata->victimCharacterName;
		else
			$displayVictimName = $killMetadata->victimCorporationName;
	}
	catch (PDOException $e)
	{
		doError('Database error getting kill metadata: '.$e->getMessage(),500);
		die();
	}
	$solarSystem = getCachedSolarSystemInfo($killMetadata->solarSystemId);
  if ($solarSystem[5])
  {
    $securityShort = 'C'.$solarSystem[5];
    $securityLong = 'Class '.$solarSystem[5];
    if ($solarSystem[6])
    {
      $securityShort = $securityShort.' '.getWHEffectShort($solarSystem[6]);
      $securityLong = $securityLong.' - '.getWHEffectLong($solarSystem[6]);
    }
  }
  else
    $securityShort = number_format($solarSystem[1],1);
	
	try
	{
		$getItemsQuery = prepareQuery($db,'SELECT
											`slotId`,
											`index`,
											`hasChildren`,
											`isModule`,
											`typeId`,
											`quantity`,
											`dropped`,
											`value`
										FROM `kill_fittings`
										WHERE `killId` = ? AND `parent` IS NULL
										ORDER BY `slotId` ASC, `isModule` DESC, `index` ASC;');
		$getItemsQuery->bindValue(1,$killId,PDO::PARAM_INT);
		if (!$getItemsQuery->execute())
			throw new RuntimeException('DB error getting kill metadata for '.$killId.': '.$getItemsQuery->errorInfo()[2]);
		$killItems = fetchAll($getItemsQuery, 'DBKillFitting'); // KILL ITEMS ARE HERE
	}
	catch (PDOException $e)
	{
		doError('Database error getting kill fittings: '.$e->getMessage(),500);
		die();
	}
	
	
	try
	{
		$getAttackersQuery = prepareQuery($db,'SELECT
												`killer`.`killerCharacterId` as `characterId`,
												`killer`.`killerCharacterName` as `npcCharacterName`,
												`killer`.`killerCorporationId` as `corporationId`,
												`killer`.`killerAllianceId` as `allianceId`,
												`killer`.`killerShipTypeId` as `shipTypeId`,
												`killer`.`killerWeaponTypeId` as `weaponTypeId`,
												`killer`.`damageDone` as `damageDone`,
												`killer`.`damageFractional` as `damagePercent`,
												`killer`.`finalBlow` as `finalBlow`,
												`char`.`characterName` as `characterName`,
												`corp`.`corporationName` as `corporationName`,
												`alliance`.`allianceName` as `allianceName`
											FROM `kill_killers` as `killer`
											LEFT JOIN `character_metadata` as `char`
												ON `killer`.`killerCharacterId` = `char`.`characterId`
											LEFT JOIN `corporation_metadata` as `corp`
												ON `killer`.`killerCorporationId` = `corp`.`corporationId`
											LEFT JOIN `alliance_metadata` as `alliance`
												ON `killer`.`killerAllianceId` = `alliance`.`allianceId`
											WHERE `killer`.`killId` = ?
											ORDER BY `killer`.`damageDone` DESC;');
		$getAttackersQuery->bindValue(1,$killId,PDO::PARAM_INT);
		if (!$getAttackersQuery->execute())
			throw new RuntimeException('DB error getting kill metadata for '.$killId.': '.$getAttackersQuery->errorInfo()[2]);
		$killAttackers = fetchAll($getAttackersQuery, 'DBKillKiller');
	}
	catch (PDOException $e)
	{
		doError('Database error getting kill attackers: '.$e->getMessage(),500);
		die();
	}
	
	foreach ($killAttackers as &$attacker)
		if ($attacker->finalBlow)
		{
			$finalBlow = $attacker;
			break;
		}
	if (!isset($finalBlow)) // weird edge-case fall-back
		$finalBlow = $killAttackers[0];
	
	try
	{
		$getCommentsQuery = prepareQuery($db,'SELECT
												`comment`.`commentDate` as `commentDate`,
												`comment`.`commenter` as `commenterID`,
												`char`.`characterName` as `commenterName`,
												`comment`.`comment` as `comment`
											FROM `kill_comments` as `comment`
											LEFT JOIN `character_metadata` as `char`
												ON `comment`.`commenter` = `char`.`characterId`
											WHERE `comment`.`killId` = ?
											ORDER BY `comment`.`commentDate` DESC;');
		$getCommentsQuery->bindValue(1,$killId,PDO::PARAM_INT);
		if (!$getCommentsQuery->execute())
			throw new RuntimeException('DB error getting kill metadata for '.$killId.': '.$getCommentsQuery->errorInfo()[2]);
		$killComments = fetchAll($getCommentsQuery, 'DBKillComment');
	}
	catch (PDOException $e)
	{
		doError('Database error getting kill comments: '.$e->getMessage(),500);
		die();
	}
	
	// DATA COLLECTION IS NOW COMPLETE - BEGIN OUTPUT
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8"/>
		<title><?php
			if ($errorStr)
				echo 'E-UNI Killfeed - Error';
			else
				echo htmlentities(getCachedItemName($killMetadata->shipTypeId)),' - ',htmlentities($displayVictimName),' - E-UNI Killfeed';
		?></title>
		<link rel="stylesheet" href="css/navbar.css" />
		<link rel="stylesheet" href="css/killfeed.css" />
		<link rel="stylesheet" href="css/kill.css" />
		<script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
		<script src="js/kill.js"></script>
		<meta name="viewport" content="width=1000, initial-scale=1" /><?php if(!$errorStr)
		{ ?> 
		<meta name="twitter:card" content="summary" />
		<meta name="twitter:site" content="@EVEUniversity" />
		<meta name="twitter:title" content="<?=htmlspecialchars($displayVictimName)?>'s <?=htmlspecialchars(getCachedItemName($killMetadata->shipTypeId))?>" />
		<meta name="twitter:description" content="destroyed <?php $datePart = explode(' ',$killMetadata->killTime); if ($datePart[0] == gmdate('Y-m-d')) echo 'at ',htmlspecialchars($datePart[1]); else echo 'on ',htmlspecialchars($datePart[0]); ?> in <?=$solarSystem[0]?> (<?=isset($securityLong)?$securityLong:$securityShort?>) by <?=$finalBlow->characterName?><?php if(count($killAttackers) > 1) { ?> and <?=count($killAttackers)-1?> other<?php if(count($killAttackers) > 2) { ?>s<?php } } ?>. Total value: <?=formatISKShort($killMetadata->valueTotal)?> ISK" />
		<meta name="twitter:image" content="http://image.eveonline.com/Render/<?=$killMetadata->shipTypeId?>_128.png" /><?php
		} ?> 
	</head><?php
	if ($errorStr)
	{ ?> 
	<body><div id="error">An error occurred while retrieving this killmail:</div><div id="error-msg"><?=$errorStr?></div></body><?php
	}
	else
	{ ?> 
	<body>
		<?php include(__DIR__.'/../render/navbar.inc.php'); ?>
		<div id="content"><div id="column-left">
			<div class="panel" id="quickinfo">
				<div id="quickinfo-top">
					<div class="quickinfo-block">
						<div class="quickinfo-left">Date:</div>
						<div class="quickinfo-right"><?=$killMetadata->killTime?></div>
					</div>
					<div class="quickinfo-block">
						<div class="quickinfo-left">Location:</div>
						<div class="quickinfo-right"><span class="optional"><?=$solarSystem[3]?> &gt; <!-- @todo link --></span><?=$solarSystem[0]?> (<span style="color: <?=getSecStatusColor($solarSystem[1])?>"<?php if(isset($securityLong)) echo ' title="',$securityLong,'"'; ?>><?=$securityShort?></span>)</div>
					</div>
				</div>
				<div id="quickinfo-middle">
					<div id="quickinfo-killer">
						<div id="quickinfo-killer-avatar">
							<?php
								if ($finalBlow->characterId) {
							?><img class="fill" src="http://image.eveonline.com/Character/<?=$finalBlow->characterId?>_256.jpg" /><?php
								}
								else
								{
							?><img class="fill" src="http://image.eveonline.com/Type/<?=$finalBlow->shipTypeId?>_64.png" /><?php
								}
							?> 
						</div>
						<div id="quickinfo-killer-ship">
							<img class="fill" src="http://image.eveonline.com/Type/<?=$finalBlow->shipTypeId?>_32.png" />
						</div>
						<div id="quickinfo-killer-name"><a<?php if ($finalBlow->characterId) { ?> href="character.php?characterID=<?=$finalBlow->characterId?>"<?php } ?>><?=$finalBlow->characterName?></a></div>
						<div id="quickinfo-killer-corp"><a<?php if ($finalBlow->corporationId) { ?> href="corporation.php?corporationID=<?=$finalBlow->corporationId?>"<?php } ?>><?=$finalBlow->corporationName?></a></div>
						<div id="quickinfo-killer-alliance"><a<?php if($finalBlow->allianceId) { ?> href="alliance.php?allianceID=<?=$finalBlow->allianceId?>"<?php } ?>><?=$finalBlow->allianceName?></a></div>
					</div>
					<div id="quickinfo-icon">
						<img class="fill" src="img/swords.png">
					</div>
					<div id="quickinfo-icon-text" class="noselect">killed by</div>
					<div id="quickinfo-victim">
						<div id="quickinfo-victim-avatar"><?php
						if ($killMetadata->victimCharacterId)
						{ ?> 
							<img class="fill" src="http://image.eveonline.com/Character/<?=$killMetadata->victimCharacterId?>_256.jpg" /><?php
						}
						else if ($killMetadata->victimCorporationId)
						{ ?> 
							<img class="fill" src="http://image.eveonline.com/Corporation/<?=$killMetadata->victimCorporationId?>_256.png" /><?php
						}
						else
						{?> 
							<img class="fill" src="http://image.eveonline.com/Character/0_256.jpg" /><?php
						} ?> 
						</div>
						<div id="quickinfo-victim-ship">
							<img class="fill" src="http://image.eveonline.com/Type/<?=$killMetadata->shipTypeId?>_32.png" />
						</div>
						<div id="quickinfo-victim-name"><a<?php if($killMetadata->victimCharacterId) { ?> href="character.php?characterID=<?=$killMetadata->victimCharacterId?>"<?php } ?>><?=$killMetadata->victimCharacterId?$killMetadata->victimCharacterName:getCachedItemName($killMetadata->shipTypeId)?></a></div>
						<div id="quickinfo-victim-corp"><a<?php if($killMetadata->victimCorporationId) { ?> href="corporation.php?corporationID=<?=$killMetadata->victimCorporationId?>"<?php } ?>><?=$killMetadata->victimCorporationName?></a></div>
						<div id="quickinfo-victim-alliance"><a<?php if($killMetadata->victimAllianceId) { ?> href="alliance.php?allianceID=<?=$killMetadata->victimAllianceId?>"<?php } ?>><?=$killMetadata->victimAllianceName?></a></div>
					</div>
				</div>
			</div>
			<div class="panel" id="fitting-panel">
				<div id="fitting-panel-ship"><img class="fill" src="http://image.eveonline.com/Render/<?=$killMetadata->shipTypeId?>_256.png" /></div>
				<div id="fitting-panel-background"><img class="fill" src ="img/panel.png" /></div>
				<div id="fitting-panel-slots"><?php
					$slotCount = getCachedItemSlots($killMetadata->shipTypeId);
					foreach ($killItems as &$item) // find subsystems
					{
						if (!isSlotFittingSlot($item->slotId))
							break;
						if (getSlotCategory($item->slotId) == SUBSYSTEM1)
						{
							$thisSlots = getCachedItemSlots($item->typeId);
							$slotCount[0] += $thisSlots[0];
							$slotCount[1] += $thisSlots[1];
							$slotCount[2] += $thisSlots[2];
							$slotCount[3] += $thisSlots[3];
						}
					}
					$expectedSlots = array();
					for ($i=0;$i<$slotCount[0];++$i)
						$expectedSlots[] = HIGHSLOT1+$i;
					for ($i=0;$i<$slotCount[1];++$i)
						$expectedSlots[] = MIDSLOT1+$i;
					for ($i=0;$i<$slotCount[2];++$i)
						$expectedSlots[] = LOWSLOT1+$i;
					for ($i=0;$i<$slotCount[3];++$i)
						$expectedSlots[] = RIGSLOT1+$i;
					for ($i=0;$i<$slotCount[4];++$i)
						$expectedSlots[] = SUBSYSTEM1+$i;
					$expectedIndex = 0;
					if (isset($expectedSlots[0]))
					{
						$nextExpected = $expectedSlots[0];
						foreach ($killItems as &$item)
						{
							// if we're done with fitting slots, stop
							if (!isSlotFittingSlot($item->slotId))
								break;
							
							// render empty slots
							while ($item->slotId > $nextExpected)
							{
								echo '<img class="fill slot" src="img/slots/',$nextExpected,'u.png" />';
								++$expectedIndex;
								if (isset($expectedSlots[$expectedIndex]))
									$nextExpected = $expectedSlots[$expectedIndex];
								else
									$nextExpected = INF; // everything else must be charges
							}
							
							if ($item->slotId < $nextExpected) // this is a charge attached to a slot
								$itemStyle = 'charge';
							elseif ($item->slotId == $nextExpected)
							{
								$itemStyle = 'module';
								echo '<img class="fill slot" src="img/slots/',$nextExpected,'f.png" />';
								++$expectedIndex;
								if (isset($expectedSlots[$expectedIndex]))
									$nextExpected = $expectedSlots[$expectedIndex];
								else
									$nextExpected = INF; // everything else must be ammo
							}
							echo '<div title="',getCachedItemName($item->typeId),'" class="fitting-',$itemStyle,' slot-',$item->slotId,'-',$itemStyle,'"><img class="fill" src="http://image.eveonline.com/Type/',$item->typeId,'_32.png" /></div>';
						}
						
						while ($nextExpected !== INF)
						{
							echo '<img class="fill slot" src="img/slots/',$nextExpected,'u.png" />';
							++$expectedIndex;
							if (isset($expectedSlots[$expectedIndex]))
								$nextExpected = $expectedSlots[$expectedIndex];
							else
								$nextExpected = INF;
						}
					}?> 
				</div>
			</div>
			<div class="panel" id="comments">
				<div id="comments-title" class="noselect">Comments</div><?php
				if (empty($killComments))
				{ ?> 
				<div id="no-comments">
					<span class="noselect">Nobody has commented on this kill yet.</span><br /><?php
					if (!isLoggedIn())
					{ ?> 
					<div id="comment-login" class="noselect">If you were involved and would like to let us know what happened, <a style="cursor: pointer; font-weight: bold;" onClick="expandLogin();">log in</a> to comment.</div><?php
					}
					else
					{ ?> 
					<div id="comment-form">
						<div id="comment-form-avatar"><img class="fill" src="http://image.eveonline.com/Character/<?=getCurrentUserCharacterId()?>_128.jpg" /></div>
						<div id="comment-form-username"><?=htmlentities(getCurrentUserName())?></div>
						<div id="comment-form-input">
							<textarea id="comment-form-inputarea"></textarea>
							<div id="comment-form-inputoverlay" onClick="this.style.display = 'none'; this.previousElementSibling.focus();">Let us know what happened...</div>
						</div>
						<div id="comment-form-submit" class="hidden"></div>
					</div><?php
					} ?> 
				</div><?php
				}
				else
				{ ?> 
				<div id="comments-wrapper"><?php
					if (isLoggedIn())
					{ ?> 
					<div id="comment-form">
						<div id="comment-form-avatar"><img class="fill" src="http://image.eveonline.com/Character/<?=getCurrentUserCharacterId()?>_128.jpg" /></div>
						<div id="comment-form-username" class="noselect"><?=htmlentities(getCurrentUserName())?></div>
						<div id="comment-form-input">
							<textarea id="comment-form-inputarea"></textarea>
							<div id="comment-form-inputoverlay" onClick="this.style.display = 'none'; this.previousElementSibling.focus();">Let us know what happened...</div>
						</div>
						<div id="comment-form-submit" class="hidden"></div>
					</div><?php
					}
					else
					{ ?> 
					<div id="comment-login">If you were involved and would like to let us know what happened, <a style="cursor: pointer; font-weight: bold;" onClick="expandLogin();">log in</a> to comment.</div><?php
					}
					
					foreach ($killComments as &$comment)
					{ ?> 
					<div class="comment-entry">
						<div class="comment-commenter-avatar">
							<img class="fill" src="http://image.eveonline.com/Character/<?=$comment->commenterID?>_128.jpg" />
						</div>
						<div class="comment-commenter-name"><!-- @todo link --><?=$comment->commenterName?><div class="comment-date"><?=$comment->commentDate?></div></div>
						<div class="comment-text"><?=htmlentities($comment->comment,ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED|ENT_HTML5,'UTF-8')?></div>
					</div><?php
					} ?> 
				</div><?php
				} ?> 
			</div>
		</div><div id="column-center">
			<div class="panel" id="modules">
				<div class="item-entry ship">
					<div class="item-icon">
						<img src="http://image.eveonline.com/Type/<?=$killMetadata->shipTypeId?>_32.png" class="fill" />
					</div>
					<div class="item-name"><?=getCachedItemName($killMetadata->shipTypeId)?></div>
					<div class="item-value"><?=formatISKShort($killMetadata->valueHull)?></div>
				</div><?php
		function renderItems($killItems,$parentSlot,$depth)
		{
			global $killId;
			static $getItemQuery = null;
			$lastSlotCategory = 0;
			foreach ($killItems as &$item)
			{
				$slotId = $parentSlot ? $parentSlot : $item->slotId;
				$category = getSlotCategory($slotId);
				$isFittingSlot = isSlotFittingSlot($slotId);
				if (!$parentSlot && $lastSlotCategory != $category)
				{ // output category header
					$lastSlotCategory = $category; ?> 
				<div class="category-header"><?php
					if (isSlotFittingSlot($category))
					{ ?> 
					<div class="category-icon"><img src="img/category-<?=$category?>.png" class="fill" /></div><?php
					} ?> 
					<div class="category-label"><?=stringifySlot($category,true)?></div>
				</div><?php
				} ?> 
				<div class="item-entry <?php
					if ($item->dropped) echo ' dropped'; else echo ' destroyed';
					if ($isFittingSlot)
					{
						if($item->isModule) echo ' fitting';
						else echo ' ammo';
					}
					else
						echo ' cargo';
					$effectiveOffset = 15*($depth+($isFittingSlot && !$item->isModule));
				?>">
					<div class="item-icon" style="left: <?=10+$effectiveOffset?>px;">
						<img src="http://image.eveonline.com/Type/<?=$item->typeId?>_32.png" class="fill" />
					</div>
					<div class="item-name" style="left: <?=45+$effectiveOffset?>px; width: <?=360-$effectiveOffset?>px;"><?=getCachedItemName($item->typeId)?></div>
					<?php if (!$isFittingSlot || !$item->isModule)
					{ ?><div class="item-quantity"><?=number_format($item->quantity)?></div><?php
					} ?> 
					<div class="item-value"><?=formatISKShort($item->value)?></div>
				</div><?php
				if ($item->hasChildren)
				{
					try
					{
						if (!$getItemQuery)
							$getItemQuery = prepareQuery(killfeedDB(),'SELECT
																`slotId`,
																`isModule`,
																`hasChildren`,
																`isModule`,
																`typeId`,
																`quantity`,
																`dropped`,
																`value`
															FROM `kill_fittings`
															WHERE `killId` = ? and `parent` = ?
															ORDER BY `slotId` ASC, `isModule` DESC, `index` ASC');
						$getItemQuery->bindValue(1,$killId,PDO::PARAM_INT);
						$getItemQuery->bindValue(2,$item->index,PDO::PARAM_INT);
						$getItemQuery->execute();
						
						$items = fetchAll($getItemQuery, 'DBKillFitting');
						renderItems($items,$slotId,$depth+1);
					}
					catch (PDOException $e)
					{
						doError('Database error while getting children item data for '.$$item->index.': '.$e->getMessage(),500);
						die();
					}
				}
			}
		}
		renderItems($killItems,0,0); ?> 
			</div>
		</div><div id="column-right">
			<div class="panel" id="quickinfo2">
				<div id="quickinfo-bottom">
					<?php if($killMetadata->closestLocationId)
					{
					?><div class="quickinfo-block">
						<div class="quickinfo-left">Near:</div>
						<div class="quickinfo-right"><?=/* @todo get location name */$killMetadata->closestLocationId?> (<?=number_format($killMetadata->closestLocationDistance,2)?> AU away)</div>
					</div><?php
					}
					?> 
					<div class="quickinfo-block">
						<div class="quickinfo-left">Damage:</div>
						<div class="quickinfo-right"><?=number_format($killMetadata->damageTaken)?></div>
					</div><?php
					$valueDestroyedStr = number_format($killMetadata->valueDestroyed,2);
					$valueDroppedStr = number_format($killMetadata->valueDropped,2);
					$valueTotalStr = number_format($killMetadata->valueTotal,2); ?> 
					<div class="quickinfo-block">
						<div class="quickinfo-left">Destroyed:</div>
						<div class="quickinfo-right"><?php for($i=strlen($valueDestroyedStr);$i<strlen($valueTotalStr);++$i) echo '&nbsp;'; echo $valueDestroyedStr; ?> ISK</div>
					</div>
					<div class="quickinfo-block">
						<div class="quickinfo-left">Dropped:</div>
						<div class="quickinfo-right"><?php for($i=strlen($valueDroppedStr);$i<strlen($valueTotalStr);++$i) echo '&nbsp;'; echo $valueDroppedStr; ?> ISK</div>
					</div>
					<div class="quickinfo-block">
						<div class="quickinfo-left">Total:</div>
						<div class="quickinfo-right"><?=$valueTotalStr?> ISK</div>
					</div>
				</div>
			</div>
			<?php $shouldCollapse = (count($killAttackers) > 8); // magic number ?>
			<div class="panel<?php if($shouldCollapse) echo " attackers-collapsed"; ?>" id="attackers"><?php
			if ($shouldCollapse)
			{ ?> 
				<div id="attackers-summary">
					<div id="attackers-summary-ship1"<?php if(!$killMetadata->secondMostCommonKillerShip) echo ' class="only-attacker-ship"'; ?>>
						<img class="fill" src="http://image.eveonline.com/Type/<?=$killMetadata->mostCommonKillerShip?>_32.png" />
					</div><?php
					if ($killMetadata->secondMostCommonKillerShip)
					{ ?> 
					<div id="attackers-summary-ship2">
						<img class="fill" src="http://image.eveonline.com/Type/<?=$killMetadata->secondMostCommonKillerShip?>_32.png" />
					</div><?php
					} ?> 
					<div id="attackers-summary-header"><?=count($killAttackers)?> attackers<span class="optional"> involved</span> <div id="attackers-expand-link">(Show all)</div></div>
					<div id="attackers-summary-info">piloting mostly <b><?=getCachedItemName($killMetadata->mostCommonKillerShip)?></b><?php if($killMetadata->secondMostCommonKillerShip) { ?><span class="optional"> and <b><?=getCachedItemName($killMetadata->secondMostCommonKillerShip)?></b></span><?php } ?></div>
				</div><?php
			}
			foreach ($killAttackers as &$attacker)
			{ ?> 
				<div class="attacker-entry<?php if ($attacker->finalBlow) echo ' finalblow'; ?>">
					<?php if ($attacker->characterId) {
					?><div class="attacker-avatar">
						<img class="fill" src="http://image.eveonline.com/Character/<?=$attacker->characterId?>_128.jpg" />
					</div>
					<div class="attacker-ship">
						<img class="fill" src="http://image.eveonline.com/Type/<?=$attacker->shipTypeId?>_32.png" />
					</div><?php
					if ($attacker->shipTypeId != $attacker->weaponTypeId)
					{ ?> 
					<div class="attacker-weapon">
						<img class="fill" src="http://image.eveonline.com/Type/<?=$attacker->weaponTypeId?>_32.png" />
					</div><?php
					} ?> 
					<div class="attacker-name"><a<?php if($attacker->characterId) { ?> href="character.php?characterID=<?=$attacker->characterId?>"<?php } ?>><?=$attacker->characterName?></a></div><?php
					} else {
					?><div class="attacker-avatar">
						<img class="fill" src="http://image.eveonline.com/Type/<?=$attacker->shipTypeId?>_64.png" />
					</div>
					<div class="attacker-name"><?=$attacker->npcCharacterName?></div><?php
					}
					?> 
					<div class="attacker-corporation"><a<?php if($attacker->corporationId) { ?> href="corporation.php?corporationID=<?=$attacker->corporationId?>"<?php } ?>><?=$attacker->corporationName?></a></div><?php
					if ($attacker->allianceId)
					{ ?> 
					<div class="attacker-alliance"><a href="alliance.php?allianceID=<?=$attacker->allianceId?>"><?=$attacker->allianceName?></a></div><?php
					} ?> 
					<div class="attacker-types"><?=getCachedItemName($attacker->shipTypeId)?><?php if($attacker->shipTypeId != $attacker->weaponTypeId) { ?> / <?=getCachedItemName($attacker->weaponTypeId)?><?php } ?></div>
					<div class="attacker-damage"><?=number_format($attacker->damageDone)?></div>
					<div class="attacker-damage-pct"><?=$attacker->damagePercent?>%</div>
				</div><?php
			} ?> 
			</div>
		</div></div>
	</body><?php
	} ?> 
</html><?php render: require(__DIR__.'/../render/doRender.inc.php'); ?>