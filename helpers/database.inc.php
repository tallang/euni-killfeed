<?php
	require_once(__DIR__.'/../config/config.inc.php');
	
	$__killfeedDB = null;
	$__authDB = null;
	function killfeedDB($persist = true)
	{
		global $__killfeedDB;
		if ($persist && $__killfeedDB)
			return $__killfeedDB;
		
		try {
			if ($persist)
				$conn = new PDO(KILLFEED_DB, DBUSER, DBPASS, array(PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
			else
				$conn = new PDO(KILLFEED_DB, DBUSER, DBPASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		}
		catch (PDOException $e)
		{
			doError(sprintf("Connecting to killfeed DB failed: %s\n", $e->getMessage()),500);
			die();
		}
		
		if ($persist)
			$__killfeedDB = $conn;
		return $conn;
	}
	
	function authDB()
	{
		global $__authDB;
		if ($__authDB)
			return $__authDB;
		
		try {
			$conn = new PDO(PHPBB_DB, DBUSER, DBPASS, array(PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		}
		catch (PDOException $e)
		{
			doError(sprintf("Connecting to auth DB failed: %s\n", $e->getMessage()),500);
			die();
		}
		
		$__authDB = $conn;
		return $conn;
	}
	
	function prepareQuery($db, $query)
	{
		try {
			$q = $db->prepare($query);
			if (!$q)
				throw new RuntimeException('Unknown error');
		}
		catch (RuntimeException $e)
		{
			doError(sprintf("Preparing of query failed: %s\nQuery was: %s\n", $e->getMessage(), $query),500);
			die();
		}
		return $q;
	}
	
	function executeQuery($query)
	{
		if (!$query->execute())
			throw new PDOException($query->errorInfo()[2]);
	}
	
	function directQuery($db, $sql)
	{
		$obj = $db->query($sql);
		if (!$obj)
			throw new PDOException($obj->errorInfo()[2]);
	}
	
	// fetches an object for each row in the result set and contains a numeric array containing them
	function fetchAll($result,$class = 'stdClass')
	{
		$rows = array();
		while ($row = $result->fetchObject($class))
			$rows[] = $row;
		return $rows;
	}
	
	/*
		PDO gives us every column as string by default.
		These constructors take the already-injected PDO attributes and cast them to what they should be.
	*/
	class DBEntityMetadata
	{
		function __construct()
		{
			if (isset($this->characterId))
				$this->characterId = +$this->characterId;
			if (isset($this->corporationId))
				$this->corporationId = +$this->corporationId;
			if (isset($this->allianceId))
				$this->allianceId = +$this->allianceId;
			if (isset($this->killCount))
				$this->killCount = +$this->killCount;
			if (isset($this->lossCount))
				$this->lossCount = +$this->lossCount;
			if (isset($this->killValue))
				$this->killValue = +$this->killValue;
			if (isset($this->effectiveKillValue))
				$this->effectiveKillValue = +$this->effectiveKillValue;
			if (isset($this->lossValue))
				$this->lossValue = +$this->lossValue;
			if (isset($this->averageFriendCount))
				$this->averageFriendCount = +$this->averageFriendCount;
			if (isset($this->averageKillValue))
				$this->averageKillValue = +$this->averageKillValue;
			if (isset($this->averageEnemyCount))
				$this->averageEnemyCount = +$this->averageEnemyCount;
			if (isset($this->averageLossValue))
				$this->averageLossValue = +$this->averageLossValue;
		}
	}
	class DBKillMetadata
	{
		function __construct()
		{
			if (isset($this->id))
				$this->id = +$this->id;
			if (isset($this->victimCharacterId))
				$this->victimCharacterId = +$this->victimCharacterId;
			if (isset($this->victimCorporationId))
				$this->victimCorporationId = +$this->victimCorporationId;
			if (isset($this->victimAllianceId))
				$this->victimAllianceId = +$this->victimAllianceId;
			if (isset($this->shipTypeId))
				$this->shipTypeId = +$this->shipTypeId;
			if (isset($this->solarSystemId))
				$this->solarSystemId = +$this->solarSystemId;
			if (isset($this->closestLocationId))
				$this->closestLocationId = +$this->closestLocationId;
			if (isset($this->closestLocationDistance))
				$this->closestLocationDistance = +$this->closestLocationDistance;
			if (isset($this->damageTaken))
				$this->damageTaken = +$this->damageTaken;
			if (isset($this->valueHull))
				$this->valueHull = +$this->valueHull;
			if (isset($this->valueFitted))
				$this->valueFitted = +$this->valueFitted;
			if (isset($this->valueDropped))
				$this->valueDropped = +$this->valueDropped;
			if (isset($this->valueDestroyed))
				$this->valueDestroyed = +$this->valueDestroyed;
			if (isset($this->valueTotal))
				$this->valueTotal = +$this->valueTotal;
			if (isset($this->points))
				$this->points = +$this->points;
			if (isset($this->mostCommonKillerShip))
				$this->mostCommonKillerShip = +$this->mostCommonKillerShip;
			if (isset($this->secondMostCommonKillerShip))
				$this->secondMostCommonKillerShip = +$this->secondMostCommonKillerShip;
		}
	}
	class DBKillKiller
	{
		function __construct()
		{
			if (isset($this->killId))
				$this->killId = +$this->killId;
			if (isset($this->characterId))
				$this->characterId = +$this->characterId;
			if (isset($this->shipTypeId))
				$this->shipTypeId = +$this->shipTypeId;
			if (isset($this->weaponTypeId))
				$this->weaponTypeId = +$this->weaponTypeId;
			if (isset($this->corporationId))
				$this->corporationId = +$this->corporationId;
			if (isset($this->allianceId))
				$this->allianceId = +$this->allianceId;
			if (isset($this->damageDone))
				$this->damageDone = +$this->damageDone;
			if (isset($this->damageFractional))
				$this->damageFractional = +$this->damageFractional;
			if (isset($this->finalBlow))
				$this->finalBlow = !!$this->finalBlow;
		}
	}
	class DBKillFitting
	{
		function __construct()
		{
			if (isset($this->killId))
				$this->killId = +$this->killId;
			if (isset($this->index))
				$this->index = +$this->index;
			if (isset($this->hasChildren))
				$this->hasChildren = !!$this->hasChildren;
			if (isset($this->parent))
				$this->parent = +$this->parent;
			if (isset($this->slotId))
				$this->slotId = +$this->slotId;
			if (isset($this->isModule))
				$this->isModule = !!$this->isModule;
			if (isset($this->typeId))
				$this->typeId = +$this->typeId;
			if (isset($this->quantity))
				$this->quantity = +$this->quantity;
			if (isset($this->dropped))
				$this->dropped = !!$this->dropped;
			if (isset($this->value))
				$this->value = +$this->value;
		}
	}
	class DBKillComment
	{
		function __construct()
		{
			if (isset($this->killId))
				$this->killId = +$this->killId;
			if (isset($this->commenter))
				$this->commenter = +$this->commenter;
		}
	}
	class DBKillListEntry
	{
		function __construct()
		{
			if (isset($this->killId))
				$this->killId = +$this->killId;
			if (isset($this->victimCharacterId))
				$this->victimCharacterId = +$this->victimCharacterId;
			if (isset($this->victimCorporationId))
				$this->victimCorporationId = +$this->victimCorporationId;
			if (isset($this->victimAllianceId))
				$this->victimAllianceId = +$this->victimAllianceId;
			if (isset($this->victimShipTypeId))
				$this->victimShipTypeId = +$this->victimShipTypeId;
			if (isset($this->solarSystemId))
				$this->solarSystemId = +$this->solarSystemId;
			if (isset($this->valueTotal))
				$this->valueTotal = +$this->valueTotal;
			if (isset($this->mostCommonKillerShip))
				$this->mostCommonKillerShip = +$this->mostCommonKillerShip;
			if (isset($this->secondMostCommonKillerShip))
				$this->secondMostCommonKillerShip = +$this->secondMostCommonKillerShip;
			if (isset($this->numKillers))
				$this->numKillers = +$this->numKillers;
			if (isset($this->killerCharacterId))
				$this->killerCharacterId = +$this->killerCharacterId;
			if (isset($this->killerCorporationId))
				$this->killerCorporationId = +$this->killerCorporationId;
			if (isset($this->killerAllianceId))
				$this->killerAllianceId = +$this->killerAllianceId;
			if (isset($this->killerShipTypeId))
				$this->killerShipTypeId = +$this->killerShipTypeId;
			if (isset($this->daysAgo))
				$this->daysAgo = +$this->daysAgo;
			if (isset($this->isLoss))
				$this->isLoss = !!$this->isLoss;
		}
	}
	class DBDailyHistoryEntry
	{
		function __construct()
		{
			if (isset($this->daysAgo))
				$this->daysAgo = +$this->daysAgo;
			if (isset($this->totalKillValue))
				$this->totalKillValue = +$this->totalKillValue;
			if (isset($this->topKillId))
				$this->topKillId = +$this->topKillId;
			if (isset($this->topKillValue))
				$this->topKillValue = +$this->topKillValue;
			if (isset($this->topKillShipType))
				$this->topKillShipType = +$this->topKillShipType;
		}
	}
	class DBSearchResult
	{
		function __construct()
		{
			if (isset($this->id))
				$this->id = +$this->id;
		}
	}
	class DBReportMetadata
	{
		function __construct()
		{
			if (isset($this->reportId))
				$this->reportId = +$this->reportId;
			if (isset($this->ownerCharacterId))
				$this->ownerCharacterId = +$this->ownerCharacterId;
		}
	}
	class DBReportSideMeta
	{
		function __construct()
		{
			if (isset($this->reportId))
				$this->reportId = +$this->reportId;
			if (isset($this->sideId))
				$this->sideId = +$this->sideId;
			if (isset($this->killValue))
				$this->killValue = +$this->killValue;
			if (isset($this->effectiveKillValue))
				$this->effectiveKillValue = +$this->effectiveKillValue;
			if (isset($this->lossValue))
				$this->lossValue = +$this->lossValue;
			if (isset($this->mainAllianceId))
				$this->mainAllianceId = +$this->mainAllianceId;
			if (isset($this->mainCorporationId))
				$this->mainCorporationId = +$this->mainCorporationId;
			if (isset($this->primaryDDShipTypeId))
				$this->primaryDDShipTypeId = +$this->primaryDDShipTypeId;
			if (isset($this->primaryDDShipCount))
				$this->primaryDDShipCount = +$this->primaryDDShipCount;
			if (isset($this->secondaryDDShipTypeId))
				$this->secondaryDDShipTypeId = +$this->secondaryDDShipTypeId;
			if (isset($this->secondaryDDShipCount))
				$this->secondaryDDShipCount = +$this->secondaryDDShipCount;
			if (isset($this->primaryLogiShipTypeId))
				$this->primaryLogiShipTypeId = +$this->primaryLogiShipTypeId;
			if (isset($this->primaryLogiShipCount))
				$this->primaryLogiShipCount = +$this->primaryLogiShipCount;
		}
	}
	class DBReportInvolved
	{
		function __construct()
		{
			if (isset($this->reportId))
				$this->reportId = +$this->reportId;
			if (isset($this->sideId))
				$this->sideId = +$this->sideId;
			if (isset($this->characterId))
				$this->characterId = +$this->characterId;
			if (isset($this->corporationId))
				$this->corporationId = +$this->corporationId;
			if (isset($this->allianceId))
				$this->allianceId = +$this->allianceId;
			if (isset($this->shipTypeId))
				$this->shipTypeId = +$this->shipTypeId;
			if (isset($this->shipValue))
				$this->shipValue = +$this->shipValue;
			if (isset($this->relatedKillId))
				$this->relatedKillId = +$this->relatedKillId;
			if (isset($this->numInvolved))
				$this->numInvolved = +$this->numInvolved;
		}
	}
	class DBReportInvolvedFitting
	{
		function __construct()
		{
			if (isset($this->reportId))
				$this->reportId = +$this->reportId;
			if (isset($this->sideId))
				$this->sideId = +$this->sideId;
			if (isset($this->characterId))
				$this->characterId = +$this->characterId;
			if (isset($this->shipTypeId))
				$this->shipTypeId = +$this->shipTypeId;
			if (isset($this->weaponTypeId))
				$this->weaponTypeId = +$this->weaponTypeId;
			if (isset($this->numOccurrence))
				$this->numOccurrence = +$this->numOccurrence;
		}
	}
?>