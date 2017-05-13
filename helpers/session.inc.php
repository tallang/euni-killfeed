<?php
	session_save_path(__DIR__.'/../session');
	ini_set('session.gc_maxlifetime',7776000);
	ini_set('session.use_only_cookies',1);
	ini_set('session.cookie_secure',0);
	session_name('KILLFEED_SID');
	session_start();
	if(isset($_SESSION['remember']) && $_SESSION['remember'])
		setcookie(session_name(),session_id(),time()+7776000);
?>