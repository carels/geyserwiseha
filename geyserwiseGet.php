#!/usr/bin/php
<?php

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
	require_once "geyserwise.lib.php";

	$GW = new GeyserWise();

	$GW->geyserStats();

	$GW->getGeyserSettings();

	$GW->output();
exit;



?>
