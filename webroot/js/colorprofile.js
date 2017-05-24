function hexToRgb(hex) {
	var hexVal = parseInt(hex.substr(1),16);
	var rStr = ((hexVal >> 16) & 0xFF).toString(10);
	var gStr = ((hexVal >>  8) & 0xFF).toString(10);
	var bStr = ((hexVal >>  0) & 0xFF).toString(10);
	return String.prototype.concat('rgb(',rStr,', ',gStr,', ',bStr,')');
}

var profile_colors = [
	{ id: 'friendly1', defaultValue: '#193319', cssValue: '#193419' }, // general friendly success indicator
	{ id: 'friendly2', defaultValue: '#1d441d', cssValue: '#1d451d' }, // brighter friendly success indicator
  { id: 'friendly3', defaultValue: '#193319' }, // effectively the same as friendly1 (compatibility option)
  { id: 'friendly4', defaultValue: '#1d441d' }, // the same as friendly2 (as above)
	{ id: 'friendly5', defaultValue: '#2b802b' }, // very bright friendly success (text)
	{ id: 'friendly6', defaultValue: '#2bc02b' }, // saturated bright friendly success (text hover)
	{ id: 'friendly7', defaultValue: '#004000' }, // very dark friendly (final blow border)
	{ id: 'hostile1',  defaultValue: '#4c1919' }, // general hostile success indicator
	{ id: 'hostile2',  defaultValue: '#661d1d' }, // brighter hostile success indicator
	{ id: 'hostile3',  defaultValue: '#331919' }, // more muted hostile success indicator
	{ id: 'hostile4',  defaultValue: '#441d1d' }, // bright muted hostile success indicator
	{ id: 'hostile5',  defaultValue: '#902020' }, // very bright hostile success (text)
	{ id: 'hostile6',  defaultValue: '#d82020' }, // saturated bright hostile success (text hover)
	{ id: 'hostile7',  defaultValue: '#700000' }, // very dark hostile (ship loss border)
  { id: 'text1',     defaultValue: '#fbfbfb' }, // standard text color
  { id: 'text2',     defaultValue: '#c3c3c3' }, // muted text color
  { id: 'text3',     defaultValue: '#a2a2a2' }  // even more muted text color!
];
for (var i=0, len=profile_colors.length; i < len; ++i)
  if (!profile_colors[i].cssValue)
    profile_colors[i].cssValue = profile_colors[i].defaultValue;

function getProfileNumColors() { return profile_colors.length; }
function getProfileColorName(i) { return profile_colors[i].id; }
function getProfileColorIdForName(name)
{
  for (var i=0, len=profile_colors.length; i < len; ++i)
    if (profile_colors[i].id == name)
      return i;
  return null;
}
function getProfileColorDefault(i) { return profile_colors[i].defaultValue; }
function getCurrentProfileColor(i) { return (localStorage.getItem('colorprofile-'+getProfileColorName(i)) || getProfileColorDefault(i)); }

var has = {};
function updateColorProfile(initialLoad)
{
	if (typeof(Storage) === 'undefined')
		return;
	var props = [
		'background-color',
		'border-color',
		'fill',
		'stroke',
		'color'
	];
	if (initialLoad)
		for (var i=0, len = props.length; i < len; ++i)
			has[i] = {};
	var toChange = {};
	for (var i=0, len = profile_colors.length; i < len; ++i)
    toChange[i] = hexToRgb(getCurrentProfileColor(i));
	
	var setProfileColor = undefined;
  if (!document.styleSheets)
    return;
	for (var i=0, len = document.styleSheets.length; i < len; ++i)
	{
		var sheet = document.styleSheets[i];
    if (!sheet.cssRules)
      continue;
		for (var j=0, len2 = sheet.cssRules.length; j < len2; ++j)
		{
			var rule = sheet.cssRules[j];
			if (rule.type !== CSSRule.STYLE_RULE)
				continue;
			for (var k=0, len3 = props.length; k < len3; ++k)
			{
				if (initialLoad)
				{
					var val = rule.style.getPropertyValue(props[k]).toLowerCase();
					for (var m=0, len4 = profile_colors.length; m < len4; ++m)
						if (val == profile_colors[m].cssValue || val == hexToRgb(profile_colors[m].cssValue))
							has[k][rule.selectorText] = m;
				}
				if ((setProfileColor = has[k][rule.selectorText]) !== undefined)
					if (toChange[setProfileColor])
						rule.style.setProperty(props[k],toChange[setProfileColor])
			}
		}
	}
}

document.addEventListener('DOMContentLoaded', function()
{
	updateColorProfile(true);
});

function changeColor(id)
{
	for (var i=0, len = profile_colors.length; i < len; ++i)
		if (profile_colors[i].id == id)
		{
			var newColor = window.prompt('Please enter new RGB for \''+id+'\':\n(Default: '+profile_colors[i].defaultValue+')',getCurrentProfileColor(i));
			if (!newColor.match(/#[0-9a-fA-F]{6}/))
			{
				window.alert('Fail - no RGB value');
				return;
			}
			localStorage.setItem('colorprofile-'+id,newColor);
			updateColorProfile(false);
			return;
		}
	console.log('Fail - invalid ID');
}

function resetColor()
{
	for (var i=0, len = profile_colors.length; i < len; ++i)
		localStorage.removeItem('colorprofile-'+id);
	updateColorProfile(false);
}