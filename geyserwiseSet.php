<?php

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
	require_once "geyserwise.lib.php";

	$GW = new GeyserWise();
	$GW->logMsg(sprintf("Running action: %s with value: %s", $GW->settings['action'], $GW->settings['value']));

	switch ($GW->settings['action']) {
	case 'switchholiday' : $GW->SetSwitchHoliday($GW->settings['value']);
				break;
	case 'switchgeyser' : $GW->SetSwitchGeyser($GW->settings['value']);
				break;
	}

//	$GW->logMsg(json_encode($GW->action));

	echo json_encode($GW->action); exit;

?>
