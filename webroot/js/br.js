var getParts = window.location.search.substr(1).split("&");
var GET = {};
for(var i=0; i<getParts.length;i++) {
	var t=getParts[i].split("=");
	GET[decodeURIComponent(t[0])]=decodeURIComponent(t[1]);
}

var refreshTickTimeout;
var refreshButton, refreshText, reassignText;
var onRefreshError, onRefreshSuccess, updateRefreshTimer;

function pushReassignEntry(data,type,element) { data.push([type,element.entityId,element.sideSelected,element.originalSide]); }
function reassignSubmit()
{
	var data = [];
	var unassignedBox = document.getElementById('reassign-unassigned');
	if (unassignedBox)
	{
		if (unassignedBox.allianceBox)
			for (var i=0, len=unassignedBox.allianceBox.entries.length; i < len; ++i)
			{
				var e = unassignedBox.allianceBox.entries[i];
				if (e.sideSelected != e.originalSide)
					pushReassignEntry(data,'alliance',e);
			}
		if (unassignedBox.corporationBox)
			for (var i=0, len=unassignedBox.corporationBox.entries.length; i < len; ++i)
			{
				var e = unassignedBox.corporationBox.entries[i];
				if (e.sideSelected != e.originalSide)
					pushReassignEntry(data,'corporation',e);
			}
		if (unassignedBox.characterBox)
			for (var i=0, len=unassignedBox.characterBox.entries.length; i < len; ++i)
			{
				var e = unassignedBox.characterBox.entries[i];
				if (e.sideSelected != e.originalSide)
					pushReassignEntry(data,'character',e);
			}
	}
	var assignedBox = document.getElementById('reassign-assigned');
	if (assignedBox)
	{
		if (assignedBox.allianceBox)
			for (var i=0, len=assignedBox.allianceBox.entries.length; i < len; ++i)
			{
				var e = assignedBox.allianceBox.entries[i];
				if (e.sideSelected != e.originalSide)
					pushReassignEntry(data,'alliance',e);
			}
		if (assignedBox.corporationBox)
			for (var i=0, len=assignedBox.corporationBox.entries.length; i < len; ++i)
			{
				var e = assignedBox.corporationBox.entries[i];
				if (e.sideSelected != e.originalSide)
					pushReassignEntry(data,'corporation',e);
			}
		if (assignedBox.characterBox)
			for (var i=0, len=assignedBox.characterBox.entries.length; i < len; ++i)
			{
				var e = assignedBox.characterBox.entries[i];
				if (e.sideSelected != e.originalSide)
					pushReassignEntry(data,'character',e);
			}
	}
	if (data.length)
	{
		refreshLocked = true;
		var c = 'reportId='+GET.id+'&data='+encodeURIComponent(JSON.stringify(data));
		var xhr = new XMLHttpRequest();
		xhr.open('POST','br_update.php',true);
		xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xhr.setRequestHeader('Content-Length',c.length);
		xhr.onload = onRefreshSuccess;
		xhr.onerror = onRefreshError;
		xhr.send(c);
	}
	else
		reassignCancel();
}

function reassignCancel()
{
	var overlay = document.getElementById('reassign-overlay');
	var envelope = document.getElementById('reassign-envelope');
	if (overlay)
		document.body.removeChild(overlay);
	if (envelope)
		document.body.removeChild(envelope);
}

var boxLabelClick = function()
{
	var classes = this.parentElement.classList;
	if (classes.contains('expanded'))
	{
		classes.remove('expanded');
		classes.add('collapsed');
	}
	else
	{
		classes.remove('collapsed');
		classes.add('expanded');
	}
};

function setEntrySide(box, side)
{
	box.sideSelected = side;
	box.className = 'reassign-box-entry side-'+side;
}

function onSideSelectButtonClick() { setEntrySide(this.parentElement, this.side); }

