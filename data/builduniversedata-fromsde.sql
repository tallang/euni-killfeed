-- execute this on the SDE database
-- tables needed: mapSolarSystems, mapConstellations, mapRegions
-- change `killfeed` to be the name of your killfeed DB
REPLACE INTO `killfeed`.`cache_solarsysteminfo`
	(`solarSystemID`,`solarSystemName`,`constellationName`,`regionName`,`regionID`,`securityStatus`)
SELECT
	`sys`.`solarSystemID`,`sys`.`solarSystemName`, `cons`.`constellationName`,`reg`.`regionName`, `reg`.`regionID`, `sys`.`security`
	FROM `mapSolarSystems` AS `sys`
	LEFT JOIN `mapConstellations` AS `cons` ON `sys`.`constellationID` = `cons`.`constellationID`
	LEFT JOIN `mapRegions` AS `reg` ON `cons`.`regionID` = `reg`.`regionID`;

REPLACE INTO `killfeed`.`cache_regioninfo`
	(`regionID`,`regionName`)
SELECT
	`regionID`,`regionName`
	FROM `mapRegions`;
