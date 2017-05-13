<?php
	/* This script does a full scan of all market-listed types in CREST
	For each type, it retrieves a list of sell orders in region 10000002 (The Forge)
	then finds the lowest-priced order in Jita and stores this as the item's value. */
	require(__DIR__.'/../render/setup.inc.php');
	require(__DIR__.'/../helpers/database.inc.php');
	require(__DIR__.'/../helpers/http.inc.php');
	try
	{
		$query = prepareQuery(killfeedDB(),'REPLACE INTO `cache_iskvalue` (`typeID`,`value`) VALUES (?,?);');
		$query->bindParam(1,$typeId,PDO::PARAM_INT);
		$query->bindParam(2,$avgPrice,PDO::PARAM_STR);
		
		$numItems = 0;
		$nextURL = 'https://crest-tq.eveonline.com/market/prices/';
		while ($nextURL)
		{
			$crestStr = httpRequestWithRetries('https://crest-tq.eveonline.com/market/prices/',25);
			if (!$crestStr)
			{
				doError('Failed to contact CREST.',500);
				break;
			}
			$crestData = json_decode($crestStr);
			if (!$crestData)
			{
				doError('Failed to parse CREST response.',500);
				break;
			}
			if (isset($crestData->exceptionType))
			{
				doError('CREST request failed with '.$crestData->exceptionType.': "'.$crestData->message.'".',500);
				break;
			}
			foreach ($crestData->items as $item)
			{
				if (isset($item->averagePrice))
					$avgPrice = $item->averagePrice;
				elseif (isset($item->adjustedPrice))
					$avgPrice = $item->adjustedPrice;
				else
					$avgPrice = 0;
				$typeId = $item->type->id;
				$typeName = $item->type->name;
				doStatus("Parsed type $typeId ($typeName) priced at ",number_format($avgPrice,2),' ISK. Inserting into DB...');
				try
				{
					$query->execute();
					doStatus("Success!\n");
					++$numItems;
				}
				catch (PDOException $e)
				{
					doStatus("FAIL.\n");
				}
			}
			
			if (isset($crestData->next))
				$nextURL = $crestData->next->href;
			else
				$nextURL = null;
		}
		doStatus("Import finished. Successfully imported market prices for ",$numItems," types from CREST.\n");
	}
	catch (PDOException $e)
	{
		doStatus('Import failed. Database error: ',$e->getMessage());
	}
?>