function makeReassignBox(parent, id, name, icon, sideSelected)
{
	var entryBox = document.createElement('div');
	entryBox.className = 'reassign-box-entry side-'+sideSelected;
	entryBox.entityId = id;
	entryBox.originalSide = sideSelected;
	entryBox.sideSelected = sideSelected;
	
	var entryLogo = document.createElement('div');
	entryLogo.className = 'reassign-box-entry-logo';
	var entryLogoImg = document.createElement('img');
	entryLogoImg.className = 'fill';
	entryLogoImg.src = icon;
	entryLogo.appendChild(entryLogoImg);
	entryBox.appendChild(entryLogo);
	
	var entryLabel = document.createElement('div');
	entryLabel.className = 'reassign-box-entry-label noselect';
	entryLabel.textContent = name;
	entryBox.appendChild(entryLabel);
	
	var resetBtn = document.createElement('div');
	resetBtn.className = 'reassign-box-button-none';
	resetBtn.side = undefined;
	resetBtn.onclick = onSideSelectButtonClick;
	resetBtn.title = 'Not assigned';
	entryBox.appendChild(resetBtn);
	
	for (var side=0; side <= 2; ++side)
	{
		var btn = document.createElement('div');
		btn.className = 'reassign-box-button-'+side;
		btn.side = side;
		btn.onclick = onSideSelectButtonClick;
		if (side)
			btn.title = 'Assign to side '+side;
		else
			btn.title = 'Exclude from report';
		entryBox.appendChild(btn);
	}
	
	parent.entries.push(entryBox);
	parent.appendChild(entryBox);
}

