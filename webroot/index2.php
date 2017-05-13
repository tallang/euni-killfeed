<!DOCTYPE html> 
<html><head><meta charset="utf-8"/><title>New Killfeed Pre-Alpha Feature Index</title></head><body>
	<h1>Quick feature index:</h1>
	<div id="issues" style="border: 2px solid #000000; padding: 0 15px; margin: 5px 0;">
		<h2>Known issues list:</h2>
		<ul>
			<li>Nested items (items in containers/ships in cargo) may be missing from mails sourced from XML API. Kills sourced from CREST are unaffected.</li>
			<li>Older kills are not fetched - this is a limitation of the XML API. If you want to see them, post them using CREST - you can get the link from zKillboard.</li>
			<li>The killfeed will not display properly if the browser window less than 1400px in width and your browser doesn't respect the viewport meta tag (most non-mobile browsers don't respect this).</li>
		</ul>
	</div>
	<div id="postCREST" style="border: 2px solid #000000; padding: 0 15px; margin: 5px 0;">
		<h2>Post CREST link:</h2>
		<form action="parseCrest.php" method="POST">
			<input type="text" name="url" /><br />
			<input type="submit" value="Parse data" />
		</form>
	</div>
	<div id="viewKill" style="border: 2px solid #000000; padding: 0 15px; margin: 5px 0;">
		<h2>View existing kill by CREST ID (same as zKill URL):</h2>
		<form action="kill.php" method="GET">
			<input type="number" name="killID" /><br />
			<input type="submit" value="View" />
		</form>
	</div>
	<div id="viewCharacter" style="border: 2px solid #000000; padding: 0 15px; margin: 5px 0;">
		<h2>View character by charID:</h2>
		<form action="character.php" method="GET">
			<input type="number" name="characterID" /><br />
			<input type="submit" value="View" />
		</form>
	</div>
</body></html>