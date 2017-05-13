<?php
	require_once(__DIR__.'/../helpers/cache.inc.php');
	require_once(__DIR__.'/../helpers/format.inc.php');
	
	/* $kills is typically array of 'DBKillListEntry' */
	function renderKillListing($kills)
	{
		$lastKillDate = null;
		foreach ($kills as $kill)
		{
			$solarSystemData = getCachedSolarSystemInfo($kill->solarSystemId);
			if ($kill->killDate != $lastKillDate)
			{ ?>
			<div class="listing-date-label">
			<?=formatDate($kill->killDate,$kill->daysAgo)?>
			</div><?php
				$lastKillDate = $kill->killDate;
			}
		?> 
			<div class="listing-entry<?php if ($kill->isLoss) echo ' loss'; else echo ' kill'; ?>"><a href="kill.php?killID=<?=$kill->killId?>">
				<div class="listing-kill-time"><?=$kill->killTime?></div>
				<div class="listing-kill-value"><?=formatISKShort($kill->valueTotal)?></div>
				<div class="listing-victim-avatar"><img class="fill" src="http://image.eveonline.com/Character/<?=$kill->victimCharacterId?>_64.jpg" /></div>
				<div class="listing-victim-icon"><img class="fill" src="http://image.eveonline.com/Type/<?=$kill->victimShipTypeId?>_64.png" /></div>
				<div class="listing-victim-name"><?=$kill->victimCharacterName?></div>
				<div class="listing-victim-corp"><?php echo $kill->victimCorporationName; if ($kill->victimAllianceId) echo '&nbsp;/&nbsp;',$kill->victimAllianceName; ?></div>
				<div class="listing-victim-ship"><?=getCachedItemName($kill->victimShipTypeId)?> / <?=$solarSystemData[0]?> (<span style="color: <?=getSecStatusColor($solarSystemData[1])?>"><?=number_format($solarSystemData[1],1)?></span>) </div>
				<div class="listing-killer-icon1<?php if(!$kill->secondMostCommonKillerShip) echo ' offset'; ?>"><img class="fill" src="http://image.eveonline.com/Type/<?=$kill->mostCommonKillerShip?>_64.png" /></div>
				<?php if($kill->secondMostCommonKillerShip) { ?><div class="listing-killer-icon2"><img class="fill" src="http://image.eveonline.com/Type/<?=$kill->secondMostCommonKillerShip?>_64.png" /></div><?php } ?>
				<div class="listing-killer-name">by <span style="font-weight: bold;"><?=$kill->killerCharacterId?$kill->killerCharacterName:getCachedItemName($kill->killerShipTypeId)?></span><?php if($kill->numKillers > 1) echo ' and ',$kill->numKillers-1,' others'; ?></div>
				<div class="listing-killer-corp"><?php echo $kill->killerCorporationName; if ($kill->killerAllianceId) echo '&nbsp;/&nbsp;',$kill->killerAllianceName; ?></div>
				<div class="listing-killer-ship">using <span style="font-weight: bold;"><?=getCachedItemName($kill->mostCommonKillerShip)?></span><?php if ($kill->secondMostCommonKillerShip) echo ' and <span style="font-weight: bold;">',getCachedItemName($kill->secondMostCommonKillerShip),'</span>'; ?></div>
			</a></div><?php
		}
	}
?>