var selectedCPSelector = null;

var selectProfile = function()
{
  if (this.parentElement.classList.contains('selected'))
    return;
  for (var i=0; i<getProfileNumColors(); ++i)
  {
    var name = getProfileColorName(i);
    var val = this.parentElement.dataset[name];
    if (val)
      localStorage.setItem('colorprofile-'+name,val);
  }
  updateColorProfile(false);
};

var old_updateColorProfile = updateColorProfile;
updateColorProfile = function(initialLoad)
{
  var foundMatch = false;
  old_updateColorProfile(initialLoad);
  var customSelector = document.getElementById('colorprofile-custom');
  var selectors = document.getElementsByClassName('colorprofile-selector');
  for (var i=0; i<selectors.length; ++i)
  {
    if (initialLoad && selectors[i] == customSelector)
      continue;
    var isMatch = true;
    for (var j=0; j<getProfileNumColors(); ++j)
      if (selectors[i].dataset[getProfileColorName(j)] &&
        (getCurrentProfileColor(j) != selectors[i].dataset[getProfileColorName(j)]))
      {
        isMatch = false;
        break;
      }
    if (isMatch)
    {
      if (selectedCPSelector)
        selectedCPSelector.classList.remove('selected');
      selectors[i].classList.add('selected');
      selectedCPSelector = selectors[i];
      foundMatch = true;
      break;
    }
  }

  var hostileTextColor, hostileTextElement;
  var friendlyTextColor, friendlyTextElement;
  for (var i=0; i<customSelector.children.length; ++i)
  {
    var e = customSelector.children[i];
    if (e.classList.contains('colorprofile-hostile-label'))
      hostileTextElement = e;
    else if (e.classList.contains('colorprofile-friendly-label'))
      friendlyTextElement = e;
    else if (e.dataset.id)
    {
      if (initialLoad && !foundMatch)
        e.firstElementChild.value = getCurrentProfileColor(getProfileColorIdForName(e.dataset.id));
      if (e.dataset.id == 'hostile6')
        hostileTextColor = e.firstElementChild.value;
      else if (e.dataset.id == 'friendly4')
        friendlyTextColor = e.firstElementChild.value;
      customSelector.dataset[e.dataset.id] = e.firstElementChild.value;
    }
  }
  hostileTextElement.style.color = hostileTextColor;
  friendlyTextElement.style.color = friendlyTextColor;
  if (!foundMatch)
  {
    if (selectedCPSelector)
      selectedCPSelector.classList.remove('selected');
    customSelector.classList.add('selected');
    selectedCPSelector = customSelector;
  }
};

var onCustomProfileChanged = function()
{
  var id = this.parentElement.dataset.id;
  this.parentElement.parentElement.dataset[id] = this.value;
  
  var updateTextClass = null;
  if (id == 'hostile6')
    updateTextClass = 'colorprofile-hostile-label';
  else if (id == 'friendly4')
    updateTextClass = 'colorprofile-friendly-label';
  if (updateTextClass)
  {
    var e = this.parentElement;
    while (e = e.previousElementSibling)
      if (e.classList.contains(updateTextClass))
        e.style.color = this.value;
  }
  
  if (this.parentElement.parentElement.classList.contains('selected'))
  {
    localStorage.setItem('colorprofile-'+id, this.value);
    updateColorProfile(false);
  }
};
document.addEventListener('DOMContentLoaded', function()
{
  var selectors = document.getElementsByClassName('colorprofile-selector');
  for (var i=0; i<selectors.length; ++i)
  {
    var e = selectors[i];
    e.firstElementChild.onclick = selectProfile;
    if (e.id == 'colorprofile-custom')
      for (var j=0, len=e.children.length; j < len; ++j)
        if (e.children[j].dataset.id)
          e.children[j].firstElementChild.onchange = onCustomProfileChanged;
  }
});