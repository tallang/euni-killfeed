<?php
	function PROFILE_time()
	{
		return explode(" ",microtime(false));
	}
	function PROFILE_elapsed($t1, $t2 = NULL)
	{
		if (is_null($t2))
			$t2 = PROFILE_time();
		return ((+$t2[0])-(+$t1[0]))+((+$t2[1])-(+$t1[1]));
	}
?>