var refreshLocked = false;
var refreshErrorText;
var resetRefreshError = function()
{
	refreshErrorText = undefined;
	updateRefreshTimer();
};
onRefreshError = function(error)
{
	refreshLocked = false;
	if (!refreshButton || !refreshText)
		return;
	if (!error)
		error = 'Transmission failed';
	refreshErrorText = error;
	updateRefreshTimer();
	window.setTimeout(resetRefreshError,2500);
	// @todo
};
onRefreshSuccess = function()
{
	var response = null;
	try
	{
		response = JSON.parse(this.responseText);
	}
	catch (e) {}
	if (response && (response.status == 'done'))
		window.location.reload(true);
	else if (response && (response.status == 'assign-request'))
	{
		reassignCancel();
		var hasAssigned = (response.assigned.character.length || response.assigned.corporation.length || response.assigned.alliance.length);
		var hasInherited = (response.inherited !== undefined);
		var hasUnassigned = (response.unassigned !== undefined);
		var overlay = document.createElement('div');
		overlay.id = 'reassign-overlay';
		document.body.appendChild(overlay);
		
		var envelope = document.createElement('div');
		envelope.id = 'reassign-envelope';
		
		var scrollbox = document.createElement('div');
		scrollbox.className = 'reassign-scrollbox';
		
		if (hasUnassigned)
		{
			var unassignedBox = document.createElement('div');
			unassignedBox.id = 'reassign-unassigned';
			unassignedBox.className = 'expanded';
			
			var unassignedLabel = document.createElement('div');
			unassignedLabel.id = 'reassign-unassigned-label';
			unassignedLabel.onclick = boxLabelClick;
			unassignedLabel.className = 'noselect';
			unassignedLabel.textContent = 'Unassigned';
			unassignedBox.appendChild(unassignedLabel);
			
			var len = response.unassigned.character.length, len2 = response.unassigned.corporation.length, len3 = response.unassigned.alliance.length;
			if (len3)
			{
				var allianceBox = document.createElement('div');
				allianceBox.className = 'reassign-box expanded';
				var allianceLabel = document.createElement('div');
				allianceLabel.className = 'reassign-box-label noselect';
				allianceLabel.onclick = boxLabelClick;
				allianceLabel.textContent = 'Alliance';
				allianceBox.appendChild(allianceLabel);
				allianceBox.entries = [];
				for (var i=0; i < len3; ++i)
				{
					var allianceData = response.unassigned.alliance[i];
					makeReassignBox(allianceBox, allianceData.allianceId, allianceData.allianceName, 'http://image.eveonline.com/Alliance/'+allianceData.allianceId+'_64.png');
				}
				unassignedBox.allianceBox = allianceBox;
				unassignedBox.appendChild(allianceBox);
			}
			if (len2)
			{
				var corporationBox = document.createElement('div');
				corporationBox.className = 'reassign-box expanded';
				var corporationLabel = document.createElement('div');
				corporationLabel.className = 'reassign-box-label';
				corporationLabel.onclick = boxLabelClick;
				corporationLabel.textContent = 'Corporation';
				corporationBox.appendChild(corporationLabel);
				corporationBox.entries = [];
				for (var i=0; i < len2; ++i)
				{
					var corporationData = response.unassigned.corporation[i];
					makeReassignBox(corporationBox, corporationData.corporationId, corporationData.corporationName, 'http://image.eveonline.com/Corporation/'+corporationData.corporationId+'_64.png');
				}
				unassignedBox.corporationBox = corporationBox;
				unassignedBox.appendChild(corporationBox);
			}
			if (len)
			{
				var characterBox = document.createElement('div');
				characterBox.className = 'reassign-box collapsed';
				var characterLabel = document.createElement('div');
				characterLabel.onclick = boxLabelClick;
				characterLabel.className = 'reassign-box-label';
				characterLabel.textContent = 'Character';
				characterBox.appendChild(characterLabel);
				characterBox.entries = [];
				for (var i=0; i < len; ++i)
				{
					var characterData = response.unassigned.character[i];
					makeReassignBox(characterBox, characterData.characterId, characterData.characterName, 'http://image.eveonline.com/Character/'+characterData.characterId+'_64.jpg');
				}
				unassignedBox.characterBox = characterBox;
				unassignedBox.appendChild(characterBox);
			}
			
			scrollbox.appendChild(unassignedBox);
		}
		
		if (hasAssigned)
		{
			var assignedBox = document.createElement('div');
			assignedBox.id = 'reassign-assigned';
			if (hasUnassigned)
				assignedBox.className = 'collapsed';
			else
				assignedBox.className = 'expanded';
			
			var assignedLabel = document.createElement('div');
			assignedLabel.id = 'reassign-assigned-label';
			assignedLabel.onclick = boxLabelClick;
			assignedLabel.className = 'noselect';
			assignedLabel.textContent = 'Assigned';
			assignedBox.appendChild(assignedLabel);
			
			var len = response.assigned.character.length, len2 = response.assigned.corporation.length, len3 = response.assigned.alliance.length;
			if (len3)
			{
				var allianceBox = document.createElement('div');
				allianceBox.className = 'reassign-box expanded';
				var allianceLabel = document.createElement('div');
				allianceLabel.className = 'reassign-box-label noselect';
				allianceLabel.onclick = boxLabelClick;
				allianceLabel.textContent = 'Alliance';
				allianceBox.appendChild(allianceLabel);
				allianceBox.entries = [];
				for (var i=0; i < len3; ++i)
				{
					var allianceData = response.assigned.alliance[i];
					makeReassignBox(allianceBox, allianceData.allianceId, allianceData.allianceName, 'http://image.eveonline.com/Alliance/'+allianceData.allianceId+'_64.png', allianceData.sideId);
				}
				assignedBox.allianceBox = allianceBox;
				assignedBox.appendChild(allianceBox);
			}
			if (len2)
			{
				var corporationBox = document.createElement('div');
				corporationBox.className = 'reassign-box expanded';
				var corporationLabel = document.createElement('div');
				corporationLabel.className = 'reassign-box-label';
				corporationLabel.onclick = boxLabelClick;
				corporationLabel.textContent = 'Corporation';
				corporationBox.appendChild(corporationLabel);
				corporationBox.entries = [];
				for (var i=0; i < len2; ++i)
				{
					var corporationData = response.assigned.corporation[i];
					makeReassignBox(corporationBox, corporationData.corporationId, corporationData.corporationName, 'http://image.eveonline.com/Corporation/'+corporationData.corporationId+'_64.png', corporationData.sideId);
				}
				assignedBox.corporationBox = corporationBox;
				assignedBox.appendChild(corporationBox);
			}
			if (len)
			{
				var characterBox = document.createElement('div');
				characterBox.className = 'reassign-box collapsed';
				var characterLabel = document.createElement('div');
				characterLabel.onclick = boxLabelClick;
				characterLabel.className = 'reassign-box-label';
				characterLabel.textContent = 'Character';
				characterBox.appendChild(characterLabel);
				characterBox.entries = [];
				for (var i=0; i < len; ++i)
				{
					var characterData = response.assigned.character[i];
					makeReassignBox(characterBox, characterData.characterId, characterData.characterName, 'http://image.eveonline.com/Character/'+characterData.characterId+'_64.jpg', characterData.sideId);
				}
				assignedBox.characterBox = characterBox;
				assignedBox.appendChild(characterBox);
			}
			
			scrollbox.appendChild(assignedBox);
		}
		
		envelope.appendChild(scrollbox);
		
		var okayButton = document.createElement('div');
		okayButton.className = 'reassign-okay';
		okayButton.textContent = 'Assign';
		okayButton.onclick = reassignSubmit;
		envelope.appendChild(okayButton);
		
		var cancelButton = document.createElement('div');
		cancelButton.textContent = 'Cancel';
		if (hasUnassigned)
			cancelButton.className = 'reassign-cancel disabled';
		else
		{
			cancelButton.className = 'reassign-cancel';
			cancelButton.onclick = reassignCancel;
		}
		envelope.appendChild(cancelButton);
		document.body.appendChild(envelope);
		refreshLocked = false;
	}
	else if (response && response.refreshDelay)
	{
		if (refreshTickTimeout !== undefined)
			window.clearTimeout(refreshTickTimeout);
		window.refreshTimeout = response.refreshDelay+1; // round up
		if (updateRefreshTimer())
			refreshTickTimeout = window.setTimeout(refreshTick,1000);
		else
			refreshTickTimeout = undefined;
	}
	else if (response && response.error)
		onRefreshError(response.error);
	else
		onRefreshError('Unknown failure');
};
function refreshBR(forceReassign)
{
	if (refreshLocked)
		return;
	refreshLocked = true;
	
	var xhr = new XMLHttpRequest();
	xhr.open('POST','br_update.php',true);
	xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	var c = 'reportId='+GET.id;
	if (forceReassign)
		c += '&reassign=1';
	xhr.setRequestHeader('Content-Length',c.length);
	xhr.onload = onRefreshSuccess;
	xhr.onerror = onRefreshError;
	xhr.send(c);
}

