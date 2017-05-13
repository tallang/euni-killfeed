<?php
  require(__DIR__.'/../render/setup.inc.php');
	// @todo remove this from production
	set_time_limit(0);
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8"/>
    <title>E-UNI Killfeed - Accessibility</title>
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/killfeed.css" />
    <link rel="stylesheet" href="css/listing.css" />
    <link rel="stylesheet" href="css/accessibility.css" />
    <script src="js/colorprofile.js"></script>
		<script src="js/navbar.js"></script>
    <script src="js/accessibility.js"></script>
    <meta name="viewport" content="width=1000, initial-scale=1" />
  </head>
  <body>
    <?php include(__DIR__.'/../render/navbar.inc.php'); ?>
    <div id="content" class="noselect">
      <div id="colorprofile" class="panel">
        <div id="colorprofile-header">Adjust color profile</div>
        <div id="colorprofile-description">This allows you to adjust the color scheme used across the entire feed. Choose one of the pre-set color schemes below, or use the "custom" option to create a color scheme to suit your personal needs or tastes.</div>
        <div id="colorprofile-example">
          <div id="colorprofile-example-label-wrapper"><div id="colorprofile-example-label">Here's how it looks in practice:</div></div>
          <div id="colorprofile-example-wrapper">
            <div class="listing-entry loss"><a>
              <div class="listing-kill-time">21:27</div>
              <div class="listing-kill-value">5.05b</div>
              <div class="listing-victim-avatar"><img class="fill" src="http://image.eveonline.com/Character/92860226_64.jpg" /></div>
              <div class="listing-victim-icon"><img class="fill" src="http://image.eveonline.com/Type/37607_64.png" /></div>
              <div class="listing-victim-name">Titus Tallang</div>
              <div class="listing-victim-corp">EVE University&nbsp;/&nbsp;Ivy League</div>
              <div class="listing-victim-ship">Ninazu / J211936 (<span style="color: #F30202">-1.0</span>) </div>
              <div class="listing-killer-icon1"><img class="fill" src="http://image.eveonline.com/Type/29988_64.png" /></div>
              <div class="listing-killer-icon2"><img class="fill" src="http://image.eveonline.com/Type/17738_64.png" /></div>				<div class="listing-killer-name">by <span style="font-weight: bold;">Scruffy Ormand</span> and 81 others</div>
              <div class="listing-killer-corp">HotzenPlotzGang&nbsp;/&nbsp;Public-Enemy</div>
              <div class="listing-killer-ship">using <span style="font-weight: bold;">Proteus</span> and <span style="font-weight: bold;">Machariel</span></div>
            </a></div>
            <div class="listing-entry kill"><a>
              <div class="listing-kill-time">09:08</div>
              <div class="listing-kill-value">3.93b</div>
              <div class="listing-victim-avatar"><img class="fill" src="http://image.eveonline.com/Character/448903436_64.jpg" /></div>
              <div class="listing-victim-icon"><img class="fill" src="http://image.eveonline.com/Type/670_64.png" /></div>
              <div class="listing-victim-name">Frood Frooster</div>
              <div class="listing-victim-corp">EVE University&nbsp;/&nbsp;Ivy League</div>
              <div class="listing-victim-ship">Capsule / Pator (<span style="color: #33F9F9">1.0</span>) </div>
              <div class="listing-killer-icon1 offset"><img class="fill" src="http://image.eveonline.com/Type/11400_64.png" /></div>
              <div class="listing-killer-name">by <span style="font-weight: bold;">Curacao Gold</span></div>
              <div class="listing-killer-corp">Let Us Sleep&nbsp;/&nbsp;Whores in space</div>
              <div class="listing-killer-ship">using <span style="font-weight: bold;">Jaguar</span></div>
            </a></div>
          </div>
          <div id="colorprofile-example-info-wrapper"><div id="colorprofile-example-info">Change the colors below and watch this update in real time!</div></div>
        </div>
        <div id="colorprofile-selectors"><?php
        function makeColorProfileBlock($friendly1,$friendly2,$friendly3,$friendly4,$friendly5,$friendly6,$friendly7,$hostile1,$hostile2,$hostile3,$hostile4,$hostile5,$hostile6,$hostile7)
        { ?>
          <div class="colorprofile-selector" data-friendly1="<?=$friendly1?>"
            data-friendly2="<?=$friendly2?>" data-friendly3="<?=$friendly3?>"
            data-friendly4="<?=$friendly4?>" data-friendly5="<?=$friendly5?>"
            data-friendly6="<?=$friendly6?>" data-friendly7="<?=$friendly7?>"
            data-hostile1="<?=$hostile1?>" data-hostile2="<?=$hostile2?>"
            data-hostile3="<?=$hostile3?>" data-hostile4="<?=$hostile4?>"
            data-hostile5="<?=$hostile5?>" data-hostile6="<?=$hostile6?>"
            data-hostile7="<?=$hostile7?>">
            <div class="colorprofile-select"><!--SELECT--></div>
            <div class="colorprofile-hostile-label" style="color: <?=$hostile6?>;">ENEMIES:</div>
            <div class="colorprofile-hostile1" style="background: <?=$hostile1?>;"></div>
            <div class="colorprofile-hostile2" style="background: <?=$hostile2?>;"></div>
            <div class="colorprofile-hostile3" style="background: <?=$hostile3?>;"></div>
            <div class="colorprofile-hostile4" style="background: <?=$hostile4?>;"></div>
            <div class="colorprofile-hostile5" style="background: <?=$hostile5?>;"></div>
            <div class="colorprofile-hostile6" style="background: <?=$hostile6?>;"></div>
            <div class="colorprofile-hostile7" style="background: <?=$hostile7?>;"></div>
            <div class="colorprofile-friendly-label" style="color: <?=$friendly6?>;">FRIENDS:</div>
            <div class="colorprofile-friendly1" style="background: <?=$friendly1?>;"></div>
            <div class="colorprofile-friendly2" style="background: <?=$friendly2?>;"></div>
            <div class="colorprofile-friendly3" style="background: <?=$friendly3?>;"></div>
            <div class="colorprofile-friendly4" style="background: <?=$friendly4?>;"></div>
            <div class="colorprofile-friendly5" style="background: <?=$friendly5?>;"></div>
            <div class="colorprofile-friendly6" style="background: <?=$friendly6?>;"></div>
            <div class="colorprofile-friendly7" style="background: <?=$friendly7?>;"></div>
          </div><?php
        }
        makeColorProfileBlock('#193319','#1d441d','#193319','#1d441d','#2b802b','#2bc02b','#004000','#4c1919','#661d1d','#331919','#441d1d','#902020','#d82020','#700000');
        makeColorProfileBlock('#193333','#1d4444','#193333','#1d4444','#2b8080','#2bc0c0','#004040','#4c4c19','#66661d','#333319','#44441d','#909020','#d8d820','#707000');
        ?>
          <div class="colorprofile-selector" id="colorprofile-custom">
            <div class="colorprofile-select"><!--USE CUSTOM--></div>
            <div class="colorprofile-hostile-label" style="color: <?=$hostile6?>;">ENEMIES:</div>
            <div class="colorprofile-hostile1" data-id="hostile1"><input type="color" class="fill" value="#777777" autocomplete="off" /></div>
            <div class="colorprofile-hostile2" data-id="hostile2"><input type="color" class="fill" value="#888888" autocomplete="off" /></div>
            <div class="colorprofile-hostile3" data-id="hostile3"><input type="color" class="fill" value="#555555" autocomplete="off" /></div>
            <div class="colorprofile-hostile4" data-id="hostile4"><input type="color" class="fill" value="#666666" autocomplete="off" /></div>
            <div class="colorprofile-hostile5" data-id="hostile5"><input type="color" class="fill" value="#999999" autocomplete="off" /></div>
            <div class="colorprofile-hostile6" data-id="hostile6"><input type="color" class="fill" value="#aaaaaa" autocomplete="off" /></div>
            <div class="colorprofile-hostile7" data-id="hostile7"><input type="color" class="fill" value="#444444" autocomplete="off" /></div>
            <div class="colorprofile-friendly-label">FRIENDS:</div>
            <div class="colorprofile-friendly1" data-id="friendly1"><input type="color" class="fill" value="#666666" autocomplete="off" /></div>
            <div class="colorprofile-friendly2" data-id="friendly2"><input type="color" class="fill" value="#787878" autocomplete="off" /></div>
            <div class="colorprofile-friendly3" data-id="friendly3"><input type="color" class="fill" value="#424242" autocomplete="off" /></div>
            <div class="colorprofile-friendly4" data-id="friendly4"><input type="color" class="fill" value="#545454" autocomplete="off" /></div>
            <div class="colorprofile-friendly5" data-id="friendly5"><input type="color" class="fill" value="#8a8a8a" autocomplete="off" /></div>
            <div class="colorprofile-friendly6" data-id="friendly6"><input type="color" class="fill" value="#9c9c9c" autocomplete="off" /></div>
            <div class="colorprofile-friendly7" data-id="friendly7"><input type="color" class="fill" value="#303030" autocomplete="off" /></div>
        </div>
      </div>
    </div>
  </body>
</html><?php render: require(__DIR__.'/../render/doRender.inc.php'); ?>