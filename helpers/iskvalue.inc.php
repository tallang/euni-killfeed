<?php
	// ISK values are heavily used in fetch scripts
	// Thus, we cache them in current script memory in addition to database for efficiency
	$EVECentralPullCount = 0;
	$valueCache = array();
	function getValueForType($typeId)
	{
		global $valueCache;
		if (array_key_exists($typeId,$valueCache))
			return $valueCache[$typeId];
		
		try
		{
			static $cacheQuery = null;
			if (!$cacheQuery)
				$cacheQuery = prepareQuery(killfeedDB(),'SELECT `value` FROM `cache_iskvalue` WHERE `typeID` = ?');
			$cacheQuery->bindValue(1,$typeId,PDO::PARAM_INT);
			$cacheQuery->execute();

			if ($cacheHit = $cacheQuery->fetchObject())
			{
				$valueCache[$typeId] = $cacheHit->value;
				return $cacheHit->value;
			}
		}
		catch (PDOException $e)
		{
			doError('Database error while getting cached value for '.$typeId.': '.$e->getMessage(),500);
			die();
		}
		return 0;
		/*global $EVECentralPullCount;
		$EVECentralPullCount++;
		// First we try to find matching sell orders in Jita...
		$eveCentralJitaStr = httpRequest(sprintf('https://api.eve-central.com/api/quicklook?typeid=%d&usesystem=30000142',$typeId));
		if (!$eveCentralJitaStr)
			return 0;
		$eveCentralJita = simplexml_load_string($eveCentralJitaStr);
		unset($eveCentralJitaStr);
		if ($eveCentralJita->quicklook->sell_orders->order)
		{ // we have sell orders in Jita, use these
			$minPrice = INF;
			foreach ($eveCentralJita->quicklook->sell_orders->order as $order)
			{
				$orderPrice = (float)$order->price;
				if ($orderPrice < $minPrice)
					$minPrice = $orderPrice;
			}
		}
		else
		{ // we don't have sell orders in Jita, look globally (outside sov null)
			unset($eveCentralJita);
			// Regions included:
			// 10000054 - Aridia
			// 10000069 - Black Rise 
			// 10000012 - Curse
			// 10000001 - Derelik
			// 10000036 - Devoid
			// 10000043 - Domain
			// 10000064 - Essence
			// 10000037 - Everyshore
			// @todo actually finish this list and implement :effort:
			$eveCentralGlobalStr = httpRequest(sprintf('https://api.eve-central.com/api/quicklook?typeid=%d',$typeId));
			if (!$eveCentralGlobalStr)
				return 0; // @todo proper error
			$eveCentralGlobal = simplexml_load_string($eveCentralGlobalStr);
			unset($eveCentralGlobalStr);
			if ($eveCentralGlobal->quicklook->sell_orders->order)
			{ // we have sell orders _somewhere_
				$minPrice = INF;
				foreach ($eveCentralGlobal->quicklook->sell_orders->order as $order)
				{
					$orderPrice = (float)$order->price;
					if ($orderPrice < $minPrice)
						$minPrice = $orderPrice;
				}
			}
			else
			{ // well, shucks - no orders, no price, no bueno
				$minPrice = 0;
			}
		}
		
		// cache in memory
		$valueCache[$typeId] = $minPrice;
		
		// cache in DB
		static $cacheQueryInsert = null;
		if (!$cacheQueryInsert)
			$cacheQueryInsert = prepareQuery(killfeedDB(),'INSERT INTO `cache_iskvalue` (`typeID`,`value`) VALUES (?,?);');
		$cacheQueryInsert->bind_param('id',$typeId,$minPrice);
		$cacheQueryInsert->execute();
		
		return $minPrice;*/
	}
?>