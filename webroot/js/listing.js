var listingContainer = null;
var listingData = [];
var currentListingPage = 1;
const LISTING_FILTER_NONE = 0, LISTING_FILTER_KILLS = 1, LISTING_FILTER_LOSSES = 2;
var currentListingFilter = LISTING_FILTER_NONE;

function getSecStatusColor(sec)
{
		if (sec <= 0)
			return '#F30202';
		if (sec < 0.15)
			return '#DC3201';
		if (sec < 0.25)
			return '#EB4903';
		if (sec < 0.35)
			return '#F66301';
		if (sec < 0.45)
			return '#E58000';
		if (sec < 0.55)
			return '#F5F501';
		if (sec < 0.65)
			return '#96F933';
		if (sec < 0.75)
			return '#00FF00';
		if (sec < 0.85)
			return '#02F34B';
		if (sec < 0.95)
			return '#4BF3C3';
		return '#33F9F9';
}

function checkFilter(killmail)
{
  switch (currentListingFilter)
  {
    case LISTING_FILTER_NONE:
      return true;
    case LISTING_FILTER_KILLS:
      return killmail.isKill;
    case LISTING_FILTER_LOSSES:
      return !killmail.isKill;
  }
  return true;
}

function isFiltered()
{
  return (currentListingFilter != LISTING_FILTER_NONE);
}

var listingLoadError = function(error)
{
  console.log('Error: '+error);
};

var listingLoadSuccess = function()
{
  var response = null;
  try
  {
    response = JSON.parse(this.responseText);
  }
  catch (e) {}
  
  if (response && response.status === 'ok')
  {
    for (var i=0, len=response.kills.length; i < len; ++i)
      listingData.push(response.kills[i]);
    redrawKillListing();
  }
  else if (response && response.error)
    listingLoadError(response.error);
  else
    listingLoadError('Unknown failure');
};

