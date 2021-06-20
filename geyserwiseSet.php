#!/usr/bin/php
<?php

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
	require_once "geyserwise.lib.php";

	$GW = new GeyserWise();

	switch ($_REQUEST['action']) {
	case 'switchholiday' : $GW->SetSwitchHoliday($_REQUEST['value']);
				break;
	case 'switchgeyser' : $GW->SetSwitchGeyser($_REQUEST['value']);
				break;
	}


	echo json_encode($GW->action); exit;
exit;



?>
