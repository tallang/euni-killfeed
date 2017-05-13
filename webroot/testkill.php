<?php require(__DIR__.'/../render/setup.inc.php'); ?><!DOCTYPE html> <html><body><pre><?php
	set_time_limit(0);
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../helpers/cache.inc.php');
	require(__DIR__.'/../helpers/slotinfo.inc.php');
	
	if (!array_key_exists('killID',$_GET) || !$_GET['killID'])
	{
		doError('Please specify ?killID=',400);
		goto error;
	}
	$killId = (int)$_GET['killID'];
	
	$db = killfeedDB();
	try
	{
		$getKillQuery = prepareQuery($db,'SELECT kill.victimCharacterId, kill.victimCorporationId, kill.victimAllianceId, kill.shipTypeId, kill.solarSystemId, kill.killTime, kill.damageTaken, kill.valueTotal, kill.valueDropped, kill.valueHull, char.characterName, corp.corporationName, alliance.allianceName
										FROM kill_metadata AS `kill`
										LEFT JOIN character_metadata AS `char` on kill.victimCharacterId = char.characterId
										LEFT JOIN corporation_metadata AS `corp` on kill.victimCorporationId = corp.corporationId
										LEFT JOIN alliance_metadata AS `alliance` on kill.victimAllianceId = alliance.allianceId
										WHERE kill.id = ?;');
		$getKillQuery->bindValue(1,$killId,PDO::PARAM_INT);
		$getKillQuery->execute();
		if (!$getKillQuery->rowCount())
		{
			doError('Specified kill was not found.',404);
			goto error;
		}
		$killData = $getKillQuery->fetchObject();
	}
	catch (PDOException $e)
	{
		doError('Database error fetching killmail meta: '.$e->getMessage(),500);
		die();
	}
?>
Killmail loaded from database successfully!
Date: <?=$killData->killTime?> 
<?php $locationData = getCachedSolarSystemInfo($killData->solarSystemId); ?>
Location: <?=$locationData[0]?> (<?=$locationData[1]?>) &lt; <?=$locationData[2]?> &lt; <?=$locationData[3]?> 
Victim: <?=$killData->characterName?> of [<?=$killData->corporationName?>] <?php if($killData->allianceName != '') { ?>&lt;<?=$killData->allianceName?>&gt;<?php } ?> 
Ship lost: <?=getCachedItemName($killData->shipTypeId)?> (<?=number_format($killData->valueHull,2)?> ISK)
Total value: <?=number_format($killData->valueTotal,2)?> ISK (<?=number_format($killData->valueDropped,2)?> ISK dropped)

<?php
	try
	{
		$getItemsQuery = prepareQuery($db,'SELECT slotId,isModule,typeId,quantity,dropped,value FROM kill_fittings WHERE killId = ? ORDER BY slotId asc, isModule desc');
		$getItemsQuery->bindValue(1,$killId,PDO::PARAM_INT);
		$getItemsQuery->execute();
		
		$lastSlotCategory = 0;
		while ($itemsData = $getItemsQuery->fetchObject())
		{
			$slotCategory = getSlotCategory($itemsData->slotId);
			if ($slotCategory != $lastSlotCategory)
			{
				$lastSlotCategory = $slotCategory;
				echo "\n",stringifySlot($slotCategory,true),":","\n";
			}
			if (isSlotFittingSlot($itemsData->slotId))
			{
				if ($itemsData->isModule)
					echo '[Module] ';
				else
					echo '[Ammo  ] ';
			}
			echo $itemsData->quantity,"x ",getCachedItemName($itemsData->typeId)," (valued at ",number_format($itemsData->value,2)," ISK, ",($itemsData->dropped?"dropped":"destroyed"),")\n";
		}
		echo "\n";
	}
	catch (PDOException $e)
	{
		doError('Database error fetching killmail items: '.$e->getMessage(),500);
		die();
	}
	
	try
	{
		$getAttackersQuery = prepareQuery($db,'SELECT killer.killerCharacterId,killer.killerCharacterName,killer.killerShipTypeId,killer.killerWeaponTypeId,killer.damageDone,killer.damageFractional,killer.finalBlow,char.characterName,corp.corporationName,alliance.allianceName
											FROM kill_killers AS `killer`
											LEFT JOIN character_metadata AS `char` ON killer.killerCharacterId = char.characterId
											LEFT JOIN corporation_metadata AS `corp` ON killer.killerCorporationId = corp.corporationId
											LEFT JOIN alliance_metadata AS `alliance` ON killer.killerAllianceId = alliance.allianceId
											WHERE killer.killId = ?
											ORDER BY killer.damageDone desc');
		$getAttackersQuery->bindValue(1,$killId,PDO::PARAM_INT);
		$getAttackersQuery->execute();
		$i = 0;
		while ($attackerData = $getAttackersQuery->fetchObject())
		{
			$i++;
			if ($attackerData->finalBlow)
				echo "FINAL BLOW: ";
			$isPlayer = ($attackerData->killerCharacterId != 0);
			if ($isPlayer)
			{
				echo "Attacker #",$i,": (Player)\n";
				echo $attackerData->characterName," of [",$attackerData->corporationName,"] &lt;",$attackerData->allianceName,"&gt;\n";
			}
			else
			{
				echo "Attacker #",$i,": (NPC)\n";
				echo $attackerData->killerCharacterName," of [",$attackerData->corporationName,"] &lt;",$attackerData->allianceName,"&gt;\n";
			}
			echo "using ",getCachedItemName($attackerData->killerWeaponTypeId)," on a ",getCachedItemname($attackerData->killerShipTypeId),"\n";
			echo "Damage: ",$attackerData->damageDone," (",$attackerData->damageFractional,"%)\n\n";
		}
	}
	catch (PDOException $e)
	{
		doError('Database error fetching killmail attackers: '.$e->getMessage(),500);
		die();
	}
?>
Information about <?=$CRESTPullCount?> items taken from CREST.
</pre></body></html><?php error: require(__DIR__.'/../render/doRender.inc.php'); ?>