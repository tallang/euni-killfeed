var navbar = null;
var navbarWrapper = null;
var navbarSearchInput = null;
var navbarSearchButton = null;
var navbarSearchResults = null;
var navbarSearchResultsError = null;

var navbarSearchOnTimeout = null;
var navbarSearchTimeout = null;

var loginDialog = null;
var loginDialogWrapper = null;
var loginDialogUsername = null;
var loginDialogPassword = null;
var loginStatus = null;
var loginStatusMessage = null;
var loginLocked = false;

document.addEventListener('DOMContentLoaded', function()
{
	navbar = document.getElementById('navbar');
	navbarWrapper = document.getElementById('navbar-wrapper');
	navbarSearchInput = document.getElementById('navbar-search-input');
	navbarSearchButton = document.getElementById('navbar-search-button');
	navbarSearchResults = document.getElementById('navbar-search-results');
	navbarSearchResultsError = document.getElementById('navbar-search-results-error');
	navbarSearchInput.onkeyup = function(e) {
		if (navbarSearchTimeout)
			window.clearTimeout(navbarSearchTimeout);
		if (e.keyCode == 13)
			navbarSearchButton.click();
		else
			navbarSearchTimeout = window.setTimeout(navbarSearchOnTimeout, 1500);
	};
	navbarSearchButton.onclick = doNavbarSearch;
	
	loginDialog = document.getElementById('login-dialog');
	loginDialogWrapper = document.getElementById('login-wrapper');
	loginDialogUsername = document.getElementById('login-username');
	loginDialogPassword = document.getElementById('login-password');
	loginStatus = document.getElementById('login-status');
	loginStatusMessage = document.getElementById('login-status-message');
	
	if (loginDialogUsername && loginDialogPassword)
	{
		var detectEnter = function(e) { if (e.keyCode == 13) doLogin(); };
		loginDialogUsername.onkeyup = detectEnter;
		loginDialogPassword.onkeyup = detectEnter;
	}
});

function expandNavbar()
{
	if (!navbar) return;
	navbar.className = 'expanded';
	navbar.style.height = (navbarWrapper.clientHeight+40)+'px';
}

function collapseNavbar()
{
	if (!navbar) return;
	navbar.className = 'collapsed';
	navbar.style.height = '30px';
}

var searchLocked = false;
function navbarSearchError(msg)
{
	if (searchLocked) return;
	navbarSearchResults.className = 'error';
	navbarSearchResultsError.textContent = msg;
	expandNavbar();
}
var onNavbarSearchError = function()
{
	searchLocked = false;
	navbarSearchError('Search failed.');
};
var onNavbarSearchSuccess = function()
{
	var response = null;
	try
	{
		response = JSON.parse(this.responseText);
	}
	catch (e) {}
	if (response && (response.status == 'ok'))
	{
		var t = navbarSearchResults.firstElementChild;
		var next = null;
		while (next = t.nextElementSibling)
		{
			if (next.className == 'navbar-search-result')
				navbarSearchResults.removeChild(next);
			else
				t = next;
		}
		
		if (response.results.length > 0)
		{
			navbarSearchResults.className = 'results';
			
			for (var i=0; i < response.results.length; ++i)
			{
				var data = response.results[i];
				var name = null, avatar = null, link = null;
				
				if (data.type == 'character')
				{
					avatar = 'http://image.eveonline.com/Character/'+data.id+'_32.jpg';
					link = 'character.php?characterID='+data.id;
				}
				else if (data.type == 'corporation')
				{
					avatar = 'http://image.eveonline.com/Corporation/'+data.id+'_32.png';
					link = 'corporation.php?corporationID='+data.id;
				}
				else if (data.type == 'alliance')
				{
					avatar = 'http://image.eveonline.com/Alliance/'+data.id+'_32.png';
					link = 'alliance.php?allianceID='+data.id;
				}
				
				var mainElement = document.createElement('div');
				mainElement.className = 'navbar-search-result';
				
				var mainWrapperElement = document.createElement('a');
				mainWrapperElement.href = link;
				mainElement.appendChild(mainWrapperElement);
				
				var avatarElement = document.createElement('div');
				avatarElement.className = 'navbar-search-result-avatar';
				var avatarImgElement = document.createElement('img');
				avatarImgElement.className = 'fill';
				avatarImgElement.src = avatar;
				avatarElement.appendChild(avatarImgElement);
				mainWrapperElement.appendChild(avatarElement);
				
				var nameElement = document.createElement('div');
				nameElement.className = 'navbar-search-result-name';
				nameElement.textContent = data.name;
				mainWrapperElement.appendChild(nameElement);
				
				navbarSearchResults.appendChild(mainElement);
			}
		}
		else if (response.isFull)
			navbarSearchResults.className = 'no-results';
		else
			navbarSearchResults.className = '';
		
		searchLocked = false;
		expandNavbar();
	}
	else
		onNavbarSearchError();
};
function doNavbarSearch(full)
{
	if (!navbarSearchInput) return;
	if (searchLocked) return;
	
	var searchString = navbarSearchInput.firstElementChild.value;
	if (searchString.length == 0) return;
	if (searchString.length < 3)
	{
		navbarSearchError('Search string too short.');
		return;
	}
	
	searchLocked = true;
	navbarSearchResults.className='loading';
	expandNavbar();
	
	var xhr = new XMLHttpRequest();
	xhr.open('POST','search.php',true);
	xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	var c = 'needle='+encodeURIComponent(searchString);
	if (full !== false)
		c += '&full=1';
	xhr.setRequestHeader('Content-Length',c.length);
	xhr.onload = onNavbarSearchSuccess;
	xhr.onerror = onNavbarSearchError;
	xhr.send(c);
}
navbarSearchOnTimeout = function()
{
	if (navbar.className == 'collapsed')
		return;
	if (document.activeElement.parentElement !== navbarSearchInput)
		return;
	if (document.activeElement.length < 3)
		return;
	doNavbarSearch(false);
};