updateRefreshTimer = function()
{
	if (!refreshButton || !refreshText)
		return false;
	if (refreshErrorText)
	{
		refreshButton.className = 'error';
		refreshText.lastChild.textContent = refreshErrorText;
		return !!window.refreshTimeout;
	}
	else if (window.refreshTimeout)
	{
		refreshButton.className = 'disabled';
		refreshText.lastChild.textContent = 'Available in '+window.refreshTimeout+' seconds';
		return true;
	}
	else
	{
		refreshButton.className = '';
		refreshText.lastChild.textContent = 'Refresh';
		return false;
	}
};
function refreshTick()
{
	--window.refreshTimeout;
	if (updateRefreshTimer())
		refreshTickTimeout = window.setTimeout(refreshTick,1000);
	else
		refreshTickTimeout = undefined;
};

document.addEventListener('DOMContentLoaded', function()
{
	refreshButton = document.getElementById('refresh-button');
	refreshText = document.getElementById('refresh-text');
	if (refreshText)
		refreshText.onclick = function() { refreshBR(false); };
	reassignText = document.getElementById('reassign-text');
	if (reassignText)
		reassignText.onclick = function() { refreshBR(true); };
	if (window.isFirstLoad)
		refreshBR(false);
	else if (window.refreshTimeout)
	{
		if (updateRefreshTimer())
			refreshTickTimeout = window.setTimeout(refreshTick,1000);
		else
			refreshTickTimeout = undefined;
	}
});