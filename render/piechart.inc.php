<?php
	require_once(__DIR__.'/../helpers/format.inc.php');
	function piechart($style, $leftValue, $leftLabel, $rightValue, $rightLabel, $leftValueText = NULL, $rightValueText = NULL)
	{
		$leftValue = +$leftValue;
		$rightValue = +$rightValue;
		if ($leftValue+$rightValue)
			$leftFraction = $leftValue/($leftValue+$rightValue);
		else
			$leftFraction = 0.5;
		if (!$leftValueText)
			$leftValueText = number_format(constrain($leftValue,2));
		if (!$rightValueText)
			$rightValueText = number_format(constrain($rightValue,2));
		$textLen = max(strlen(html_entity_decode($leftValueText,ENT_COMPAT|ENT_HTML5,'UTF-8')),strlen(html_entity_decode($rightValueText,ENT_COMPAT|ENT_HTML5,'UTF-8')),3);
		echo '<svg class="pie ',htmlspecialchars($style),'" viewBox="0 0 160 64">';
			echo '<g class="left">';
				echo '<text class="value noselect" x="',69+7*$textLen,'" y="15">',htmlentities($leftValueText, ENT_COMPAT | ENT_HTML5, 'UTF-8', false),'</text>';
				echo '<text class="label noselect" x="',72+7*$textLen,'" y="15">',htmlentities($leftLabel, ENT_COMPAT | ENT_HTML5, 'UTF-8', false),'</text>';
				echo '<circle r="32" cx="32" cy="32" />';
			echo '</g>';
			echo '<g class="right">';
				// strokes always start painting on the right - we need to do some fancy handling for this
				if ($leftFraction > 0.75)
				{ // our pattern is 270° space, then single stroke
					$spacing = 75.39822; // 270° arc on circle of radius 16
					$stroke = 100.530964915*(1-$leftFraction);
					$offset = $stroke;
				}
				else
				{ // our pattern is (right-25%) stroke, left gap, 25% stroke
				  // stroke length is always circle circumference, we use gap and offset to adjust this
					$stroke = 100.6;
					$spacing = $stroke*$leftFraction;
					$offset = (0.25+$leftFraction)*$stroke;
				}
				echo '<circle r="16" cx="32" cy="32" style="stroke-dasharray: ',$stroke,' ',$spacing,'; stroke-dashoffset: ',$offset,';" />';
				echo '<text class="value noselect" x="',69+7*$textLen,'" y="27">',htmlentities($rightValueText, ENT_COMPAT | ENT_HTML5, 'UTF-8', false),'</text>';
				echo '<text class="label noselect" x="',72+7*$textLen,'" y="27">',htmlentities($rightLabel, ENT_COMPAT | ENT_HTML5, 'UTF-8', false),'</text>';
			echo '</g>';
		echo '</svg>';
	}
?>