function expandLogin()
{
	if (!loginDialog) return;
	loginDialog.className = 'expanded';
	loginDialog.style.height = (loginDialogWrapper.scrollHeight+10)+'px';
	loginDialog.style.width  = (loginDialogWrapper.scrollWidth +20)+'px';
}

function collapseLogin()
{
	if (!loginDialog) return;
	loginDialog.className = 'collapsed';
	loginDialog.style.height = undefined;
	loginDialog.style.width = undefined;
}

var collapseLoginMessage = function() { loginStatus.className = 'collapsed'; loginStatus.style.width = '0px'; }
var collapseLoginMessageTimeout = 0;
function loginMessage(message,color)
{
	if (!loginStatus) return;
	loginStatusMessage.textContent = message;
	loginStatusMessage.style.color = color;
	loginStatus.className = 'expanded';
	loginStatus.style.width = (loginStatusMessage.clientWidth+20)+'px';
	
	if (collapseLoginMessageTimeout)
		window.clearTimeout(collapseLoginMessageTimeout);
	collapseLoginMessageTimeout = window.setTimeout(collapseLoginMessage,5000);
}

var onLoginError = function()
{
	loginLocked = false;
	loginMessage('LOGIN FAILED', '#FF0000');
	loginDialogUsername.className = 'error';
	loginDialogPassword.className = 'error';
};
var onLoginSuccess = function()
{
	if (this.responseText == 'ok')
	{
		loginMessage('LOGIN SUCCESS', '#00FF00');
		loginDialogUsername.className = '';
		loginDialogPassword.className = '';
		window.location.reload(true);
	}
	else
		onLoginError();
};
function doLogin()
{
	if (loginLocked) return;
	if (!loginDialogUsername || !loginDialogPassword) return;

	var username = loginDialogUsername.value;
	var password = loginDialogPassword.value;
	
	if (username.length == 0)
	{
		loginMessage('NO USERNAME', '#FF0000');
		loginDialogUsername.className = 'error';
		loginDialogPassword.className = '';
		return;
	}
	
	if (password.length == 0)
	{
		loginMessage('NO PASSWORD', '#FF0000');
		loginDialogUsername.className = '';
		loginDialogPassword.className = 'error';
		return;
	}
	
	loginLocked = true;
	loginMessage('LOGGING IN...', '#FFFF00');
	loginDialogUsername.className = '';
	loginDialogPassword.className = '';
	
	var xhr = new XMLHttpRequest();
	xhr.open('POST','login.php',true);
	xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	var c = 'user='+encodeURIComponent(username)+'&pass='+encodeURIComponent(password)+'&remember=1';
	xhr.setRequestHeader('Content-Length',c.length);
	xhr.onload = onLoginSuccess;
	xhr.onerror = onLoginError;
	xhr.send(c);
}
function doLogout()
{
	var xhr = new XMLHttpRequest();
	xhr.open('POST','logout.php',true);
	xhr.onload = xhr.onerror = function() { window.location.reload(true); };
	xhr.send();
}