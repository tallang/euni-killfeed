<?php
  require_once(__DIR__.'/../../helpers/cache.inc.php');
  require_once(__DIR__.'/../../helpers/format.inc.php');
  define('MAX_KILL_ID',4294967295);
  header('Content-Type: application/json');
	try
	{
    $type = isset($_POST['type']) ? $_POST['type'] : (isset($_GET['type']) ? $_GET['type'] : null);
    $typeId = isset($_POST['id']) ? +$_POST['id'] : (isset($_GET['id']) ? +$_GET['id'] : null);
    $numKills = isset($_POST['count']) ? +$_POST['count'] : (isset($_GET['count']) ? +$_GET['count'] : 30);
    $beforeId = isset($_POST['before']) ? +$_POST['before'] : (isset($_GET['before']) ? +$_GET['before'] : MAX_KILL_ID);
    $data = new stdClass();
    $db = killfeedDB();
    switch ($type)
    {
      case 'character':
      {
        if ($typeId === null)
        {
          $data->status = 'nok';
          $data->error = 'Invalid parameters';
          break;
        }
        $data->status = 'ok';
        $data->kills = [];
        $getKillQuery = prepareQuery($db, 'SELECT
            `index`.`killid` as `killId`,
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
            (`kill`.`victimCharacterId` = :characterId) as `isLoss`
          FROM `character_kill_history` as `index`
          LEFT JOIN `kill_metadata` as `kill`
            ON `kill`.`id` = `index`.`killid`
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
          WHERE `index`.`characterId` = :characterId AND `index`.`killid` < :beforeId AND `kill`.`valueTotal` > 100000
          ORDER BY `kill`.`id` DESC
          LIMIT :numKills;');
        $getKillQuery->bindValue(':characterId',$typeId,PDO::PARAM_INT);
        $getKillQuery->bindValue(':beforeId',$beforeId,PDO::PARAM_INT);
        $getKillQuery->bindValue(':numKills',$numKills,PDO::PARAM_INT);
        $getKillQuery->execute();

        $kills = fetchAll($getKillQuery,'DBKillListEntry');
        foreach ($kills as $kill)
        {
          $solarSystemData = getCachedSolarSystemInfo($kill->solarSystemId);
          $jsonKill = new stdClass();
          $jsonKill->isKill = ($kill->victimCharacterId !== $typeId);
          $jsonKill->killId = $kill->killId;
          $jsonKill->relativeDate = formatDate($kill->killDate, $kill->daysAgo);
          $jsonKill->fullTimestamp = $kill->killTime;
          $jsonKill->value = $kill->valueTotal;
          $jsonKill->valueString = formatISKShort($kill->valueTotal);
          $jsonKill->victimName = $kill->victimCharacterId ? $kill->victimCharacterName : getCachedItemName($kill->victimShipTypeId);
          $jsonKill->victimCharacterId = $kill->victimCharacterId;
          $jsonKill->victimCharacterName = $kill->victimCharacterName;
          $jsonKill->victimCorporationId = $kill->victimCorporationId;
          $jsonKill->victimCorporationName = $kill->victimCorporationName;
          $jsonKill->victimAllianceId = $kill->victimAllianceId;
          $jsonKill->victimAllianceName = $kill->victimAllianceName;
          $jsonKill->victimShipTypeId = $kill->victimShipTypeId;
          $jsonKill->victimShipName = getCachedItemName($kill->victimShipTypeId);
          $jsonKill->solarSystemName = $solarSystemData[0];
          $jsonKill->solarSystemSec = $solarSystemData[1];
          $jsonKill->killerName = $kill->killerCharacterId ? $kill->killerCharacterName : getCachedItemName($kill->killerShipTypeId);
          $jsonKill->killerCharacterId = $kill->killerCharacterId;
          $jsonKill->killerCharacterName = $kill->killerCharacterName;
          $jsonKill->killerCorporationId = $kill->killerCorporationId;
          $jsonKill->killerCorporationName = $kill->killerCorporationName;
          $jsonKill->killerAllianceId = $kill->killerAllianceId;
          $jsonKill->killerAllianceName = $kill->killerAllianceName;
          $jsonKill->mostCommonKillerShipId = $kill->mostCommonKillerShip;
          $jsonKill->mostCommonKillerShipName = getCachedItemName($kill->mostCommonKillerShip);
          $jsonKill->sendMostKillerShipId = $kill->secondMostCommonKillerShip;
          $jsonKill->secondMostKillerShipName = getCachedItemName($kill->secondMostCommonKillerShip);
          $jsonKill->numKillers = $kill->numKillers;
          
          $data->kills[] = $jsonKill;
        }
        if (count($kills) < $numKills)
          $data->kills[] = null;
        break;
      }
      default:
        $data->status = 'nok';
        $data->error = 'Invalid type specified';
        break;
    }
    echo json_encode($data);
  }
  catch (PDOException $e)
  {
    doError('DB error fetching kill listing: '.$e->getMessage(),500);
    throw new RuntimeException('Database Error');
  }
  catch (RuntimeException $e)
  {
    $data = new stdClass();
    $data->status = 'nok';
	  $data->error = $e->getMessage();
    echo json_encode($data);
  }
?>