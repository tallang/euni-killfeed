<?php
	require(__DIR__.'/../render/setup.inc.php');
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../helpers/http.inc.php');
	require(__DIR__.'/../helpers/cache.inc.php');
	// fetches all items in top-level ship/module/item market groups
	// then parses them into DB appropriately
	// any items that aren't found this way will be parsed on demand
	$crestGroupShip = 4;
	$crestGroupModule = 9;
	$crestGroupAmmo = 11;
	$crestGroupSubsystems = 1112;
	
	try
	{
		$queryInsertItem = prepareQuery(killfeedDB(),'REPLACE INTO `cache_iteminfo` (`typeID`,`typeName`,`isModule`) VALUES (?,?,?);');
		$queryInsertItem->bindParam(1,$typeId,PDO::PARAM_INT);
		$queryInsertItem->bindParam(2,$typeName,PDO::PARAM_STR);
		$queryInsertItem->bindParam(3,$isModule,PDO::PARAM_BOOL);
		$queryDeleteItem = prepareQuery(killfeedDB(),'DELETE FROM `cache_iteminfo` WHERE `typeID` = ?;'); // for giving subsystems proper info - they are caught as "modules"
		$queryDeleteItem->bindParam(1,$typeId,PDO::PARAM_INT);
		
		
		$nextURL = "https://crest-tq.eveonline.com/market/types/?group=https://crest-tq.eveonline.com/market/groups/$crestGroupShip/";
		$nShips = 0;
		doStatus("Beginning to parse ship types. This may take a while...\n");
		while ($nextURL)
		{
			$crestStr = httpRequestWithRetries($nextURL,50);
			if (!$crestStr)
			{
				doError("Failed to contact CREST at URL '$nextURL'.",500);
				return;
			}
			$crestData = json_decode($crestStr);
			unset($crestStr);
			if (!$crestData)
			{
				doError("Failed to parse CREST response for URL '$nextURL'.",500);
				return;
			}
			if (isset($crestData->exceptionType))
			{
				doError('CREST request failed with '.$crestData->exceptionType.': "'.$crestData->message.'".',500);
				return;
			}
			$totalCount = $crestData->totalCount;
			foreach ($crestData->items as $item)
			{
				$typeId = $item->type->id;
				$queryDeleteItem->execute();
				$name = getCachedItemName($item->type->id); // NOTE: This also requests & caches data from CREST!
				if ($name)
				{
					++$nShips;
					doStatus('(',$nShips,'/',$totalCount,') Successfully parsed ship type \'',$name,'\' (',$typeId,").\n");
				}
				else
				{
					--$totalCount;
					doStatus("Failed to parse ship type $typeId from CREST. Skipping.\n");
				}
			}
			
			if (isset($crestData->next))
				$nextURL = $crestData->next->href;
			else
				$nextURL = null;
			unset($crestData);
		}
		doStatus("Finished parsing $nShips ship types.\n");
		
		$nextURL = "https://crest-tq.eveonline.com/market/types/?group=https://crest-tq.eveonline.com/market/groups/$crestGroupModule/";
		$nModules = 0;
		doStatus("Now parsing modules. This should be done fairly quickly...\n");
		while ($nextURL)
		{
			$crestStr = httpRequestWithRetries($nextURL,50);
			if (!$crestStr)
			{
				doError("Failed to contact CREST at URL '$nextURL'.",500);
				return;
			}
			$crestData = json_decode($crestStr);
			unset($crestStr);
			if (!$crestData)
			{
				doError("Failed to parse CREST response for URL '$nextURL'.",500);
				return;
			}
			if (isset($crestData->exceptionType))
			{
				doError('CREST request failed with '.$crestData->exceptionType.': "'.$crestData->message.'".',500);
				return;
			}
			$isModule = true;
			foreach ($crestData->items as $item)
			{
				$typeId = $item->type->id;
				$typeName = $item->type->name;
				$queryInsertItem->execute();
				++$nModules;
				doStatus('(',$nModules,'/',$crestData->totalCount,') Successfully parsed module \'',$typeName,'\' (',$typeId,").\n");
			}
			
			if (isset($crestData->next))
				$nextURL = $crestData->next->href;
			else
				$nextURL = null;
			unset($crestData);
		}
		doStatus("Done. $nModules module types successfully parsed.\n");
		
		$nextURL = "https://crest-tq.eveonline.com/market/types/?group=https://crest-tq.eveonline.com/market/groups/$crestGroupAmmo/";
		$nAmmo = 0;
		doStatus("Now parsing charges. This should also be done very quickly...\n");
		while ($nextURL)
		{
			$crestStr = httpRequestWithRetries($nextURL,50);
			if (!$crestStr)
			{
				doError("Failed to contact CREST at URL '$nextURL'.",500);
				return;
			}
			$crestData = json_decode($crestStr);
			unset($crestStr);
			if (!$crestData)
			{
				doError("Failed to parse CREST response for URL '$nextURL'.",500);
				return;
			}
			if (isset($crestData->exceptionType))
			{
				doError('CREST request failed with '.$crestData->exceptionType.': "'.$crestData->message.'".',500);;
				return;
			}
			$isModule = false;
			foreach ($crestData->items as $item)
			{
				$typeId = $item->type->id;
				$typeName = $item->type->name;
				$queryInsertItem->execute();
				++$nAmmo;
				doStatus('(',$nAmmo,'/',$crestData->totalCount,') Successfully parsed ammo type \'',$typeName,'\' (',$typeId,").\n");
			}
			
			if (isset($crestData->next))
				$nextURL = $crestData->next->href;
			else
				$nextURL = null;
			unset($crestData);
		}
		doStatus("Done. $nAmmo charge types successfully parsed.\n");
		
		$nextURL = "https://crest-tq.eveonline.com/market/types/?group=https://crest-tq.eveonline.com/market/groups/$crestGroupSubsystems/";
		$nSubsystems = 0;
		doStatus("Now parsing subsystems. This might take a bit longer again.\n");
		while ($nextURL)
		{
			$crestStr = httpRequestWithRetries($nextURL,50);
			if (!$crestStr)
			{
				doError("Failed to contact CREST at URL '$nextURL'.",500);
				return;
			}
			$crestData = json_decode($crestStr);
			unset($crestStr);
			if (!$crestData)
			{
				doError("Failed to parse CREST response for URL '$nextURL'.",500);
				return;
			}
			if (isset($crestData->exceptionType))
			{
				doError('CREST request failed with '.$crestData->exceptionType.': "'.$crestData->message.'".',500);
				return;
			}
			$totalCount = $crestData->totalCount;
			foreach ($crestData->items as $item)
			{
				$typeId = $item->type->id;
				$queryDeleteItem->execute(); // we need to delete the "just" module info inserted above - subs have slot info
				$name = getCachedItemName($typeId); // NOTE: This also caches the item using CREST (slot count etc)
				if ($name)
				{
					++$nSubsystems;
					doStatus('(',$nSubsystems,'/',$totalCount,')Successfully parsed subsystem type \'',$name,'\' (',$typeId,').',"\n");
				}
				else
				{
					--$totalCount;
					doStatus("Failed to parse subsystem type $typeId from CREST. Skipping.\n");
				}
			}
			
			if (isset($crestData->next))
				$nextURL = $crestData->next->href;
			else
				$nextURL = null;
			unset($crestData);
		}
		doStatus("Done. $nSubsystems subsystem types successfully parsed.\n");
	}
	catch (PDOException $e)
	{
		doError('Import failed. Database error: '.$e,500);
	}
	doStatus("Import finished.\n");
?>