function redrawKillListing()
{
  if (!listingContainer)
    return;
  while (listingContainer.lastElementChild)
    listingContainer.removeChild(listingContainer.lastElementChild);
  
  const KILLS_PER_PAGE = 30;
  var skipCount = (currentListingPage-1) * KILLS_PER_PAGE;
  var renderCount = KILLS_PER_PAGE;
  var lastKillDate = null;
  for (var i=0, len = listingData.length; renderCount && (i < len); ++i)
  {
    var kill = listingData[i];
    
    if (kill === null) // null entry means server has indicated end of data
    {
      skipCount = 0;
      renderCount = 0;
      break;
    }
    
    if (!checkFilter(kill))
      continue;
    
    if (skipCount)
    {
      --skipCount;
      continue;
    }
    
    // render this kill now
    if (lastKillDate != kill.relativeDate)
    {
      var label = document.createElement('div');
      label.className = 'listing-date-label noselect';
      label.textContent = kill.relativeDate;
      listingContainer.appendChild(label);
      lastKillDate = kill.relativeDate;
    }
    
    var entry = document.createElement('div');
    if (kill.isKill)
      entry.className = 'listing-entry kill';
    else
      entry.className = 'listing-entry loss';
    var linkWrapper = document.createElement('a');
    linkWrapper.href = 'kill.php?killID='+kill.killId;
    
    var timestamp = document.createElement('div');
    timestamp.className = 'listing-kill-time';
    timestamp.textContent = kill.fullTimestamp;
    linkWrapper.appendChild(timestamp);
    
    var value = document.createElement('div');
    value.className = 'listing-kill-value';
    value.textContent = kill.valueString;
    linkWrapper.appendChild(value);
    
    var victimAvatar = document.createElement('div');
    victimAvatar.className = 'listing-victim-avatar';
    var victimAvatarImg = document.createElement('img');
    victimAvatarImg.className = 'fill';
    victimAvatarImg.src = 'http://image.eveonline.com/Character/'+kill.victimCharacterId+'_64.jpg';
    victimAvatar.appendChild(victimAvatarImg);
    linkWrapper.appendChild(victimAvatar);
    
    var victimShipIcon = document.createElement('div');
    victimShipIcon.className = 'listing-victim-icon';
    var victimShipIconImg = document.createElement('img');
    victimShipIconImg.className = 'fill';
    victimShipIconImg.src = 'http://image.eveonline.com/Type/'+kill.victimShipTypeId+'_64.png';
    victimShipIcon.appendChild(victimShipIconImg);
    linkWrapper.appendChild(victimShipIcon);
    
    var victimName = document.createElement('div');
    victimName.className = 'listing-victim-name';
    victimName.textContent = kill.victimName;
    linkWrapper.appendChild(victimName);
    
    var victimCorp = document.createElement('div');
    victimCorp.className = 'listing-victim-corp';
    if (kill.victimAllianceId)
      victimCorp.textContent = kill.victimCorporationName + ' / ' + kill.victimAllianceName;
    else
      victimCorp.textContent = kill.victimCorporationName;
    linkWrapper.appendChild(victimCorp);
    
    var victimShip = document.createElement('div');
    victimShip.className = 'listing-victim-ship';
    var victimShipText1 = document.createTextNode(kill.victimShipName + ' / ' + kill.solarSystemName + ' (');
    var secStatusText = document.createElement('span');
    secStatusText.style.color = getSecStatusColor(kill.solarSystemSec);
    secStatusText.textContent = kill.solarSystemSec.toFixed(1);
    var victimShipText2 = document.createTextNode(')');
    victimShip.appendChild(victimShipText1);
    victimShip.appendChild(secStatusText);
    victimShip.appendChild(victimShipText2);
    linkWrapper.appendChild(victimShip);
    
    var killerIcon1 = document.createElement('div');
    if (kill.secondMostKillerShipId)
      killerIcon1.className = 'listing-killer-icon1';
    else
      killerIcon1.className = 'listing-killer-icon1 offset';
    var killerIcon1Img = document.createElement('img');
    killerIcon1Img.className = 'fill';
    killerIcon1Img.src = 'http://image.eveonline.com/Type/'+kill.mostCommonKillerShipId+'_64.png';
    killerIcon1.appendChild(killerIcon1Img);
    linkWrapper.appendChild(killerIcon1);
    
    if (kill.secondMostKillerShipId)
    {
      var killerIcon2 = document.createElement('div');
      killerIcon2.className = 'listing-killer-icon2';
      var killerIcon2Img = document.createElement('img');
      killerIcon2Img.className = 'fill';
      killerIcon2Img.src = 'http://image.eveonline.com/Type/'+kill.secondMostKillerShipId+'_64.png';
      killerIcon2.appendChild(killerIcon2Img);
      linkWrapper.appendChild(killerIcon2);
    }
    
    var killerName = document.createElement('div');
    killerName.className = 'listing-killer-name';
    var killerNameText1 = document.createTextNode('by ');
    var killerNameText2 = document.createElement('span');
    killerNameText2.style.fontWeight = 'bold';
    killerNameText2.textContent = kill.killerName;
    killerName.appendChild(killerNameText1);
    killerName.appendChild(killerNameText2);
    if (kill.numKillers > 0)
    {
      var killerNameText3 = document.createTextNode(' and '+(kill.numKillers-1)+' others');
      killerName.appendChild(killerNameText3);
    }
    linkWrapper.appendChild(killerName);
    
    var killerCorp = document.createElement('div');
    killerCorp.className = 'listing-killer-corp';
    if (kill.killerAllianceId)
      killerCorp.textContent = kill.killerCorporationName + ' / ' + kill.killerAllianceName;
    else
      killerCorp.textContent = kill.killerCorporationName;
    linkWrapper.appendChild(killerCorp);
    
    var killerShip = document.createElement('div');
    killerShip.className = 'listing-killer-ship';
    var killerShipText1 = document.createTextNode('using ');
    var killerShipText2 = document.createElement('span');
    killerShipText2.style.fontWeight = 'bold';
    killerShipText2.textContent = kill.mostCommonKillerShipName;
    killerShip.appendChild(killerShipText1);
    killerShip.appendChild(killerShipText2);
    if (kill.secondMostKillerShipId)
    {
      var killerShipText3 = document.createTextNode(' and ');
      var killerShipText4 = document.createElement('span');
      killerShipText4.style.fontWeight = 'bold';
      killerShipText4.textContent = kill.secondMostKillerShipName;
      killerShip.appendChild(killerShipText3);
      killerShip.appendChild(killerShipText4);
    }
    linkWrapper.appendChild(killerShip);
    
    entry.appendChild(linkWrapper);
    listingContainer.appendChild(entry);
    --renderCount;
  }
  
  if (renderCount > 0) // not enough data to render
  {
    // request more data
    var c = 'type='+listingContainer.dataset.type+'&id='+listingContainer.dataset.typeid;
    if (listingData.length)
      c += '&before='+listingData[listingData.length-1].killId;
    
    if (isFiltered())
      c += '&count='+((renderCount+skipCount)*2);
    else
      c += '&count='+(renderCount+skipCount);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST','api/listKills.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xhr.setRequestHeader('Content-Length',c.length);
		xhr.onload = listingLoadSuccess;
		xhr.onerror = listingLoadError;
		xhr.send(c);
  }
}


function removeFilters()
{
  if (currentListingFilter == LISTING_FILTER_NONE)
    return;
  currentListingFilter = LISTING_FILTER_NONE;
  this.parentElement.className = 'listing-selected-all';
  redrawKillListing();
}

function setFilterKills()
{
  if (currentListingFilter == LISTING_FILTER_KILLS)
    return;
  currentListingFilter = LISTING_FILTER_KILLS;
  this.parentElement.className = 'listing-selected-kills';
  redrawKillListing();
}

function setFilterLosses()
{
  if (currentListingFilter == LISTING_FILTER_LOSSES)
    return;
  currentListingFilter = LISTING_FILTER_LOSSES;
  this.parentElement.className = 'listing-selected-losses';
  redrawKillListing();
}

document.addEventListener('DOMContentLoaded', function()
{
  listingContainer = document.getElementById('listing-container');
  if (listingContainer.dataset.type && listingContainer.dataset.typeid)
    redrawKillListing();
  else
    listingContainer = null;
  
  var listingSelectors = document.getElementById('listing-selectors');
  if (listingSelectors)
  {
    var e = listingSelectors.firstElementChild;
    while (e)
    {
      switch (e.id)
      {
        case 'listing-selector-all':
          e.onclick = removeFilters;
          break;
        case 'listing-selector-kills':
          e.onclick = setFilterKills;
          break;
        case 'listing-selector-losses':
          e.onclick = setFilterLosses;
          break;
      }
      e = e.nextElementSibling;
    }
  }
});