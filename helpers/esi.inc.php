<?php
  require_once(__DIR__.'/http.inc.php');
  
  function getCharacterName($id)
  {
    $esiStr = httpRequest("https://esi.tech.ccp.is/latest/characters/$id/");
    if (!$esiStr)
      return "Unknown character #$id";
    $esiData = json_decode($esiStr);
    if (!$esiData)
      return "Unknown character #$id";
    return $esiData->name;
  }
  
  function getCorporationName($id)
  {
    $esiStr = httpRequest("https://esi.tech.ccp.is/latest/corporations/$id/");
    if (!$esiStr)
      return "Unknown corp #$id";
    $esiData = json_decode($esiStr);
    if (!$esiData)
      return "Unknown corp #$id";
    return $esiData->name;
  }
  
  function getAllianceName($id)
  {
    $esiStr = httpRequest("https://esi.tech.ccp.is/latest/alliances/$id/");
    if (!$esiStr)
      return "Unknown alliance #$id";
    $esiData = json_decode($esiStr);
    if (!$esiData)
      return "Unknown alliance #$id";
    return $esiData->name;
  }
?>