<?php
	function formatISKShort($value)
	{
		static $shortStrings = array(
			array(1000000000,'b'),
			array(   1000000,'m'),
			array(      1000,'k')
		);
		foreach ($shortStrings as $unit)
			if ($value >= $unit[0])
				return sprintf('%s%s',number_format($value/$unit[0],2),$unit[1]);
		return number_format($value,2).'&nbsp;';
	}
	
	function formatDate($date,$daysAgo)
	{
		if ($daysAgo == 0) return 'Today';
		if ($daysAgo == 1) return 'Yesterday';
		if ($daysAgo > 0 && $daysAgo < 7) return "$daysAgo days ago";
		return $date;
	}
	
	function getSecStatusColor($secStatus)
	{
		if ($secStatus <= 0)
			return '#F30202';
		if ($secStatus < 0.15)
			return '#DC3201';
		if ($secStatus < 0.25)
			return '#EB4903';
		if ($secStatus < 0.35)
			return '#F66301';
		if ($secStatus < 0.45)
			return '#E58000';
		if ($secStatus < 0.55)
			return '#F5F501';
		if ($secStatus < 0.65)
			return '#96F933';
		if ($secStatus < 0.75)
			return '#00FF00';
		if ($secStatus < 0.85)
			return '#02F34B';
		if ($secStatus < 0.95)
			return '#4BF3C3';
		return '#33F9F9';
	}
  
  function getWHEffectShort($effect)
  {
    switch ($effect)
    {
      case 'pulsar':
        return 'P';
      case 'wolfrayet':
        return 'W-R';
      case 'cataclysmic':
        return 'C';
      case 'magnetar':
        return 'M';
      case 'blackhole':
        return 'BH';
      case 'redgiant':
        return 'RG';
      default:
        doError('Parsing unknown WH effect "'.$effect.'".',200);
        return '';
    }
  }
  
  function getWHEffectLong($effect)
  {
    switch ($effect)
    {
      case 'pulsar':
        return 'Pulsar';
      case 'wolfrayet':
        return 'Wolf-Rayet Star';
      case 'cataclysmic':
        return 'Cataclysmic Variable';
      case 'magnetar':
        return 'Magnetar';
      case 'blackhole':
        return 'Black Hole';
      case 'redgiant':
        return 'Red Giant';
      default:
        doError('Parsing unknown WH effect "'.$effect.'".',200);
        return '';
    }
  }
	
	function constrain($val,$digits) // constrain value to n significant digits, unless doing so would get rid of non-decimals, in which case return as-is
	{
		if (!$val)
			return $val;
		return round($val,max($digits-log10($val),0));
	}
?>