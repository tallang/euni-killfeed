<?php
	require_once(__DIR__.'/database.inc.php');
	require_once(__DIR__.'/http.inc.php');
	
	/* ITEM DATA CACHING */
	define('DOGMA_ATTRIBUTE_HIGH_SLOTS',14);
	define('DOGMA_ATTRIBUTE_HIGH_SLOTS_MOD',1374);
	define('DOGMA_ATTRIBUTE_MID_SLOTS',13);
	define('DOGMA_ATTRIBUTE_MID_SLOTS_MOD',1375);
	define('DOGMA_ATTRIBUTE_LOW_SLOTS',12);
	define('DOGMA_ATTRIBUTE_LOW_SLOTS_MOD',1376);
	define('DOGMA_ATTRIBUTE_RIG_SLOTS',1137);
	define('DOGMA_ATTRIBUTE_SUBSYSTEMS',1367);
	define('DOGMA_EFFECT_HIGH_SLOT',12);
	define('DOGMA_EFFECT_MID_SLOT',13);
	define('DOGMA_EFFECT_LOW_SLOT',11);
	define('DOGMA_EFFECT_RIG_SLOT',2663);
	define('DOGMA_EFFECT_SUBSYSTEM',3772);
	define('CACHETYPE_ITEM_NAME',1);
	define('CACHETYPE_ITEM_SLOTS',2);
	define('CACHETYPE_ITEM_IS_MODULE',3);
	$CRESTPullCount = 0;
	function populateCacheItem($typeId,$returnWhat)
	{
		global $CRESTPullCount;
		$CRESTPullCount++;
		$crestStr = httpRequest("https://crest-tq.eveonline.com/inventory/types/$typeId/");
		if (!$crestStr) 
			return null;
		$crestData = json_decode($crestStr);
		if (!$crestData)
			return null;
		
		$itemName = $crestData->name;
		$highSlots = $midSlots = $lowSlots = $rigSlots = $subSlots = 0;
		if (isset($crestData->dogma) && isset($crestData->dogma->attributes))
			foreach ($crestData->dogma->attributes as $attr)
			{
				switch ($attr->attribute->id)
				{
					case DOGMA_ATTRIBUTE_HIGH_SLOTS:
					case DOGMA_ATTRIBUTE_HIGH_SLOTS_MOD:
						$highSlots = $attr->value;
						break;
					case DOGMA_ATTRIBUTE_MID_SLOTS:
					case DOGMA_ATTRIBUTE_MID_SLOTS_MOD:
						$midSlots = $attr->value;
						break;
					case DOGMA_ATTRIBUTE_LOW_SLOTS:
					case DOGMA_ATTRIBUTE_LOW_SLOTS_MOD:
						$lowSlots = $attr->value;
						break;
					case DOGMA_ATTRIBUTE_RIG_SLOTS:
						$rigSlots = $attr->value;
						break;
					case DOGMA_ATTRIBUTE_SUBSYSTEMS:
						$subSlots = $attr->value;
						break;
				}
			}
		
		$isModule = false;
		if (isset($crestData->dogma) && isset($crestData->dogma->effects))
			foreach ($crestData->dogma->effects as $effect)
				if (
					$effect->effect->id == DOGMA_EFFECT_HIGH_SLOT ||
					$effect->effect->id == DOGMA_EFFECT_MID_SLOT ||
					$effect->effect->id == DOGMA_EFFECT_LOW_SLOT ||
					$effect->effect->id == DOGMA_EFFECT_RIG_SLOT ||
					$effect->effect->id == DOGMA_EFFECT_SUBSYSTEM
				)
				{
					$isModule = true;
					break;
				}

		if ($highSlots > 0 || $midSlots > 0 || $lowSlots > 0 || $rigSlots > 0 || $subSlots > 0) // this insert is also used for subsystems, btw - not just for ships
		{
			try
			{
				static $cacheQueryInsertShipData = null;
				if (!$cacheQueryInsertShipData)
					$cacheQueryInsertShipData = prepareQuery(killfeedDB(),'INSERT INTO `cache_iteminfo` (`typeID`,`typeName`,`highSlots`,`midSlots`,`lowSlots`,`rigSlots`,`subsystemSlots`,`isModule`) VALUES (?,?,?,?,?,?,?,?);');
				$cacheQueryInsertShipData->bindValue(1,$typeId,PDO::PARAM_INT);
				$cacheQueryInsertShipData->bindValue(2,$itemName,PDO::PARAM_STR);
				$cacheQueryInsertShipData->bindValue(3,$highSlots,PDO::PARAM_INT);
				$cacheQueryInsertShipData->bindValue(4,$midSlots,PDO::PARAM_INT);
				$cacheQueryInsertShipData->bindValue(5,$lowSlots,PDO::PARAM_INT);
				$cacheQueryInsertShipData->bindValue(6,$rigSlots,PDO::PARAM_INT);
				$cacheQueryInsertShipData->bindValue(7,$subSlots,PDO::PARAM_INT);
				$cacheQueryInsertShipData->bindValue(8,$isModule,PDO::PARAM_BOOL);
				$cacheQueryInsertShipData->execute();
			}
			catch (PDOException $e)
			{
				doError('Database error while getting cached data for '.$typeId.': '.$e->getMessage(),500);
				die();
			}
		}
		else
		{
			try
			{
				static $cacheQueryInsertItemData = null;
				if (!$cacheQueryInsertItemData)
					$cacheQueryInsertItemData = prepareQuery(killfeedDB(),'INSERT INTO `cache_iteminfo` (`typeID`,`typeName`,`isModule`) VALUES (?,?,?);');
				$cacheQueryInsertItemData->bindValue(1,$typeId,PDO::PARAM_INT);
				$cacheQueryInsertItemData->bindValue(2,$itemName,PDO::PARAM_STR);
				$cacheQueryInsertItemData->bindValue(3,$isModule,PDO::PARAM_BOOL);
				$cacheQueryInsertItemData->execute();
			}
			catch (PDOException $e)
			{
				doError('Database error while building cached data for '.$typeId.': '.$e->getMessage(),500);
				die();
			}
		}
		
		switch ($returnWhat)
		{
			case CACHETYPE_ITEM_NAME:
				return $itemName;
			case CACHETYPE_ITEM_SLOTS:
				if ($highSlots >= 0)
					return array($highSlots, $midSlots, $lowSlots, $rigSlots, $subSlots);
				else
					return array(0,0,0,0,0);
			case CACHETYPE_ITEM_IS_MODULE:
				return $isModule;
		}
	}
	
	function getCachedItemName($typeId)
	{
		if (!$typeId) return 'Unknown';
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `typeName` FROM `cache_iteminfo` WHERE `typeID` = ?');
			$cacheQuery->bindValue(1,$typeId,PDO::PARAM_INT);
			$cacheQuery->execute();
			
			if ($cacheHit = $cacheQuery->fetchColumn(0))
				return $cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached data for '.$typeId.': '.$e->getMessage(),500);
			die();
		}
		
		// typename not found in cache - resolve using CREST
		return populateCacheItem($typeId,CACHETYPE_ITEM_NAME);
	}
	
	function getCachedItemSlots($typeId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `highSlots`,`midSlots`,`lowSlots`,`rigSlots`,`subsystemSlots` FROM `cache_iteminfo` WHERE `typeID` = ?');
			$cacheQuery->bindValue(1,$typeId,PDO::PARAM_INT);
			$cacheQuery->execute();
			
			if ($cacheHit = $cacheQuery->fetchObject())
				return array(+$cacheHit->highSlots, +$cacheHit->midSlots, +$cacheHit->lowSlots, +$cacheHit->rigSlots, +$cacheHit->subsystemSlots);
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached data for '.$typeId.': '.$e->getMessage(),500);
			die();
		}
		
		// data isn't cached yet - ask CREST for info
		return populateCacheItem($typeId,CACHETYPE_ITEM_SLOTS);
	}
	
	function isCachedItemModule($typeId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `isModule` FROM `cache_iteminfo` WHERE `typeId` = ?');
			$cacheQuery->bindValue(1,$typeId,PDO::PARAM_INT);
			$cacheQuery->execute();
			
			if ($cacheHit = $cacheQuery->fetchObject())
				return (bool)$cacheHit->isModule;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached data for '.$typeId.': '.$e->getMessage(),500);
			die();
		}
		
		// ask CREST
		return populateCacheItem($typeId,CACHETYPE_ITEM_IS_MODULE);
	}
	
	
	/* SYSTEM DATA CACHING */
	define('CACHETYPE_REGION_NAME',1);
	define('CACHETYPE_SYSTEM_NAME',1);
	define('CACHETYPE_SYSTEM_REGION',2);
	define('CACHETYPE_SYSTEM_SEC_STATUS',3);
	define('CACHETYPE_SYSTEM_CONSTELLATION',4);
	define('CACHETYPE_SYSTEM_INFO',5);
	define('CACHETYPE_SYSTEM_REGION_NAME',6);

	function populateCacheRegion($regionId,$returnWhat)
	{
		global $CRESTPullCount;
		$CRESTPullCount++;
		$crestStr = httpRequest("https://crest-tq.eveonline.com/regions/$regionId/");
		if (!$crestStr)
			return null; // @todo
		$crestData = json_decode($crestStr);
		if (!$crestData)
			return null;
		unset($crestStr);
		
		$regionName = $crestData->name;
		
		try
		{
			static $cacheQueryInsert = null;
			if (!$cacheQueryInsert)
				$cacheQueryInsert = prepareQuery(killfeedDB(),'INSERT INTO `cache_regioninfo` (`regionId`,`regionName`) VALUES (?,?);');
			$cacheQueryInsert->bindValue(1,$regionId,PDO::PARAM_INT);
			$cacheQueryInsert->bindValue(2,$regionName,PDO::PARAM_STR);
			$cacheQueryInsert->execute();
		}
		catch (Exception $e)
		{
			doError('Database error while building cached region data for '.$regionId.': '.$e->getMessage(),500);
			die();
		}			
		
		switch ($returnWhat)
		{
			case CACHETYPE_REGION_NAME:
				return $regionName;
		}
	}
	
	function populateCacheSolarSystem($solarSystemId,$returnWhat)
	{
		global $CRESTPullCount;
		$CRESTPullCount++;
		$crestStr = httpRequest("https://crest-tq.eveonline.com/solarsystems/$solarSystemId/");
		if (!$crestStr)
			return null;
		$crestData = json_decode($crestStr);
		if (!$crestData)
			return null;
		unset($crestStr);
		
		$systemName = $crestData->name;
		$securityStatus = $crestData->securityStatus;
		
		$constellationURL = $crestData->constellation->href;
		unset($crestData);
		
		$constellationStr = httpRequest($constellationURL);
		if (!$constellationStr)
			return null; // @todo error
		$constellationData = json_decode($constellationStr);
		if (!$constellationData)
			return null;
		unset($constellationStr);
		
		$constellationName = $constellationData->name;
		$regionURL = $constellationData->region->href;
		unset($constellationData);
		
		$regionId = (int)preg_replace("/[^0-9]/","",$regionURL); // @todo get back to this and see if foxfour has added numeric id here (would be nice)
		$regionName = getCachedRegionName($regionId);
		try
		{
			static $cacheQueryInsertSolarSystemData = null;
			if (!$cacheQueryInsertSolarSystemData)
				$cacheQueryInsertSolarSystemData = prepareQuery(killfeedDB(),'INSERT INTO `cache_solarsysteminfo` (`solarSystemID`,`solarSystemName`,`constellationName`,`regionID`,`regionName`,`securityStatus`) VALUES (?,?,?,?,?,?);');
			$cacheQueryInsertSolarSystemData->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQueryInsertSolarSystemData->bindValue(2,$systemName,PDO::PARAM_STR);
			$cacheQueryInsertSolarSystemData->bindValue(3,$constellationName,PDO::PARAM_STR);
			$cacheQueryInsertSolarSystemData->bindValue(4,$regionId,PDO::PARAM_INT);
			$cacheQueryInsertSolarSystemData->bindValue(5,$regionName,PDO::PARAM_STR);
			$cacheQueryInsertSolarSystemData->bindValue(6,$securityStatus,PDO::PARAM_STR);
			$cacheQueryInsertSolarSystemData->execute();
		}
		catch (PDOException $e)
		{
			doError('Database error while build cached data for solar system '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		switch ($returnWhat)
		{
			case CACHETYPE_SYSTEM_NAME:
				return $systemName;
			case CACHETYPE_SYSTEM_REGION:
				return $regionId;
			case CACHETYPE_SYSTEM_REGION_NAME:
				return $regionName;
			case CACHETYPE_SYSTEM_SEC_STATUS:
				return $securityStatus;
			case CACHETYPE_SYSTEM_CONSTELLATION:
				return $constellationName;
			case CACHETYPE_SYSTEM_INFO:
				return array($systemName,$securityStatus,$constellationname,$regionId);
		}
	}
	
	function getCachedRegionName($regionId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `regionName` FROM `cache_regioninfo` WHERE `regionId` = ?');
			$cacheQuery->bindValue(1,$regionId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchColumn())
				return $cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached region data for '.$regionId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheRegion($regionId,CACHETYPE_REGION_NAME);
	}
	
	function getCachedSolarSystemName($solarSystemId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `solarSystemName` FROM `cache_solarsysteminfo` WHERE `solarSystemId` = ?');
			$cacheQuery->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchColumn())
				return $cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached system data for '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheSolarSystem($solarSystemId,CACHETYPE_SYSTEM_NAME);
	}
	
	function getCachedSolarSystemSecStatus($solarSystemId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `solarSystemName` FROM `cache_solarsysteminfo` WHERE `solarSystemId` = ?');
			$cacheQuery->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchColumn())
				return +$cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached system data for '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheSolarSystem($solarSystemId,CACHETYPE_SYSTEM_NAME);
	}
	
	function getCachedSolarSystemConstellation($solarSystemId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `constellationName` FROM `cache_solarsysteminfo` WHERE `solarSystemId` = ?');
			$cacheQuery->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchColumn())
				return $cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached solar system data for '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheSolarSystem($solarSystemId,CACHETYPE_CONSTELLATION_NAME);
	}
	
	function getCachedSolarSystemRegionID($solarSystemId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `regionId` FROM `cache_solarsysteminfo` WHERE `solarSystemId` = ?');
			$cacheQuery->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchColumn())
				return +$cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached solar system data for '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheSolarSystem($solarSystemId,CACHETYPE_SYSTEM_REGION);
	}
	
	function getCachedSolarSystemRegionName($solarSystemId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `regionName` FROM `cache_solarsysteminfo` WHERE `solarSystemId` = ?');
			$cacheQuery->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchColumn())
				return $cacheHit;
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached data for '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheSolarSystem($solarSystemId,CACHETYPE_SYSTEM_REGION_NAME);
	}
	
  //                  0    1         2             3          4        5     6
	// return value is [name,secstatus,constellation,regionName,regionId,class,effect], except it does it in a single cache hit instead of 4 (saves query time)
	function getCachedSolarSystemInfo($solarSystemId)
	{
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT cssi.`solarSystemName`,cssi.`securityStatus`,cssi.`constellationName`,cssi.`regionName`,cssi.`regionId`,csse.`wormholeClass`,csse.`wormholeEffect` FROM `cache_solarsysteminfo` as cssi LEFT JOIN `cache_solarsysteminfo_extra` as csse on cssi.`solarSystemId`=csse.`solarSystemId` WHERE cssi.`solarSystemId` = ?');
			$cacheQuery->bindValue(1,$solarSystemId,PDO::PARAM_INT);
			$cacheQuery->execute();
			if ($cacheHit = $cacheQuery->fetchObject())
				return [$cacheHit->solarSystemName,+$cacheHit->securityStatus,$cacheHit->constellationName,$cacheHit->regionName,+$cacheHit->regionId,+$cacheHit->wormholeClass,$cacheHit->wormholeEffect];
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached system data for '.$solarSystemId.': '.$e->getMessage(),500);
			die();
		}
		
		return populateCacheSolarSystem($solarSystemId,CACHETYPE_SYSTEM_INFO);
	}
?>