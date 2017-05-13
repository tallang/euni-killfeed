<?php
	function httpRequest($url, $discardContent = false)
	{
		$handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle,CURLOPT_USERAGENT,'E-UNI Killfeed Beta Data Fetcher');
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
		if (!$discardContent)
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		$html = curl_exec($handle);
		if (!$html)
		{
			trigger_error(sprintf("CURL request to '$url' failed:\n%s",curl_error($handle)),E_USER_WARNING);
			curl_close($handle);
			return null;
		}
		// commenting this for now since for some reason curl refuses to return nonzero return codes for the API
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if ($httpCode && ($httpCode !== 200))
		{
			trigger_error(sprintf("CURL request to '$url' returned code %s\n",curl_getinfo($handle,CURLINFO_HTTP_CODE)),E_USER_NOTICE);
			$html = null;
		}
		
		curl_close($handle);
		return $html;
	}
	
	function httpRequestWithRetries($url, $numRetries)
	{
		while (--$numRetries)
			if ($data = @httpRequest($url))
				return $data;
		return httpRequest($url);
	}
?>