<?php
	define('OUR_ALLIANCE_ID',937872513);
	require_once(__DIR__.'/database.inc.php');
	require_once(__DIR__.'/locate.inc.php');
	require_once(__DIR__.'/iskvalue.inc.php');
	require_once(__DIR__.'/slotinfo.inc.php');
	require_once(__DIR__.'/cache.inc.php');
  require_once(__DIR__.'/esi.inc.php');
	/* DB needs the following values for a kill:
		table `kill_metadata`:
			- killId (XML row attribute killID)
			- victimCharacterId (XML victim attribute characterID)
			- victimCorporationId (XML victim attribute corporationID)
			- victimAllianceId (XML victim attribute allianceID)
			- shipTypeId (XML victim attribute shipTypeID)
			- solarSystemId (XML row attribute solarSystemID)
			- killTime (XML row attribute killTime)
			- damageTaken (victim row attribute damageTaken)
			- closestLocationId (parsed from XML victim attributes x/y/z and SDE)
			- closestLocationDistance (as above)
			- valueHull, valueFitted, valueDropped, valueDestroyed, valueTotal (calculated while processing fittings)
		table `kill_killers`:
			- killId
			- killerCharacterId (XML attacker row attribute characterID)
			- killerCharacterName (XML attacker row attribute characterName - only populated if characterID = 0, for NPC names)
			- killerShipTypeId (XML attacker row attribute shipTypeID)
			- killerWeaponTypeId (XML attacker row attribute weaponTypeID)
			- killerCorporationId (XML attacker row attribute corporationID)
			- killerAllianceId (XML attacker row attribute allianceID)
			- damageDone (XML attacker row attribute damageDone)
			- damageFractional (calculated as damageDone/damageTaken)
			- finalBlow (XML attacker row attribute finalBlow)
		table `kill_fittings`:
			- killId
			- slotId (mapped from XML item row attribute flag)
			- typeId (XML item row attribute typeID)
			- quantity (XML item row attribute qtyDropped/qtyDestroyed, split possibly)
			- dropped (XML item row attribute qtyDropped/qtyDestroyed, split possibly)
			- value (fetched using typeId)
	*/
  define('MODE_ZKILLBOARD',3);
	function recurseItems($items,$parent,$killId,&$index,$mode,&$valueDropped,&$valueDestroyed,&$valueFitted,&$valueTotal)
	{
		$db = killfeedDB();
		foreach ($items as $item)
		{
      switch ($mode)
      {
        case MODE_ZKILLBOARD:
          $typeId = $item->item_type_id;
          $slotFlag = $item->flag;
          $quantityDropped = isset($item->quantity_dropped) ? $item->quantity_dropped : 0;
          $quantityDestroyed = isset($item->quantity_destroyed) ? $item->quantity_destroyed : 0;
          if (isset($item->items))
          {
            $hasChildren = true;
            $children = $item->items;
          }
          else
          {
            $hasChildren = false;
            $children = null;
          }
          $isBPC = ($item->singleton == 2);
          break;
        default:
          return;
      }
      
			$slotId = convertCCPFlagToSlot($slotFlag);
			$isFitted = !$parent && isSlotFittingSlot($slotId);
			if ($slotFlag && $slotId == UNKNOWNSLOT)
				doError(sprintf('Found a \'%s\' (%d) in unknown flag %d',getCachedItemName($typeId),$typeId,$slotFlag), 200);
			$isModule = $isFitted && isCachedItemModule($typeId);
			if ($isModule === null)
				throw new RuntimeException("Failed to get CREST module info for $typeId.");
			if ($isBPC)
				$itemValueSingle = 0;
			else
				$itemValueSingle = getValueForType($typeId);
			
			// insert stacks into DB (split stack in case of partial destruction - note that currently the game doesn't use this)
			// then add the value to all respective counts
			if ($quantityDropped > 0)
			{
				$thisIndex = ++$index;
				$itemValueDropped = $quantityDropped * $itemValueSingle;
				if ($parent)
				{
					static $queryInsertDroppedItemChild = null;
					if (!$queryInsertDroppedItemChild)
						$queryInsertDroppedItemChild = prepareQuery($db,'INSERT INTO `kill_fittings` (`killId`,`index`,`hasChildren`,`parent`,`slotId`,`isModule`,`typeId`,`quantity`,`dropped`,`value`) VALUES (?,?,?,?,?,?,?,?,1,?);');
					$queryInsertDroppedItemChild->bindValue(1,$killId,PDO::PARAM_INT);
					$queryInsertDroppedItemChild->bindValue(2,$thisIndex,PDO::PARAM_INT);
					$queryInsertDroppedItemChild->bindValue(3,$hasChildren,PDO::PARAM_BOOL);
					$queryInsertDroppedItemChild->bindValue(4,$parent,PDO::PARAM_INT);
					$queryInsertDroppedItemChild->bindValue(5,$slotId,PDO::PARAM_INT);
					$queryInsertDroppedItemChild->bindValue(6,$isModule,PDO::PARAM_BOOL);
					$queryInsertDroppedItemChild->bindValue(7,$typeId,PDO::PARAM_INT);
					$queryInsertDroppedItemChild->bindValue(8,$quantityDropped,PDO::PARAM_INT);
					$queryInsertDroppedItemChild->bindValue(9,$itemValueDropped,PDO::PARAM_STR);
					if (!$queryInsertDroppedItemChild->execute())
						throw new RuntimeException('DB error inserting child item: '.$queryInsertDroppedItemChild->errorInfo()[2]);
				}
				else
				{
					static $queryInsertDroppedItem = null;
					if (!$queryInsertDroppedItem)
						$queryInsertDroppedItem = prepareQuery($db,'INSERT INTO `kill_fittings` (`killId`,`index`,`hasChildren`,`slotId`,`isModule`,`typeId`,`quantity`,`dropped`,`value`) VALUES (?,?,?,?,?,?,?,1,?);');
					$queryInsertDroppedItem->bindValue(1,$killId,PDO::PARAM_INT);
					$queryInsertDroppedItem->bindValue(2,$thisIndex,PDO::PARAM_INT);
					$queryInsertDroppedItem->bindValue(3,$hasChildren,PDO::PARAM_BOOL);
					$queryInsertDroppedItem->bindValue(4,$slotId,PDO::PARAM_INT);
					$queryInsertDroppedItem->bindValue(5,$isModule,PDO::PARAM_BOOL);
					$queryInsertDroppedItem->bindValue(6,$typeId,PDO::PARAM_INT);
					$queryInsertDroppedItem->bindValue(7,$quantityDropped,PDO::PARAM_INT);
					$queryInsertDroppedItem->bindValue(8,$itemValueDropped,PDO::PARAM_STR);
					if (!$queryInsertDroppedItem->execute())
						throw new RuntimeException('DB error inserting item: '.$queryInsertDroppedItem->errorInfo()[2]);
				}
				$valueDropped += $itemValueDropped;
				if ($isFitted)
					$valueFitted += $itemValueDropped;
				$valueTotal += $itemValueDropped;
			}
			if ($quantityDestroyed > 0)
			{
				$thisIndex = ++$index;
				$itemValueDestroyed = $quantityDestroyed * $itemValueSingle;
				if ($parent)
				{
					static $queryInsertDestroyedItemChild = null;
					if (!$queryInsertDestroyedItemChild)
						$queryInsertDestroyedItemChild = prepareQuery($db,'INSERT INTO `kill_fittings` (`killId`,`index`,`hasChildren`,`parent`,`slotId`,`isModule`,`typeId`,`quantity`,`dropped`,`value`) VALUES (?,?,?,?,?,?,?,?,0,?);');
					$queryInsertDestroyedItemChild->bindValue(1,$killId,PDO::PARAM_INT);
					$queryInsertDestroyedItemChild->bindValue(2,$thisIndex,PDO::PARAM_INT);
					$queryInsertDestroyedItemChild->bindValue(3,$hasChildren,PDO::PARAM_BOOL);
					$queryInsertDestroyedItemChild->bindValue(4,$parent,PDO::PARAM_INT);
					$queryInsertDestroyedItemChild->bindValue(5,$slotId,PDO::PARAM_INT);
					$queryInsertDestroyedItemChild->bindValue(6,$isModule,PDO::PARAM_BOOL);
					$queryInsertDestroyedItemChild->bindValue(7,$typeId,PDO::PARAM_INT);
					$queryInsertDestroyedItemChild->bindValue(8,$quantityDestroyed,PDO::PARAM_INT);
					$queryInsertDestroyedItemChild->bindValue(9,$itemValueDestroyed,PDO::PARAM_STR);
				}
				else
				{
					static $queryInsertDestroyedItem = null;
					if (!$queryInsertDestroyedItem)
						$queryInsertDestroyedItem = prepareQuery($db,'INSERT INTO `kill_fittings` (`killId`,`index`,`hasChildren`,`slotId`,`isModule`,`typeId`,`quantity`,`dropped`,`value`) VALUES (?,?,?,?,?,?,?,0,?);');
					$queryInsertDestroyedItem->bindValue(1,$killId,PDO::PARAM_INT);
					$queryInsertDestroyedItem->bindValue(2,$thisIndex,PDO::PARAM_INT);
					$queryInsertDestroyedItem->bindValue(3,$hasChildren,PDO::PARAM_BOOL);
					$queryInsertDestroyedItem->bindValue(4,$slotId,PDO::PARAM_INT);
					$queryInsertDestroyedItem->bindValue(5,$isModule,PDO::PARAM_BOOL);
					$queryInsertDestroyedItem->bindValue(6,$typeId,PDO::PARAM_INT);
					$queryInsertDestroyedItem->bindValue(7,$quantityDestroyed,PDO::PARAM_INT);
					$queryInsertDestroyedItem->bindValue(8,$itemValueDestroyed,PDO::PARAM_STR);
					$queryInsertDestroyedItem->execute();
				}
				$valueDestroyed += $itemValueDestroyed;
				if ($isFitted)
					$valueFitted += $itemValueDestroyed;
				$valueTotal += $itemValueDestroyed;
			}
			
			if($hasChildren)
				recurseItems($children,$thisIndex,$killId,$index,$mode,$valueDropped,$valueDestroyed,$valueFitted,$valueTotal);
		}
	}
	function importKillmail($kill,$mode,&$metadataCharacter = null,&$metadataCorporation = null,&$metadataAlliance = null)
	{
		$db = killfeedDB();
    switch ($mode)
    {
      case MODE_ZKILLBOARD:
        $killId = $kill->killmail_id;
        break;
      default:
        return;
    }
		
		/* Step 0: Make sure this kill isn't a duplicate, skip otherwise. */
		doStatus("Processing ",$killId,"...");
		try
		{
			static $querySelectKillMetadata = null;
			if (!$querySelectKillMetadata)
				$querySelectKillMetadata = prepareQuery($db,'SELECT `id` FROM `kill_metadata` WHERE `id`=?');
			$querySelectKillMetadata->bindValue(1,$killId,PDO::PARAM_INT);
			$querySelectKillMetadata->execute();
			if ($querySelectKillMetadata->rowCount())
			{
				doStatus("Duplicate.\n");
				return $killId;
			}
		}
		catch (PDOException $e)
		{
			doError('Database error in duplicate check: '.$e->getMessage(),500);
			return 0;
		}
		
		// Actually insert this mail
		$db->beginTransaction();
		try
		{
			/* Step 1: Get kill metadata, determine hull price and closest celestial. */
      switch ($mode)
      {
        case MODE_ZKILLBOARD:
          $victim = $kill->victim;
          if (isset($victim->character_id))
            $victimCharacterId = $victim->character_id;
          else
            $victimCharacterId = 0;
          
          if (isset($victim->corporation_id))
            $victimCorporationId = $victim->corporation_id;
          else
            $victimCorporationId = 0;
          
          if (isset($victim->alliance_id))
            $victimAllianceId = $victim->alliance_id;
          else
            $victimAllianceId = 0;
          
          $shipTypeId = $victim->ship_type_id;
          $solarSystemId = $kill->solar_system_id;
          $killTime = $kill->killmail_time;
          $damageTaken = $victim->damage_taken;
          $numKillers = isset($kill->attackers) ? sizeof($kill->attackers) : 0;
          
          if (isset($victim->position))
          {
            $killX = $victim->position->x;
            $killY = $victim->position->y;
            $killZ = $victim->position->z;
          }
          else
            $killX = $killY = $killZ = 0;
          break;
        default:
          return;
      }
			
			$closestCelestial = $closestCelestialDistance = 0;
			findClosestCelestial($solarSystemId, $killX, $killY, $killZ, $closestCelestial, $closestCelestialDistance);
			
			$valueTotal = $valueDestroyed = $valueHull = getValueForType($shipTypeId);
			$valueDropped = $valueFitted = 0;
			
			doStatus("Done (from $killTime)\n");
			
			/* Step 2: iterate over all items on the killmail, add up the values, and insert them into DB */
			$index = 0;
      switch ($mode)
      {
        case MODE_ZKILLBOARD:
          recurseItems($kill->victim->items, 0, $killId, $index, $mode, $valueDropped, $valueDestroyed, $valueFitted, $valueTotal);
          break;
        default:
          return;
      }
			
			/* Step 3: iterate over all killers involved, track their damage and insert them into DB */
			$numPlayerKillers = 0;
			$playerDamageDone = array();
			// save killers' corp/alliance membership to be used in effective value further down, as well as for metadata updating
			$corporationMemberCache = array();   // char -> corporation
			$allianceMemberCache = array();      // char -> alliance
			$corporationAllianceCache = array(); // corp -> alliance
			$totalPlayerDamageDone = 0; // this is used to calculate effective value destroyed for stats
			$killerShipTypeCount = array();
			foreach ((($mode == MODE_ZKILLBOARD) ? $kill->attackers : null) as $killer)
			{
        switch ($mode)
        {
          case MODE_ZKILLBOARD:
            if (isset($killer->character_id) && $killer->character_id)
              $killerCharacterId = $killer->character_id;
            else
              $killerCharacterId = 0;
              
            if (isset($killer->ship_type_id))
              $killerShipTypeId = $killer->ship_type_id;
            else
              $killerShipTypeId = 0;
            
            if (isset($killer->weapon_type_id) && $killer->weapon_type_id)
              $killerWeaponTypeId = $killer->weapon_type_id;
            else
              $killerWeaponTypeId = $killerShipTypeId;
            
            if (isset($killer->corporation_id) && $killer->corporation_id)
              $killerCorporationId = $killer->corporation_id;
            else
              $killerCorporationId = 0;

            if (isset($killer->alliance_id) && $killer->alliance_id)
              $killerAllianceId = $killer->alliance_id;
            else
              $killerAllianceId = 0;

            $damageDone = $killer->damage_done;
            $finalBlow = $killer->final_blow;
            break;
          default:
            return;
        }

				$killerIsNPC = ($killerCharacterId == 0);
				if (!$killerIsNPC)
				{
					$numPlayerKillers++;
					$playerDamageDone[$killerCharacterId] = $damageDone;
					$totalPlayerDamageDone += $damageDone;
					$corporationMemberCache[$killerCharacterId] = $killerCorporationId;
					$allianceMemberCache[$killerCharacterId] = $killerAllianceId;
					$corporationAllianceCache[$killerCorporationId] = $killerAllianceId;
				}
				if ($damageDone)
					$damageFractional = $damageDone*100/$damageTaken;
				else
					$damageFractional = 0;
				
				if ($killerIsNPC)
				{
					static $queryInsertNPCKiller = null;
					if (!$queryInsertNPCKiller)
						$queryInsertNPCKiller = prepareQuery($db,'INSERT INTO `kill_killers` (`killId`,`killerCharacterId`,`killerCharacterName`,`killerShipTypeId`,`killerWeaponTypeId`,`killerCorporationId`,`killerAllianceId`,`damageDone`,`damageFractional`,`finalBlow`) VALUES (?,0,?,?,?,?,?,?,?,?);');
					$queryInsertNPCKiller->bindValue(1,$killId,PDO::PARAM_INT);
					$queryInsertNPCKiller->bindValue(2,getCachedItemName($killerShipTypeId),PDO::PARAM_STR);
					$queryInsertNPCKiller->bindValue(3,$killerShipTypeId,PDO::PARAM_INT);
					$queryInsertNPCKiller->bindValue(4,$killerWeaponTypeId,PDO::PARAM_INT);
					$queryInsertNPCKiller->bindValue(5,$killerCorporationId,PDO::PARAM_INT);
					$queryInsertNPCKiller->bindValue(6,$killerAllianceId,PDO::PARAM_INT);
					$queryInsertNPCKiller->bindValue(7,$damageDone,PDO::PARAM_INT);
					$queryInsertNPCKiller->bindValue(8,$damageFractional,PDO::PARAM_STR);
					$queryInsertNPCKiller->bindValue(9,$finalBlow,PDO::PARAM_BOOL);
					$queryInsertNPCKiller->execute();
				}
				else
				{
					static $queryInsertPlayerKiller = null;
					if (!$queryInsertPlayerKiller)
						$queryInsertPlayerKiller = prepareQuery($db,'INSERT INTO `kill_killers` (`killId`,`killerCharacterId`,`killerShipTypeId`,`killerWeaponTypeId`,`killerCorporationId`,`killerAllianceId`,`damageDone`,`damageFractional`,`finalBlow`) VALUES (?,?,?,?,?,?,?,?,?);');
					$queryInsertPlayerKiller->bindValue(1,$killId,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(2,$killerCharacterId,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(3,$killerShipTypeId,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(4,$killerWeaponTypeId,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(5,$killerCorporationId,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(6,$killerAllianceId,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(7,$damageDone,PDO::PARAM_INT);
					$queryInsertPlayerKiller->bindValue(8,$damageFractional,PDO::PARAM_STR);
					$queryInsertPlayerKiller->bindValue(9,$finalBlow,PDO::PARAM_BOOL);
					$queryInsertPlayerKiller->execute();
					if (null === $metadataCharacter)
					{ // do this in single kill inserts
						static $queryInsertCharacterMetadataKiller = null;
						if (!$queryInsertCharacterMetadataKiller)
							$queryInsertCharacterMetadataKiller = prepareQuery($db,'INSERT INTO `character_metadata` (`characterId`,`characterName`,`corporationId`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageKillValue`,`averageFriendCount`,`averageLossValue`,`averageEnemyCount`) VALUES (?,"",?,?,1,0,?,0,0,0,0,0,0)
																					ON DUPLICATE KEY UPDATE `corporationId`=VALUES(`corporationId`), `allianceId`=VALUES(`allianceId`), `killCount`=`killCount`+1, `killValue`=`killValue`+VALUES(`killValue`);');
						$queryInsertCharacterMetadataKiller->bindValue(1,$killerCharacterId,PDO::PARAM_INT);
						$queryInsertCharacterMetadataKiller->bindValue(2,$killerCorporationId,PDO::PARAM_INT);
						$queryInsertCharacterMetadataKiller->bindValue(3,$killerAllianceId,PDO::PARAM_INT);
						$queryInsertCharacterMetadataKiller->bindValue(4,$valueTotal,PDO::PARAM_STR);
						$queryInsertCharacterMetadataKiller->execute();
            if ($queryInsertCharacterMetadataKiller->rowCount() == 1)
            { // new character
              static $queryUpdateCharacterNameKiller = null;
              if (!$queryUpdateCharacterNameKiller)
                $queryUpdateCharacterNameKiller = prepareQuery($db,'UPDATE `character_metadata` SET `characterName`=? WHERE `characterId`=?;');
              $queryUpdateCharacterNameKiller->bindValue(1,getCharacterName($killerCharacterId),PDO::PARAM_STR);
              $queryUpdateCharacterNameKiller->bindValue(2,$killerCharacterId,PDO::PARAM_INT);
              $queryUpdateCharacterNameKiller->execute();
            }
					}
					else
					{ // otherwise, push it onto the bulk array to be handled later (performance improvement)
						if (isset($metadataCharacter[$killerCharacterId]))
						{
							++$metadataCharacter[$killerCharacterId]->killCount;
							$metadataCharacter[$killerCharacterId]->killValue += $valueTotal;
						}
						else
						{
							$metadataCharacter[$killerCharacterId] = new DBEntityMetadata();
							$metadataCharacter[$killerCharacterId]->characterId = $killerCharacterId;
							$metadataCharacter[$killerCharacterId]->corporationId = $killerCorporationId;
							$metadataCharacter[$killerCharacterId]->allianceId = $killerAllianceId;
							$metadataCharacter[$killerCharacterId]->killCount = 1;
							$metadataCharacter[$killerCharacterId]->killValue = $valueTotal;
							$metadataCharacter[$killerCharacterId]->effectiveKillValue = 0;
							$metadataCharacter[$killerCharacterId]->friendCount = 0;
							$metadataCharacter[$killerCharacterId]->lossCount = 0;
							$metadataCharacter[$killerCharacterId]->lossValue = 0;
							$metadataCharacter[$killerCharacterId]->enemyCount = 0;
						}
					}
				}
				
				if ($killerShipTypeId)
				{
					if (isset($killerShipTypeCount[$killerShipTypeId]))
						++$killerShipTypeCount[$killerShipTypeId];
					else
						$killerShipTypeCount[$killerShipTypeId] = 1;
				}
			}
			$mostCommonKillerShip = $secondMostCommonKillerShip = 0;
			$mostCommonKillerShipCount = $secondMostCommonKillerShipCount = 0;
			foreach ($killerShipTypeCount as $killerShipTypeId => &$killerShipTypeCount)
			{
				if ($killerShipTypeCount > $mostCommonKillerShipCount)
				{
					$secondMostCommonKillerShip = $mostCommonKillerShip;
					$secondMostCommonKillerShipCount = $mostCommonKillerShipCount;
					$mostCommonKillerShip = $killerShipTypeId;
					$mostCommonKillerShipCount = $killerShipTypeCount;
				}
				else if ($killerShipTypeCount > $secondMostCommonKillerShipCount)
				{
					$secondMostCommonKillerShip = $killerShipTypeId;
					$secondMostCommonKillerShipCount = $killerShipTypeCount;
				}
			}
			
			
			/* Step 4: now that iteration over items/killers is complete, insert kill metadata into DB */
			if ($closestCelestial)
			{
				static $queryInsertKillMetadata = null;
				if (!$queryInsertKillMetadata)
					$queryInsertKillMetadata = prepareQuery($db,'INSERT INTO `kill_metadata` (`id`,`victimCharacterId`,`victimCorporationId`,`victimAllianceId`,`shipTypeId`,`solarSystemId`,`killTime`,`closestLocationId`,`closestLocationDistance`,`damageTaken`,`valueHull`,`valueFitted`,`valueDropped`,`valueDestroyed`,`valueTotal`,`numKillers`,`mostCommonKillerShip`,`secondMostCommonKillerShip`,`points`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0);');
				$queryInsertKillMetadata->bindValue( 1,$killId,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 2,$victimCharacterId,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 3,$victimCorporationId,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 4,$victimAllianceId,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 5,$shipTypeId,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 6,$solarSystemId,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 7,$killTime,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue( 8,$closestCelestial,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue( 9,$closestCelestialDistance,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue(10,$damageTaken,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue(11,$valueHull,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue(12,$valueFitted,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue(13,$valueDropped,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue(14,$valueDestroyed,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue(15,$valueTotal,PDO::PARAM_STR);
				$queryInsertKillMetadata->bindValue(16,$numKillers,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue(17,$mostCommonKillerShip,PDO::PARAM_INT);
				$queryInsertKillMetadata->bindValue(18,$secondMostCommonKillerShip,PDO::PARAM_INT);
				if (!$queryInsertKillMetadata->execute())
					throw new RuntimeException('DB error inserting kill meta: '.$queryInsertKillMetadata->errorInfo()[2]);
			}
			else
			{
				static $queryInsertKillMetadataNoLoc = null;
				if (!$queryInsertKillMetadataNoLoc)
					$queryInsertKillMetadataNoLoc = prepareQuery($db,'INSERT INTO `kill_metadata` (`id`,`victimCharacterId`,`victimCorporationId`,`victimAllianceId`,`shipTypeId`,`solarSystemId`,`killTime`,`damageTaken`,`valueHull`,`valueFitted`,`valueDropped`,`valueDestroyed`,`valueTotal`,`numKillers`,`mostCommonKillerShip`,`secondMostCommonKillerShip`,`points`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0);');
				$queryInsertKillMetadataNoLoc->bindValue( 1,$killId,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 2,$victimCharacterId,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 3,$victimCorporationId,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 4,$victimAllianceId,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 5,$shipTypeId,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 6,$solarSystemId,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 7,$killTime,PDO::PARAM_STR);
				$queryInsertKillMetadataNoLoc->bindValue( 8,$damageTaken,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue( 9,$valueHull,PDO::PARAM_STR);
				$queryInsertKillMetadataNoLoc->bindValue(10,$valueFitted,PDO::PARAM_STR);
				$queryInsertKillMetadataNoLoc->bindValue(11,$valueDropped,PDO::PARAM_STR);
				$queryInsertKillMetadataNoLoc->bindValue(12,$valueDestroyed,PDO::PARAM_STR);
				$queryInsertKillMetadataNoLoc->bindValue(13,$valueTotal,PDO::PARAM_STR);
				$queryInsertKillMetadataNoLoc->bindValue(14,$numKillers,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue(15,$mostCommonKillerShip,PDO::PARAM_INT);
				$queryInsertKillMetadataNoLoc->bindValue(16,$secondMostCommonKillerShip,PDO::PARAM_INT);
				if (!$queryInsertKillMetadataNoLoc->execute())
					throw new RuntimeException('DB error inserting kill meta: '.$queryInsertKillMetadataNoLoc->errorInfo()[2]);
			}
			
			/* Step 5: actual kill data is now there - update statistics */
			/* Step 5a: victim statistics (player, corporation, alliance) */
			if ($victimCharacterId)
			{
				if (null === $metadataCharacter)
				{
					static $queryUpdateCharacterMetadataVictim = null;
					if (!$queryUpdateCharacterMetadataVictim)
						$queryUpdateCharacterMetadataVictim = prepareQuery($db,'INSERT INTO `character_metadata` (`characterId`,`characterName`,`corporationId`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageKillValue`,`averageFriendCount`,`averageLossValue`,`averageEnemyCount`) VALUES (?,"",?,?,0,1,0,0,?,0,0,?,?)
																ON DUPLICATE KEY UPDATE `corporationId`=VALUES(`corporationId`), `allianceId`=VALUES(`allianceId`), `lossCount`=`lossCount`+1, `lossValue`=`lossValue`+VALUES(`lossValue`), `averageLossValue`=((`lossValue`+VALUES(`lossValue`))/(`lossCount`+1)), `averageEnemyCount`=((`averageEnemyCount`*`lossCount`)+VALUES(`averageEnemyCount`))/(`lossCount`+1);');
					$queryUpdateCharacterMetadataVictim->bindValue(1,$victimCharacterId,PDO::PARAM_INT);
					$queryUpdateCharacterMetadataVictim->bindValue(2,$victimCorporationId,PDO::PARAM_INT);
					$queryUpdateCharacterMetadataVictim->bindValue(3,$victimAllianceId,PDO::PARAM_INT);
					$queryUpdateCharacterMetadataVictim->bindValue(4,$valueTotal,PDO::PARAM_STR);
					$queryUpdateCharacterMetadataVictim->bindValue(5,$valueTotal,PDO::PARAM_STR);
					$queryUpdateCharacterMetadataVictim->bindValue(6,$numPlayerKillers,PDO::PARAM_INT);
					if(!$queryUpdateCharacterMetadataVictim->execute())
						throw new RuntimeException('DB error inserting victim char meta: '.$queryUpdateCharacterMetadataVictim->errorInfo()[2]);
          if ($queryUpdateCharacterMetadataVictim->rowCount() == 1)
          {
            // new character
            static $queryUpdateCharacterNameVictim = null;
            if (!$queryUpdateCharacterNameVictim)
              $queryUpdateCharacterNameVictim = prepareQuery($db,'UPDATE `character_metadata` SET `characterName`=? WHERE `characterId`=?;');
            $queryUpdateCharacterNameVictim->bindValue(1,getCharacterName($victimCharacterId),PDO::PARAM_STR);
            $queryUpdateCharacterNameVictim->bindValue(2,$victimCharacterId,PDO::PARAM_INT);
            $queryUpdateCharacterNameVictim->execute();
          }
				}
				else
				{
					if (isset($metadataCharacter[$victimCharacterId]))
					{
						++$metadataCharacter[$victimCharacterId]->lossCount;
						$metadataCharacter[$victimCharacterId]->lossValue += $valueTotal;
						$metadataCharacter[$victimCharacterId]->enemyCount += $numPlayerKillers;
					}
					else
					{
						$metadataCharacter[$victimCharacterId] = new DBEntityMetadata();
						$metadataCharacter[$victimCharacterId]->characterId = $victimCharacterId;
						$metadataCharacter[$victimCharacterId]->corporationId = $victimCorporationId;
						$metadataCharacter[$victimCharacterId]->allianceId = $victimAllianceId;
						$metadataCharacter[$victimCharacterId]->killCount = 0;
						$metadataCharacter[$victimCharacterId]->killValue = 0;
						$metadataCharacter[$victimCharacterId]->effectiveKillValue = 0;
						$metadataCharacter[$victimCharacterId]->friendCount = 0;
						$metadataCharacter[$victimCharacterId]->lossCount = 1;
						$metadataCharacter[$victimCharacterId]->lossValue = $valueTotal;
						$metadataCharacter[$victimCharacterId]->enemyCount = $numPlayerKillers;
					}
				}
			}
			
			if ($victimCorporationId)
			{
				if (null === $metadataCorporation)
				{
					static $queryUpdateCorporationMetadataVictim = null;
					if (!$queryUpdateCorporationMetadataVictim)
						$queryUpdateCorporationMetadataVictim = prepareQuery($db,'INSERT INTO `corporation_metadata` (`corporationId`,`corporationName`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (?,?,?,0,1,0,0,?,0,0,?,?)
															ON DUPLICATE KEY UPDATE `allianceId`=VALUES(`allianceId`), `lossCount`=`lossCount`+1, `lossValue`=`lossValue`+VALUES(`lossValue`), `averageEnemyCount`=((`averageEnemyCount`*`lossCount`)+VALUES(`averageEnemyCount`))/(`lossCount`+1), `averageLossValue`=(`lossValue`+VALUES(`averageLossValue`))/(`lossCount`+1);');
					$queryUpdateCorporationMetadataVictim->bindValue(1,$victimCorporationId,PDO::PARAM_INT);
					$queryUpdateCorporationMetadataVictim->bindValue(2,$victimCorporationName,PDO::PARAM_STR);
					$queryUpdateCorporationMetadataVictim->bindValue(3,$victimAllianceId,PDO::PARAM_INT);
					$queryUpdateCorporationMetadataVictim->bindValue(4,$valueTotal,PDO::PARAM_STR);
					$queryUpdateCorporationMetadataVictim->bindValue(5,$numPlayerKillers,PDO::PARAM_INT);
					$queryUpdateCorporationMetadataVictim->bindValue(6,$valueTotal,PDO::PARAM_STR);
					$queryUpdateCorporationMetadataVictim->execute();
          if ($queryUpdateCorporationMetadataVictim->rowCount() == 1)
          { // new corporation
              static $queryUpdateCorporationNameVictim = null;
              if (!$queryUpdateCorporationNameVictim)
                $queryUpdateCorporationNameVictim = prepareQuery($db,'UPDATE `corporation_metadata` SET `corporationName`=? WHERE `corporationId`=?;');
              $queryUpdateCorporationNameVictim->bindValue(1,getCorporationName($victimCorporationId),PDO::PARAM_STR);
              $queryUpdateCorporationNameVictim->bindValue(2,$victimCorporationId,PDO::PARAM_INT);
              $queryUpdateCorporationNameVictim->execute();
          }
				}
				else
				{
					if (isset($metadataCorporation[$victimCorporationId]))
					{
						if (!isset($metadataCorporation[$victimCorporationId]->lossCount))
						{
							var_dump($metadataCorporation[$victimCorporationId]);
							die();
						}
						++$metadataCorporation[$victimCorporationId]->lossCount;
						$metadataCorporation[$victimCorporationId]->lossValue += $valueTotal;
						$metadataCorporation[$victimCorporationId]->enemyCount += $numPlayerKillers;
					}
					else
					{
						$metadataCorporation[$victimCorporationId] = new DBEntityMetadata();
						$metadataCorporation[$victimCorporationId]->corporationId = $victimCorporationId;
						$metadataCorporation[$victimCorporationId]->corporationName = getCorporationName($victimCorporationId);
						$metadataCorporation[$victimCorporationId]->allianceId = $victimAllianceId;
						$metadataCorporation[$victimCorporationId]->killCount = 0;
						$metadataCorporation[$victimCorporationId]->killValue = 0;
						$metadataCorporation[$victimCorporationId]->effectiveKillValue = 0;
						$metadataCorporation[$victimCorporationId]->friendCount = 0;
						$metadataCorporation[$victimCorporationId]->lossCount = 1;
						$metadataCorporation[$victimCorporationId]->lossValue = $valueTotal;
						$metadataCorporation[$victimCorporationId]->enemyCount = $numPlayerKillers;
					}
				}
			}
			
			if ($victimAllianceId)
			{
				if (null == $metadataAlliance)
				{
					static $queryUpdateAllianceMetadataVictim = null;
					if (!$queryUpdateAllianceMetadataVictim)
						$queryUpdateAllianceMetadataVictim = prepareQuery($db,'INSERT INTO `alliance_metadata` (`allianceId`,`allianceName`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (?,"",0,1,0,0,?,0,0,?,?)
															ON DUPLICATE KEY UPDATE `lossCount`=`lossCount`+1, `lossValue`=`lossValue`+VALUES(`lossValue`), `averageEnemyCount`=((`averageEnemyCount`*`lossCount`)+VALUES(`averageEnemyCount`))/(`lossCount`+1), `averageLossValue`=(`lossValue`+VALUES(`averageLossValue`))/(`lossCount`+1);');
					$queryUpdateAllianceMetadataVictim->bindValue(1,$victimAllianceId,PDO::PARAM_INT);
					$queryUpdateAllianceMetadataVictim->bindValue(2,$valueTotal,PDO::PARAM_STR);
					$queryUpdateAllianceMetadataVictim->bindValue(3,$numPlayerKillers,PDO::PARAM_INT);
					$queryUpdateAllianceMetadataVictim->bindValue(4,$valueTotal,PDO::PARAM_STR);
					$queryUpdateAllianceMetadataVictim->execute();
          if ($queryUpdateAllianceMetadataVictim->rowCount() == 1)
          { // new alliance
              static $queryUpdateAllianceNameVictim = null;
              if (!$queryUpdateAllianceNameVictim)
                $queryUpdateAllianceNameVictim = prepareQuery($db,'UPDATE `alliance_metadata` SET `allianceName`=? WHERE `allianceId`=?;');
              $queryUpdateAllianceNameVictim->bindValue(1,getAllianceName($victimAllianceId),PDO::PARAM_STR);
              $queryUpdateAllianceNameVictim->bindValue(2,$victimAllianceId,PDO::PARAM_INT);
              $queryUpdateAllianceNameVictim->execute();
          }
				}
				else
				{
					if (isset($metadataAlliance[$victimAllianceId]))
					{
						++$metadataAlliance[$victimAllianceId]->lossCount;
						$metadataAlliance[$victimAllianceId]->lossValue += $valueTotal;
						$metadataAlliance[$victimAllianceId]->enemyCount += $numPlayerKillers;
					}
					else
					{
						$metadataAlliance[$victimAllianceId] = new DBEntityMetadata();
						$metadataAlliance[$victimAllianceId]->allianceId = $victimAllianceId;
						$metadataAlliance[$victimAllianceId]->allianceName = getAllianceName($victimAllianceId);
						$metadataAlliance[$victimAllianceId]->killCount = 0;
						$metadataAlliance[$victimAllianceId]->killValue = 0;
						$metadataAlliance[$victimAllianceId]->effectiveKillValue = 0;
						$metadataAlliance[$victimAllianceId]->friendCount = 0;
						$metadataAlliance[$victimAllianceId]->lossCount = 1;
						$metadataAlliance[$victimAllianceId]->lossValue = $valueTotal;
						$metadataAlliance[$victimAllianceId]->enemyCount = $numPlayerKillers;
					}
				}
			}
			
			/* Step 5b: killer statistics (player) */
			$corporationEffectiveValue = array();
			$allianceEffectiveValue = array();
			foreach ($playerDamageDone as $killerCharacterId => &$damageDone)
			{
				if ($totalPlayerDamageDone > 0)
					$effectiveKillValue = $valueTotal * ($damageDone/$totalPlayerDamageDone);
				else
					$effectiveKillValue = $valueTotal / $numPlayerKillers;
				
				if ($killerCorporationId = $corporationMemberCache[$killerCharacterId])
				{
					if (isset($corporationEffectiveValue[$killerCorporationId]))
						$corporationEffectiveValue[$killerCorporationId] += $effectiveKillValue;
					else
						$corporationEffectiveValue[$killerCorporationId] = $effectiveKillValue;
				}
				if ($killerAllianceId = $allianceMemberCache[$killerCharacterId])
				{
					if (isset($allianceEffectiveValue[$killerAllianceId]))
						$allianceEffectiveValue[$killerAllianceId] += $effectiveKillValue;
					else
						$allianceEffectiveValue[$killerAllianceId] = $effectiveKillValue;
				}
				
				if (null === $metadataCharacter)
				{
					static $queryUpdateCharacterMetadataKiller = null;
					if (!$queryUpdateCharacterMetadataKiller)
						$queryUpdateCharacterMetadataKiller = prepareQuery($db,'UPDATE `character_metadata` SET `effectiveKillValue`=`effectiveKillValue`+?, `averageKillValue`=`killValue`/`killCount`, `averageFriendCount`=((`averageFriendCount`*(`killCount`-1))+?)/`killCount` WHERE `characterId` = ?');
					$queryUpdateCharacterMetadataKiller->bindValue(1,$effectiveKillValue,PDO::PARAM_STR);
					$queryUpdateCharacterMetadataKiller->bindValue(2,$numPlayerKillers,PDO::PARAM_INT);
					$queryUpdateCharacterMetadataKiller->bindValue(3,$killerCharacterId,PDO::PARAM_INT);
					$queryUpdateCharacterMetadataKiller->execute();
				}
				else
				{
					// entry already exists from earlier, we only add stuff that requires info about all killers here
					$metadataCharacter[$killerCharacterId]->effectiveKillValue += $effectiveKillValue;
					$metadataCharacter[$killerCharacterId]->friendCount += $numPlayerKillers;
				}
				
				static $queryAddCharacterAssociationKiller = null;
				if (!$queryAddCharacterAssociationKiller)
					$queryAddCharacterAssociationKiller = prepareQuery($db,'INSERT INTO `character_kill_history` (`characterId`,`killId`) VALUES (?,?);');
				$queryAddCharacterAssociationKiller->bindValue(1,$killerCharacterId,PDO::PARAM_INT);
				$queryAddCharacterAssociationKiller->bindValue(2,$killId,PDO::PARAM_INT);
				$queryAddCharacterAssociationKiller->execute();
			}
			
			/* Step 5c: killer statistics (corporation) */
			foreach ($corporationEffectiveValue as $killerCorporationId => &$effectiveKillValue)
			{
				$killerAllianceId = $corporationAllianceCache[$killerCorporationId];

				if (null === $metadataCorporation)
				{
					static $queryUpdateCorporationMetadataKiller = null;
					if (!$queryUpdateCorporationMetadataKiller)
						$queryUpdateCorporationMetadataKiller = prepareQuery($db,'INSERT INTO `corporation_metadata` (`corporationId`,`corporationName`,`allianceId`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (?,"",?,1,0,?,?,0,?,?,0,0)
																ON DUPLICATE KEY UPDATE `allianceId`=VALUES(`allianceId`), `killCount`=`killCount`+1, `killValue`=`killValue`+VALUES(`killValue`), `effectiveKillValue`=`effectiveKillValue`+VALUES(`effectiveKillValue`), `averageFriendCount`=((`averageFriendCount`*`killCount`)+VALUES(`averageFriendCount`))/(`killCount`+1), `averageKillValue`=(`killValue`+VALUES(`averageKillValue`))/(`killCount`+1);');
					$queryUpdateCorporationMetadataKiller->bindValue(1,$killerCorporationId,PDO::PARAM_INT);
					$queryUpdateCorporationMetadataKiller->bindValue(2,$killerAllianceId,PDO::PARAM_INT);
					$queryUpdateCorporationMetadataKiller->bindValue(3,$valueTotal,PDO::PARAM_STR);
					$queryUpdateCorporationMetadataKiller->bindValue(4,$effectiveKillValue,PDO::PARAM_STR);
					$queryUpdateCorporationMetadataKiller->bindValue(5,$numPlayerKillers,PDO::PARAM_INT);
					$queryUpdateCorporationMetadataKiller->bindValue(6,$valueTotal,PDO::PARAM_STR);
					$queryUpdateCorporationMetadataKiller->execute();
          if ($queryUpdateCorporationMetadataKiller->rowCount() == 1)
          { // new corporation
              static $queryUpdateCorporationNameKiller = null;
              if (!$queryUpdateCorporationNameKiller)
                $queryUpdateCorporationNameKiller = prepareQuery($db,'UPDATE `corporation_metadata` SET `corporationName`=? WHERE `corporationId`=?;');
              $queryUpdateCorporationNameKiller->bindValue(1,getCorporationName($killerCorporationId),PDO::PARAM_STR);
              $queryUpdateCorporationNameKiller->bindValue(2,$killerCorporationId,PDO::PARAM_INT);
              $queryUpdateCorporationNameKiller->execute();
          }
				}
				else
				{
					if (isset($metadataCorporation[$killerCorporationId]))
					{
						++$metadataCorporation[$killerCorporationId]->killCount;
						$metadataCorporation[$killerCorporationId]->killValue += $valueTotal;
						$metadataCorporation[$killerCorporationId]->effectiveKillValue += $effectiveKillValue;
						$metadataCorporation[$killerCorporationId]->friendCount += $numPlayerKillers;
					}
					else
					{
						$metadataCorporation[$killerCorporationId] = new DBEntityMetadata();
						$metadataCorporation[$killerCorporationId]->corporationId = $killerCorporationId;
						$metadataCorporation[$killerCorporationId]->corporationName = getCorporationName($killerCorporationId);
						$metadataCorporation[$killerCorporationId]->allianceId = $killerAllianceId;
						$metadataCorporation[$killerCorporationId]->killCount = 1;
						$metadataCorporation[$killerCorporationId]->killValue = $valueTotal;
						$metadataCorporation[$killerCorporationId]->effectiveKillValue = $effectiveKillValue;
						$metadataCorporation[$killerCorporationId]->friendCount = $numPlayerKillers;
						$metadataCorporation[$killerCorporationId]->lossCount = 0;
						$metadataCorporation[$killerCorporationId]->lossValue = 0;
						$metadataCorporation[$killerCorporationId]->enemyCount = 0;
					}
				}
				
				static $queryAddCorporationAssociationKiller = null;
				if (!$queryAddCorporationAssociationKiller)
					$queryAddCorporationAssociationKiller = prepareQuery($db,'INSERT INTO `corporation_kill_history` (`corporationId`,`killId`) VALUES (?,?);');
				$queryAddCorporationAssociationKiller->bindValue(1,$killerCorporationId,PDO::PARAM_INT);
				$queryAddCorporationAssociationKiller->bindValue(2,$killId,PDO::PARAM_INT);
				$queryAddCorporationAssociationKiller->execute();
			}
				
			/* Step 5d: killer statistics (alliance) */
			foreach ($allianceEffectiveValue as $killerAllianceId => &$effectiveKillValue)
			{
				if ($metadataAlliance === null)
				{
					static $queryUpdateAllianceMetadataKiller = null;
					if (!$queryUpdateAllianceMetadataKiller)
						$queryUpdateAllianceMetadataKiller = prepareQuery($db,'INSERT INTO `alliance_metadata` (`allianceId`,`allianceName`,`killCount`,`lossCount`,`killValue`,`effectiveKillValue`,`lossValue`,`averageFriendCount`,`averageKillValue`,`averageEnemyCount`,`averageLossValue`) VALUES (?,"",1,0,?,?,0,?,?,0,0)
																ON DUPLICATE KEY UPDATE `killCount`=`killCount`+1, `killValue`=`killValue`+VALUES(`killValue`), `effectiveKillValue`=`effectiveKillValue`+VALUES(`effectiveKillValue`), `averageFriendCount`=((`averageFriendCount`*`killCount`)+VALUES(`averageFriendCount`))/(`killCount`+1), `averageKillValue`=(`killValue`+VALUES(`averageKillValue`))/(`killCount`+1);');
					$queryUpdateAllianceMetadataKiller->bindValue(1,$killerAllianceId,PDO::PARAM_INT);
					$queryUpdateAllianceMetadataKiller->bindValue(2,$valueTotal,PDO::PARAM_STR);
					$queryUpdateAllianceMetadataKiller->bindValue(3,$effectiveKillValue,PDO::PARAM_STR);
					$queryUpdateAllianceMetadataKiller->bindValue(4,$numPlayerKillers,PDO::PARAM_INT);
					$queryUpdateAllianceMetadataKiller->bindValue(5,$valueTotal,PDO::PARAM_STR);
					$queryUpdateAllianceMetadataKiller->execute();
          if ($queryUpdateAllianceMetadataKiller->rowCount() == 1)
          { // new alliance
              static $queryUpdateAllianceNameKiller = null;
              if (!$queryUpdateAllianceNameKiller)
                $queryUpdateAllianceNameKiller = prepareQuery($db,'UPDATE `alliance_metadata` SET `allianceName`=? WHERE `allianceId`=?;');
              $queryUpdateAllianceNameKiller->bindValue(1,getAllianceName($killerAllianceId),PDO::PARAM_STR);
              $queryUpdateAllianceNameKiller->bindValue(2,$killerAllianceId,PDO::PARAM_INT);
              $queryUpdateAllianceNameKiller->execute();
          }
				}
				else
				{
					if (isset($metadataAlliance[$killerAllianceId]))
					{
						++$metadataAlliance[$killerAllianceId]->killCount;
						$metadataAlliance[$killerAllianceId]->killValue += $valueTotal;
						$metadataAlliance[$killerAllianceId]->effectiveKillValue += $effectiveKillValue;
						$metadataAlliance[$killerAllianceId]->friendCount += $numPlayerKillers;
					}
					else
					{
						$metadataAlliance[$killerAllianceId] = new DBEntityMetadata();
						$metadataAlliance[$killerAllianceId]->allianceId = $killerAllianceId;
						$metadataAlliance[$killerAllianceId]->allianceName = getAllianceName($killerAllianceId);
						$metadataAlliance[$killerAllianceId]->killCount = 1;
						$metadataAlliance[$killerAllianceId]->killValue = $valueTotal;
						$metadataAlliance[$killerAllianceId]->effectiveKillValue = $effectiveKillValue;
						$metadataAlliance[$killerAllianceId]->friendCount = $numPlayerKillers;
						$metadataAlliance[$killerAllianceId]->lossCount = 0;
						$metadataAlliance[$killerAllianceId]->lossValue = 0;
						$metadataAlliance[$killerAllianceId]->enemyCount = 0;
					}
				}
				
				static $queryAddAllianceAssociationKiller = null;
				if (!$queryAddAllianceAssociationKiller)
					$queryAddAllianceAssociationKiller = prepareQuery($db,'INSERT INTO `alliance_kill_history` (`allianceId`,`killId`) VALUES (?,?);');
				$queryAddAllianceAssociationKiller->bindValue(1,$killerAllianceId,PDO::PARAM_INT);
				$queryAddAllianceAssociationKiller->bindValue(2,$killId,PDO::PARAM_INT);
				$queryAddAllianceAssociationKiller->execute();
			}
			
			/* Step 5e: daily statistics */
			if (
				($victimAllianceId == OUR_ALLIANCE_ID) ||
				isset($allianceEffectiveValue[OUR_ALLIANCE_ID])
			)
			{
				static $queryUpdateDailyStats = null;
				if (!$queryUpdateDailyStats)
					$queryUpdateDailyStats = prepareQuery($db,'INSERT INTO `kill_day_history` (`day`,`totalKillValue`,`topKillId`,`topKillValue`,`topKillShipType`,`firstKillId`,`lastKillId`) VALUES (:day,:effectiveValue,:killid,:value,:shiptypeid,:killid,:killid) ON DUPLICATE KEY UPDATE `totalKillValue` = `totalKillValue`+VALUES(`totalKillValue`), `topKillId` = IF(`topKillValue` < VALUES(`topKillValue`), VALUES(`topKillId`), `topKillId`), `topKillValue` = IF (`topKillId` = VALUES(`topKillId`), VALUES(`topKillValue`), `topKillValue`), `topKillShipType` = IF (`topKillId` = VALUES(`topKillId`), VALUES(`topKillShipType`), `topKillShipType`), `firstKillId`=LEAST(`firstKillId`,VALUES(`firstKillId`)), `lastKillId`=GREATEST(`lastKillId`,VALUES(`lastKillId`));');
				$queryUpdateDailyStats->bindValue(':day',$killTime,PDO::PARAM_STR);
				if ($victimAllianceId == OUR_ALLIANCE_ID)
					$queryUpdateDailyStats->bindValue(':effectiveValue',$valueTotal,PDO::PARAM_STR);
				else
					$queryUpdateDailyStats->bindValue(':effectiveValue',$allianceEffectiveValue[OUR_ALLIANCE_ID],PDO::PARAM_STR);
				$queryUpdateDailyStats->bindValue(':killid',$killId,PDO::PARAM_INT);
				$queryUpdateDailyStats->bindValue(':value',$valueTotal,PDO::PARAM_STR);
				$queryUpdateDailyStats->bindValue(':shiptypeid',$shipTypeId,PDO::PARAM_INT);
				if (!$queryUpdateDailyStats->execute())
					throw new RuntimeException('DB error updating daily stats: '.$queryUpdateDailyStats->errorInfo()[2]);
			}

			/* Step 6: Add associations to the quick lookup tables for victim: character/corporation/alliance/ship/solarsystem/region */
			if ($victimCharacterId && !isset($playerDamageDone[$victimCharacterId]))
			{
				static $queryAddCharacterAssociationVictim = null;
				if (!$queryAddCharacterAssociationVictim)
					$queryAddCharacterAssociationVictim = prepareQuery($db,'INSERT INTO `character_kill_history` (`characterId`,`killId`) VALUES (?,?);');
				$queryAddCharacterAssociationVictim->bindValue(1,$victimCharacterId,PDO::PARAM_INT);
				$queryAddCharacterAssociationVictim->bindValue(2,$killId,PDO::PARAM_INT);
				$queryAddCharacterAssociationVictim->execute();
			}
			if ($victimCorporationId && !isset($corporationEffectiveValue[$victimCorporationId]))
			{
				static $queryAddCorporationAssociationVictim = null;
				if (!$queryAddCorporationAssociationVictim)
					$queryAddCorporationAssociationVictim = prepareQuery($db,'INSERT INTO `corporation_kill_history` (`corporationId`,`killId`) VALUES (?,?);');
				$queryAddCorporationAssociationVictim->bindValue(1,$victimCorporationId,PDO::PARAM_INT);
				$queryAddCorporationAssociationVictim->bindValue(2,$killId,PDO::PARAM_INT);
				$queryAddCorporationAssociationVictim->execute();
			}
			if ($victimAllianceId && !isset($allianceEffectiveValue[$victimAllianceId]))
			{
				static $queryAddAllianceAssociationVictim = null;
				if (!$queryAddAllianceAssociationVictim)
					$queryAddAllianceAssociationVictim = prepareQuery($db,'INSERT INTO `alliance_kill_history` (`allianceId`,`killId`) VALUES (?,?);');
				$queryAddAllianceAssociationVictim->bindValue(1,$victimAllianceId,PDO::PARAM_INT);
				$queryAddAllianceAssociationVictim->bindValue(2,$killId,PDO::PARAM_INT);
				$queryAddAllianceAssociationVictim->execute();
			}
			
			static $queryAddShipAssociation = null;
			if (!$queryAddShipAssociation)
				$queryAddShipAssociation = prepareQuery($db,'INSERT INTO `ship_kill_history` (`shipTypeId`,`killId`) VALUES (?,?);');
			$queryAddShipAssociation->bindValue(1,$shipTypeId,PDO::PARAM_INT);
			$queryAddShipAssociation->bindValue(2,$killId,PDO::PARAM_INT);
			$queryAddShipAssociation->execute();
			
			static $queryAddSolarSystemAssociation = null;
			if (!$queryAddSolarSystemAssociation)
				$queryAddSolarSystemAssociation = prepareQuery($db,'INSERT INTO `solarsystem_kill_history` (`solarSystemId`,`killId`) VALUES (?,?);');
			$queryAddSolarSystemAssociation->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$queryAddSolarSystemAssociation->bindValue(2,$killId,PDO::PARAM_INT);
			$queryAddSolarSystemAssociation->execute();
			
			$regionId = getCachedSolarSystemRegionID($solarSystemId);
			static $queryAddRegionAssociation = null;
			if (!$queryAddRegionAssociation)
				$queryAddRegionAssociation = prepareQuery($db,'INSERT INTO `region_kill_history` (`regionId`,`killId`) VALUES (?,?);');
			$queryAddRegionAssociation->bindValue(1,$regionId,PDO::PARAM_INT);
			$queryAddRegionAssociation->bindValue(2,$killId,PDO::PARAM_INT);
			$queryAddRegionAssociation->execute();
			
			$db->commit();
		}
		catch (RuntimeException $e)
		{
			$db->rollBack();
			doError("Error in committing kill $killId to database:\n".$e->getMessage(),500);
			doStatus("Error in committing kill $killId to database - skipped!\n");
			
			return 0;
		}
		
		return $killId;
	}
?>