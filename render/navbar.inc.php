<?php require_once(__DIR__.'/../helpers/session.inc.php'); require_once(__DIR__.'/../helpers/auth.inc.php'); ?> 
		<div id="navbar" class="collapsed">
			<div id="navbar-expand-button" onClick="expandNavbar();"><img class="fill" src="img/nav_expand.png" /></div>
			<div id="navbar-collapse-button" onClick="collapseNavbar();"><img class="fill" src="img/nav_collapse.png" /></div>
			<div id="navbar-wrapper"><!-- wrapper used to calculate actual content height (JS hack, ugh - css doesn't let us transition from height: fixed to height: auto, sadly) -->
				<div class="logo-container"><img class="fill" src="img/logo.png" /></div>
				<div id="navbar-search">
					<div id="navbar-search-input"><input type="text" /></div>
					<div id="navbar-search-button"><img class="fill" src="img/search.png" /></div>
				</div>
				<div id="navbar-search-results">
					<div id="navbar-search-close" onclick="this.parentElement.className=''; expandNavbar();">x</div>
					<div id="navbar-search-loading"><img src="img/loading.gif" class="fill" /></div>
					<div id="navbar-search-no-results">No results found.</div>
					<div id="navbar-search-results-error"></div>
				</div>
				<div class="navbar-label">Links</div>
				<div class="navbar-entry"><a href=".">Main page</a></div>
				<div class="navbar-entry"><a href="index2.php">WIP Feature Index</a></div><?php
				if ($_SERVER['SCRIPT_NAME'] === '/kill.php')
				{ ?> 
				<div class="navbar-label">This Kill</div>
				<div class="navbar-entry"><a href="https://zkillboard.com/kill/<?=(int)(isset($_GET['killID']) ? $_GET['killID'] : 0)?>/">View on zKillboard</a></div>
				<div class="navbar-entry"><a href="" onclick="alert('NYI'); return false;">View battle report</a></div><?php
				} ?> 
				<div class="navbar-label">User settings</div>
        <div class="navbar-entry"><a href="accessibility.php">Change color profile</a></div>
        <div class="navbar-entry"><a href="" onclick="changeColor('text1'); return false;">Change 'text1'</a></div>
        <div class="navbar-entry"><a href="" onclick="changeColor('text2'); return false;">Change 'text2'</a></div>
        <div class="navbar-entry"><a href="" onclick="changeColor('text3'); return false;">Change 'text3'</a></div>
			</div>
		</div>
		<div id="login-dialog" class="collapsed">
			<div id="login-expand-button" onClick="expandLogin();"></div>
			<div id="login-collapse-button" onClick="collapseLogin();"></div>
<?php if (isLoggedIn())
{ ?>
			<div id="login-wrapper" class="logout">
				Welcome, <b><?=htmlentities(getCurrentUserName())?></b>. (<a id="logout-text" onClick="doLogout();">Log out</a>)
			</div>
<?php }
else
{ ?>
			<div id="login-wrapper" class="login">
				<label for="login-username" style="left: 5px;">USER:</label>
				<input type="text" class="fill" id="login-username" style="left: 45px;" />
				<label for="login-password" style="left: 145px;">PASS:</label>
				<input type="password" id="login-password" style="left: 185px;" />
				<div id="login-login" onClick="doLogin();"><img class="fill" src="img/arrow-right.png" /></div>
			</div>
<?php } ?>
		</div>
		<div id="login-status" class="collapsed">
			<div id="login-status-message"></div>
		</div>
