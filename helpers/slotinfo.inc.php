<?php
	define('HIGHSLOT1',      1);
	define('HIGHSLOT2',      2);
	define('HIGHSLOT3',      3);
	define('HIGHSLOT4',      4);
	define('HIGHSLOT5',      5);
	define('HIGHSLOT6',      6);
	define('HIGHSLOT7',      7);
	define('HIGHSLOT8',      8);
	
	define('MIDSLOT1',       9);
	define('MIDSLOT2',      10);
	define('MIDSLOT3',      11);
	define('MIDSLOT4',      12);
	define('MIDSLOT5',      13);
	define('MIDSLOT6',      14);
	define('MIDSLOT7',      15);
	define('MIDSLOT8',      16);
	
	define('LOWSLOT1',      17);
	define('LOWSLOT2',      18);
	define('LOWSLOT3',      19);
	define('LOWSLOT4',      20);
	define('LOWSLOT5',      21);
	define('LOWSLOT6',      22);
	define('LOWSLOT7',      23);
	define('LOWSLOT8',      24);
	
	define('RIGSLOT1',      25);
	define('RIGSLOT2',      26);
	define('RIGSLOT3',      27);
	
	define('SUBSYSTEM1',    28);
	define('SUBSYSTEM2',    29);
	define('SUBSYSTEM3',    30);
	define('SUBSYSTEM4',    31);
	define('SUBSYSTEM5',    32);
	
	define('SERVICESLOT1',  33);
	define('SERVICESLOT2',  34);
	define('SERVICESLOT3',  35);
	define('SERVICESLOT4',  36);
	define('SERVICESLOT5',  37);
	define('SERVICESLOT6',  38);
	define('SERVICESLOT7',  39);
	define('SERVICESLOT8',  40);
	
	define('FIGHTERTUBE1',  50);
	define('FIGHTERTUBE2',  51);
	define('FIGHTERTUBE3',  52);
	define('FIGHTERTUBE4',  53);
	define('FIGHTERTUBE5',  54);
	
	define('IMPLANTSLOT',   70);

	define('DRONEHOLD',    100);
	define('FIGHTERHOLD',  101);
	define('CARGOHOLD',    102);
	define('SHIPHOLD',     103);
	define('FLEETHOLD',    104);
	define('FUELHOLD',     105);
	define('OREHOLD',      106);
  define('MINERALHOLD',  107);
  define('AMMOHOLD',     108);
  define('PIHOLD',       109);
  define('SUBSYSTEMHOLD',110);
  
  define('CONTAINER',    200);
	
	define('UNKNOWNSLOT',  255);
	
	// IN:  flag field from API killmail
	// OUT: slot id as used by the killfeed
	function convertCCPFlagToSlot($flag)
	{
		switch($flag)
		{
			// low slots
			case 11:
			case 12:
			case 13:
			case 14:
			case 15:
			case 16:
			case 17:
			case 18:
				return LOWSLOT1+($flag-11);
			// mid slots
			case 19:
			case 20:
			case 21:
			case 22:
			case 23:
			case 24:
			case 25:
			case 26:
				return MIDSLOT1+($flag-19);
			// high slots
			case 27:
			case 28:
			case 29:
			case 30:
			case 31:
			case 32:
			case 33:
			case 34:
				return HIGHSLOT1+($flag-27);
      // maybe only _secure_ containers? unsure, you see them so rarely
      // (pretty sure this will only ever appear with parent != 0)
      case 64:
        return CONTAINER;
			case 89:
				return IMPLANTSLOT;
			// rig slot (note: technically 95-99 are listed as additional "rig slots" in the game data)
			case 92:
			case 93:
			case 94:
				return RIGSLOT1+($flag-92);
			// subsystem slots
			case 125:
			case 126:
			case 127:
			case 128:
			case 129:
				return SUBSYSTEM1+($flag-125);
			// various ship cargo holds
			case 5:
				return CARGOHOLD;
			case 87:
				return DRONEHOLD;
			case 90:
				return SHIPHOLD;
			case 133:
				return FUELHOLD;
			case 134:
				return OREHOLD;
      case 136:
        return MINERALHOLD;
      case 143:
        return AMMOHOLD;
			case 149:
				return PIHOLD;
			case 155:
				return FLEETHOLD;
			case 158:
				return FIGHTERHOLD;
			case 159:
			case 160:
			case 161:
			case 162:
			case 163:
				return FIGHTERTUBE1+($flag-159);
			case 164:
			case 165:
			case 166:
			case 167:
			case 168:
			case 169:
			case 170:
			case 171:
				return SERVICESLOT1+($flag-164);
      case 177:
        return SUBSYSTEMHOLD;
			default:
				return UNKNOWNSLOT;
		}
	}
	
	function isSlotFittingSlot($slot)
	{
		return ($slot >= HIGHSLOT1) && ($slot <= SUBSYSTEM5);
	}
	
	// Returns the first slot in the category, if the slot belongs to a category
	function getSlotCategory($slot)
	{
		switch ($slot)
		{
			case HIGHSLOT1:
			case HIGHSLOT2:
			case HIGHSLOT3:
			case HIGHSLOT4:
			case HIGHSLOT5:
			case HIGHSLOT6:
			case HIGHSLOT7:
			case HIGHSLOT8:
				return HIGHSLOT1;
			case MIDSLOT1:
			case MIDSLOT2:
			case MIDSLOT3:
			case MIDSLOT4:
			case MIDSLOT5:
			case MIDSLOT6:
			case MIDSLOT7:
			case MIDSLOT8:
				return MIDSLOT1;
			case LOWSLOT1:
			case LOWSLOT2:
			case LOWSLOT3:
			case LOWSLOT4:
			case LOWSLOT5:
			case LOWSLOT6:
			case LOWSLOT7:
			case LOWSLOT8:
				return LOWSLOT1;
			CASE RIGSLOT1:
			case RIGSLOT2:
			case RIGSLOT3:
				return RIGSLOT1;
			case SUBSYSTEM1:
			case SUBSYSTEM2:
			case SUBSYSTEM3:
			case SUBSYSTEM4:
			case SUBSYSTEM5:
				return SUBSYSTEM1;
			case FIGHTERTUBE1:
			case FIGHTERTUBE2:
			case FIGHTERTUBE3:
			case FIGHTERTUBE4:
			case FIGHTERTUBE5:
				return FIGHTERTUBE1;
			case SERVICESLOT1:
			case SERVICESLOT2:
			case SERVICESLOT3:
			case SERVICESLOT4:
			case SERVICESLOT5:
			case SERVICESLOT6:
			case SERVICESLOT7:
			case SERVICESLOT8:
				return SERVICESLOT1;
			default:
				return $slot;
		}
	}
	
	function stringifySlot($slot, $plural=false)
	{
		switch ($slot)
		{
			case HIGHSLOT1:
			case HIGHSLOT2:
			case HIGHSLOT3:
			case HIGHSLOT4:
			case HIGHSLOT5:
			case HIGHSLOT6:
			case HIGHSLOT7:
			case HIGHSLOT8:
				return $plural ? 'High slots' : 'High slot';
			case MIDSLOT1:
			case MIDSLOT2:
			case MIDSLOT3:
			case MIDSLOT4:
			case MIDSLOT5:
			case MIDSLOT6:
			case MIDSLOT7:
			case MIDSLOT8:
				return $plural ? 'Mid slots' : 'Mid slot';
			case LOWSLOT1:
			case LOWSLOT2:
			case LOWSLOT3:
			case LOWSLOT4:
			case LOWSLOT5:
			case LOWSLOT6:
			case LOWSLOT7:
			case LOWSLOT8:
				return $plural ? 'Low slots' : 'Low slot';
			CASE RIGSLOT1:
			case RIGSLOT2:
			case RIGSLOT3:
				return $plural ? 'Rig slots' : 'Rig slot';
			case SUBSYSTEM1:
			case SUBSYSTEM2:
			case SUBSYSTEM3:
			case SUBSYSTEM4:
			case SUBSYSTEM5:
				return $plural ? 'Subsystems' : 'Subsystem';
			case FIGHTERTUBE1:
			case FIGHTERTUBE2:
			case FIGHTERTUBE3:
			case FIGHTERTUBE4:
			case FIGHTERTUBE5:
				return $plural ? 'Launch tubes' : 'Launch tube';
			case SERVICESLOT1:
			case SERVICESLOT2:
			case SERVICESLOT3:
			case SERVICESLOT4:
			case SERVICESLOT5:
			case SERVICESLOT6:
			case SERVICESLOT7:
			case SERVICESLOT8:
				return $plural ? 'Structure services' : 'Structure service';
      case CONTAINER:
        return 'Cargo container';
			case IMPLANTSLOT:
				return $plural ? 'Implants & Hardwirings' : 'Implant';
			case DRONEHOLD:
				return 'Drone bay';
			case FIGHTERHOLD:
				return 'Fighter bay';
			case CARGOHOLD:
				return 'Cargo hold';
      case MINERALHOLD:
        return 'Mineral hold';
      case AMMOHOLD:
        return 'Ammo hold';
      case PIHOLD:
        return 'Planetary commodity hold';
			case FLEETHOLD:
				return 'Fleet hangar';
			case SHIPHOLD:
				return 'Ship Maintenance Bay';
			case FUELHOLD:
				return 'Fuel bay';
			case OREHOLD:
				return 'Ore hold';
      case SUBSYSTEMHOLD:
        return 'Subsystem hold';
			case UNKNOWNSLOT:
			default:
				return 'Unknown location';
		}
	}
	
	function isShipLogistics($typeId)
	{
		switch ($typeId)
		{
			case 625: // Augoror
			case 634: // Exequror
			case 620: // Osprey
			case 631: // Scythe
			case 11987: // Guardian
			case 11989: // Oneiros
			case 11985: // Basilisk
			case 11978: // Scimitar
			case 37604: // Apostle
			case 37607: // Ninazu
			case 37605: // Minokawa
			case 37606: // Lif
			case 590: // Inquisitor
			case 592: // Navitas
			case 582: // Bantam
			case 599: // Burst
			case 37457: // Deacon
			case 37459: // Thalia
			case 37458: // Kirin
			case 37460: // Scalpel
			case 32790: // Etana
			case 33472: // Nestor
				return true;
			default:
				return false;
		}
	}
	
	function isShipTrivial($typeId)
	{
		switch ($typeId)
		{
			case 670: // Capsule
			case 33328: // Capsule - Genolution 'Auroral' 197-variant
				return true;
			default:
				return false;
		}
	}
?>