<html><body><pre><?php
	require(__DIR__.'/../helpers/http.inc.php');
	
	$what = $_GET['what'];
	$href = @$_GET['href'];
	$typeId = @(int)$_GET['typeId'];
	if ($what == 'item')
		$crestStr = httpRequest("https://crest-tq.eveonline.com/inventory/types/$typeId/");
	elseif ($what == 'solarsystem')
		$crestStr = httpRequest("https://crest-tq.eveonline.com/solarsystems/$typeId/");
	elseif ($what == 'constellation')
		$crestStr = httpRequest("https://crest-tq.eveonline.com/constellations/$typeId/");
	elseif ($what == 'region')
	{
		if ($typeId > 0)
			$crestStr = httpRequest("https://crest-tq.eveonline.com/regions/$typeId/");
		else
			$crestStr = httpRequest("https://crest-tq.eveonline.com/regions/");
	}
	elseif ($what == 'marketorders')
	{
		if ($typeId > 0)
			$crestStr = httpRequest("https://crest-tq.eveonline.com/market/10000002/orders/sell/?type=https://crest-tq.eveonline.com/inventory/types/$typeId/");
		else
			$crestStr = httpRequest('https://crest-tq.eveonline.com/market/10000002/orders/sell/');
	}
	elseif ($what == 'marketgroups')
	{
		if ($typeId > 0)
			$crestStr = httpRequest("https://crest-tq.eveonline.com/market/types/?group=https://crest-tq.eveonline.com/market/groups/$typeId/");
		else
			$crestStr = httpRequest('https://crest-tq.eveonline.com/market/groups/');
	}
	elseif ($what == 'markettypes')
		$crestStr = httpRequest('https://crest-tq.eveonline.com/market/types/');
	elseif ($what == 'marketprices')
		$crestStr = httpRequest('https://crest-tq.eveonline.com/market/prices/');
	elseif ($what == 'killmail')
	{
		if (strncmp($href,'https://crest-tq.eveonline.com/killmails/',41) === 0)
			$crestStr = httpRequest($href);
	}
	if (!isset($crestStr) || !$crestStr)
		return "Invalid item: #$typeId";
	$crestData = json_decode($crestStr);
	if (!$crestData)
		return "Invalid item: #%typeId";
	
	var_dump($crestData);
?></pre></body></html>