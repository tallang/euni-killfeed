-- api key system
DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
	`keyID` INT UNSIGNED NOT NULL,
	`vCode` CHAR(64) NOT NULL,
	`isCorporate` BOOL NOT NULL,
	`characterID` INT UNSIGNED,
	`latestKill` INT UNSIGNED NOT NULL,
	`cacheTime` DATETIME NOT NULL,
	PRIMARY KEY USING HASH (`keyID`),
	INDEX `api_keys_timesort` USING BTREE (`cacheTime`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `api_urls`;
CREATE TABLE `api_urls` (
  `urlId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(250) NOT NULL,
  `latestKill` INT UNSIGNED NOT NULL,
  PRIMARY KEY USING HASH (`urlId`)
) ENGINE = InnoDB;

-- data tables
DROP TABLE IF EXISTS `kill_metadata`;
CREATE TABLE `kill_metadata` (
	`id` INT UNSIGNED NOT NULL,
	`victimCharacterId` INT UNSIGNED NOT NULL,
	`victimCorporationId` INT UNSIGNED NOT NULL,
	`victimAllianceId` INT UNSIGNED NOT NULL,
	`numKillers` INT UNSIGNED NOT NULL,
	`mostCommonKillerShip` INT UNSIGNED NOT NULL,
	`secondMostCommonKillerShip` INT UNSIGNED NOT NULL,
	`shipTypeId` INT UNSIGNED NOT NULL,
	`solarSystemId` INT UNSIGNED NOT NULL,
	`killTime` DATETIME NOT NULL,
	`closestLocationId` INT UNSIGNED,
	`closestLocationDistance` DECIMAL(5,2) UNSIGNED,
	`damageTaken` INT UNSIGNED NOT NULL,
	`valueHull` DECIMAL(25,2) UNSIGNED NOT NULL,
	`valueFitted` DECIMAL(25,2) UNSIGNED NOT NULL,
	`valueDropped` DECIMAL(25,2) UNSIGNED NOT NULL,
	`valueDestroyed` DECIMAL(25,2) UNSIGNED NOT NULL,
	`valueTotal` DECIMAL(25,2) UNSIGNED NOT NULL,
	`points` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`id`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `kill_killers`;
CREATE TABLE `kill_killers` (
	`killId` INT UNSIGNED NOT NULL,
	`killerCharacterId` INT UNSIGNED NOT NULL,
	`killerCharacterName` VARCHAR(100),
	`killerShipTypeId` INT UNSIGNED NOT NULL,
	`killerWeaponTypeId` INT UNSIGNED NOT NULL,
	`killerCorporationId` INT UNSIGNED NOT NULL,
	`killerAllianceId` INT UNSIGNED NOT NULL,
	`damageDone` INT UNSIGNED NOT NULL,
	`damageFractional` DECIMAL(5,2) UNSIGNED NOT NULL,
	`finalBlow` BOOL NOT NULL,
	INDEX `kill_killers_killId` USING HASH (`killId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `kill_fittings`;
CREATE TABLE `kill_fittings` (
	`killId` INT UNSIGNED NOT NULL,
	`index` INT UNSIGNED NOT NULL,
	`hasChildren` BOOL NOT NULL,
	`parent` INT UNSIGNED,
	`slotId` TINYINT UNSIGNED NOT NULL,
	`isModule` BOOL NOT NULL,
	`typeId` INT UNSIGNED NOT NULL,
	`quantity` INT UNSIGNED NOT NULL,
	`dropped` BOOL NOT NULL,
	`value` DECIMAL(25,2) UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`killId`,`index`),
	INDEX `kill_fittings_killId_parent` USING HASH (`killId`,`parent`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `kill_comments`;
CREATE TABLE `kill_comments` (
	`killId` INT UNSIGNED NOT NULL,
	`commentDate` DATETIME NOT NULL,
	`commenter` INT UNSIGNED NOT NULL,
	`comment` TEXT NOT NULL,
	INDEX `kill_comments_killId_commentTime` USING BTREE (`killId`,`commentDate`)
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

DROP TABLE IF EXISTS `kill_day_history`;
CREATE TABLE `kill_day_history` (
	`day` DATE NOT NULL,
	`totalKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`topKillId` INT UNSIGNED NOT NULL,
	`topKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`topKillShipType` INT UNSIGNED NOT NULL,
	`firstKillId` INT UNSIGNED NOT NULL,
	`lastKillId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`day`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `character_metadata`;
CREATE TABLE `character_metadata` (
	`characterId` INT UNSIGNED NOT NULL,
	`characterName` VARCHAR(100) NOT NULL COLLATE utf8_general_ci,
	`corporationId` INT UNSIGNED NOT NULL,
	`allianceId` INT UNSIGNED NOT NULL,
	`killCount` INT UNSIGNED NOT NULL,
	`lossCount` INT UNSIGNED NOT NULL,
	`killValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`effectiveKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`lossValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`averageFriendCount` DECIMAL(6,2) UNSIGNED NOT NULL,
	`averageKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`averageEnemyCount` DECIMAL(6,2) UNSIGNED NOT NULL,
	`averageLossValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	PRIMARY KEY USING HASH (`characterId`),
	INDEX `character_metadata_characterName` USING BTREE (LCASE(`characterName`))
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

DROP TABLE IF EXISTS `corporation_metadata`;
CREATE TABLE `corporation_metadata` (
	`corporationId` INT UNSIGNED NOT NULL,
	`corporationName` VARCHAR(100) NOT NULL,
	`allianceId` INT UNSIGNED NOT NULL,
	`killCount` INT UNSIGNED NOT NULL,
	`lossCount` INT UNSIGNED NOT NULL,
	`killValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`effectiveKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`lossValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`averageFriendCount` DECIMAL(6,2) UNSIGNED NOT NULL,
	`averageKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`averageEnemyCount` DECIMAL(6,2) UNSIGNED NOT NULL,
	`averageLossValue` DECIMAL (25,2) UNSIGNED NOT NULL,
	PRIMARY KEY USING HASH (`corporationId`),
	INDEX `corporation_metadata_corporationName` USING BTREE (`corporationName`)
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

DROP TABLE IF EXISTS `alliance_metadata`;
CREATE TABLE `alliance_metadata` (
	`allianceId` INT UNSIGNED NOT NULL,
	`allianceName` VARCHAR(100) NOT NULL,
	`killCount` INT UNSIGNED NOT NULL,
	`lossCount` INT UNSIGNED NOT NULL,
	`killValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`effectiveKillValue` DECIMAL (25,2) UNSIGNED NOT NULL,
	`lossValue` DECIMAL (25,2) UNSIGNED NOT NULL,
	`averageFriendCount` DECIMAL(6,2) UNSIGNED NOT NULL,
	`averageKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`averageEnemyCount` DECIMAL(6,2) UNSIGNED NOT NULL,
	`averageLossValue` DECIMAL (25,2) UNSIGNED NOT NULL,
	PRIMARY KEY USING HASH (`allianceId`),
	INDEX `alliance_metadata_allianceName` USING BTREE (`allianceName`)
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

-- fast lookup tables
DROP TABLE IF EXISTS `character_kill_history`;
CREATE TABLE `character_kill_history` (
	`characterId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`characterId`,`killId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `corporation_kill_history`;
CREATE TABLE `corporation_kill_history` (
	`corporationId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`corporationId`,`killId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `alliance_kill_history`;
CREATE TABLE `alliance_kill_history` (
	`allianceId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`allianceId`,`killId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `ship_kill_history`;
CREATE TABLE `ship_kill_history` (
	`shipTypeId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`shipTypeId`,`killId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `solarsystem_kill_history`;
CREATE TABLE `solarsystem_kill_history` (
	`solarSystemId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`solarSystemId`,`killId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `region_kill_history`;
CREATE TABLE `region_kill_history` (
	`regionId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE (`regionId`,`killId`)
) ENGINE = InnoDB;

-- cache tables
DROP TABLE IF EXISTS `cache_iteminfo`;
CREATE TABLE `cache_iteminfo` (
	`typeID` INT UNSIGNED NOT NULL,
	`typeName` VARCHAR(100) NOT NULL,
	`highSlots` TINYINT UNSIGNED,
	`midSlots` TINYINT UNSIGNED,
	`lowSlots` TINYINT UNSIGNED,
	`rigSlots` TINYINT UNSIGNED,
	`subsystemSlots` TINYINT UNSIGNED,
	`isModule` BOOL NOT NULL,
	PRIMARY KEY USING HASH (`typeID`)
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

DROP TABLE IF EXISTS `cache_solarsysteminfo`;
CREATE TABLE `cache_solarsysteminfo` (
	`solarSystemID` INT UNSIGNED NOT NULL,
	`solarSystemName` VARCHAR(100) NOT NULL,
	`constellationName` VARCHAR(100) NOT NULL,
	`regionID` INT UNSIGNED NOT NULL,
	`regionName` VARCHAR(100) NOT NULL,
	`securityStatus` DECIMAL(2,1) NOT NULL,
	PRIMARY KEY USING HASH (`solarSystemID`)
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

DROP TABLE IF EXISTS `cache_solarsysteminfo_extra`;
CREATE TABLE `cache_solarsysteminfo_extra` (
  `solarSystemID` INT UNSIGNED NOT NULL,
  `wormholeClass` TINYINT UNSIGNED NOT NULL,
  `wormholeEffect` ENUM('pulsar','wolfrayet','cataclysmic','magnetar','blackhole','redgiant'),
  PRIMARY KEY USING HASH (`solarSystemID`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `cache_regioninfo`;
CREATE TABLE `cache_regioninfo` (
	`regionID` INT UNSIGNED NOT NULL,
	`regionName` VARCHAR(100) NOT NULL,
	PRIMARY KEY USING HASH (`regionID`)
) CHARACTER SET = 'utf8', ENGINE = InnoDB;

DROP TABLE IF EXISTS `cache_iskvalue`;
CREATE TABLE `cache_iskvalue` (
	`typeID` INT UNSIGNED NOT NULL,
	`value` DECIMAL(25,2) UNSIGNED NOT NULL,
	PRIMARY KEY USING HASH (`typeID`)
) ENGINE = InnoDB;

-- battle report data
DROP TABLE IF EXISTS `battlereport_meta`;
CREATE TABLE `battlereport_meta` (
	`reportId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ownerCharacterId` INT UNSIGNED NOT NULL,
	`lastRefreshed` DATETIME,
	`reportType` ENUM('2side'),
	PRIMARY KEY USING HASH (`reportId`)
) ENGINE = InnoDB;
SET @battlereport_meta_AI = CONCAT("ALTER TABLE `battlereport_meta` AUTO_INCREMENT = ", FLOOR(1+(0xffff*RAND())));
PREPARE battlereport_meta_AI FROM @battlereport_meta_AI;
EXECUTE battlereport_meta_AI;

DROP TABLE IF EXISTS `battlereport_sources`;
CREATE TABLE `battlereport_sources` (
	`reportId` INT UNSIGNED NOT NULL,
	`solarSystemId` INT UNSIGNED NOT NULL,
	`startTime` DATETIME NOT NULL,
	`endTime` DATETIME NOT NULL,
	INDEX `battlereport_sources_reportId` USING HASH (`reportId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `battlereport_source_lists`;
CREATE TABLE `battlereport_source_lists` (
	`reportId` INT UNSIGNED NOT NULL,
	`killId` INT UNSIGNED NOT NULL,
	`isWhitelist` BOOL NOT NULL,
	INDEX `battlereport_source_lists_reportId` USING HASH (`reportId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `battlereport_side_assignments`;
CREATE TABLE `battlereport_side_assignments` (
	`reportId` INT UNSIGNED NOT NULL,
	`entityType` ENUM('character','corporation','alliance'),
	`entityId` INT UNSIGNED NOT NULL,
	`sideId` INT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE(`reportId`,`entityType`,`entityId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `battlereport_sides`;
CREATE TABLE `battlereport_sides` (
	`reportId` INT UNSIGNED NOT NULL,
	`sideId` TINYINT UNSIGNED NOT NULL,
	`killValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`effectiveKillValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`lossValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`mainAllianceId` INT UNSIGNED NOT NULL,
	`mainCorporationId` INT UNSIGNED NOT NULL,
	`primaryDDShipTypeId` INT UNSIGNED NOT NULL,
	`primaryDDShipCount` SMALLINT UNSIGNED NOT NULL,
	`secondaryDDShipTypeId` INT UNSIGNED NOT NULL,
	`secondaryDDShipCount` SMALLINT UNSIGNED NOT NULL,
	`primaryLogiShipTypeId` INT UNSIGNED NOT NULL,
	`primaryLogiShipCount` SMALLINT UNSIGNED NOT NULL,
	PRIMARY KEY USING BTREE(`reportId`,`sideId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `battlereport_involved`;
CREATE TABLE `battlereport_involved` (
	`reportId` INT UNSIGNED NOT NULL,
	`sideId` TINYINT UNSIGNED NOT NULL,
	`characterId` INT UNSIGNED NOT NULL,
	`corporationId` INT UNSIGNED NOT NULL,
	`allianceId` INT UNSIGNED NOT NULL,
	`shipTypeId` INT UNSIGNED NOT NULL,
	`shipValue` DECIMAL(25,2) UNSIGNED NOT NULL,
	`relatedKillId` INT UNSIGNED NOT NULL,
	`numInvolved` INT UNSIGNED NOT NULL,
	INDEX `battlereport_involved_lookup` USING BTREE (`reportId`,`sideId`,`shipValue`),
	INDEX `battlereport_involved_relatedKillId` USING HASH (`relatedKillId`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `battlereport_involved_fittings`;
CREATE TABLE `battlereport_involved_fittings` (
	`reportId` INT UNSIGNED NOT NULL,
	`sideId` TINYINT UNSIGNED NOT NULL,
	`characterId` INT UNSIGNED NOT NULL,
	`shipTypeId` INT UNSIGNED NOT NULL,
	`weaponTypeId` INT UNSIGNED NOT NULL,
	`numOccurrence` SMALLINT UNSIGNED NOT NULL,
	INDEX `battlereport_involved_fittings_lookup` USING HASH (`reportId`,`sideId`,`characterId`,`shipTypeId`)
) ENGINE = InnoDB;
