<?php

	ini_set('register_argc_argv', 1);
	require_once "support/web_browser.php";
	require_once "support/tag_filter.php";

class GeyserWise {

	function __construct() {
		global $argv;
		$this->settings = array();

		$request_body = file_get_contents('php://input');
		$payload = json_decode($request_body);
		if (count($payload)) { 		// If we received a json payload, use that
			$this->settings['txtUserName'] = $payload->user;
			$this->settings['txtPassword'] = $payload->pass;
			$this->settings['ddUnit'] = sprintf("%d", $payload->unit);

			if ($payload->action) $this->settings['action'] = $payload->action;
			if ($payload->value) $this->settings['value'] = $payload->value;

		} elseif (count($argv) >= 3) {	// If we're running from the command line, use that
			$this->settings['txtUserName'] = $argv[1];
			$this->settings['txtPassword'] = $argv[2];
			$this->settings['ddUnit'] = sprintf("%d", $argv[3]);
		} else {		// Otherwise default to a GET / POST request
			$this->settings['txtUserName'] = $_REQUEST['user'];
			$this->settings['txtPassword'] = $_REQUEST['pass'];
			$this->settings['ddUnit'] = sprintf("%d", $_REQUEST['unit']);
			if ($_REQUEST['action']) $this->settings['action'] = $_REQUEST['action'];
			if ($_REQUEST['value']) $this->settings['value'] = $_REQUEST['value'];
		}

		if ( (! $this->settings['txtUserName']) || (! $this->settings['txtPassword']) || (! $this->settings['ddUnit']) ) {
			die ("Missing parameters");
		}
	}

	function logMsg($msg) {
		$fh = fopen("../logs/geyserwise.log", "a");
		fwrite($fh, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg));
		fclose($fh);
	}

	function geyserStats() {
		global $settings;
		// Retrieve the standard HTML parsing array for later use.
		$htmloptions = TagFilter::GetHTMLOptions();

		// Retrieve a URL (emulating Firefox by default).
		$url = "https://geyserwiseonline.com/RemoteManager/PostingService/Dashboard.ashx"; 
		$web = new WebBrowser();
		$options = array(
			"postvars" => array(
				"value" => $this->settings['ddUnit']
			)
		);

		$result = $web->Process($url, $options);
		$this->checkResult($result);

		preg_match('/^(\d+).*,(\d{4}-\d{2}-\d{2}).*;(\d{2}:\d{2}),(true|false),(true|false),(true|false)/', $result['body'], $matches);

		$this->geyserStats = array(
				'geysertemp' => $matches[1],
				'lastupdate' => sprintf("%s %s", $matches[2], $matches[3]),
				'lastupdateepoch' => strtotime(sprintf("%s %s UTC", $matches[2], $matches[3])),
				'geyserelement' => ($matches[4] == 'true') ? 'on' : 'off'
			);
	}

	function checkResult($result) {
		if (!$result["success"]) {
			echo "Error retrieving URL.  " . $result["error"] . "\n";
			exit();
		}

		if ($result["response"]["code"] != 200) {
			echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
			exit();
		}

	}

	function isWeekend() {
		$dow = date('w');

		switch ($dow) {
		case 0 :
		case 6 : return true;
			 break;
		default: return false;
		}
	}

	function shouldGeyserBeOn($stats) {

		$thisTime = date('H:i');

		$j=0;
		$times = array();
		for ($i=1; $i<=4; $i++) {
			if ($stats[sprintf('txtTimer%dOnHours', $i)] != '') {
				$times[$j]['on'] = sprintf("%02d:%02d", $stats[sprintf('txtTimer%dOnHours', $i)], $stats[sprintf('txtTimer%dOnMinutes', $i)]);
			}
			if ($stats[sprintf('txtTimer%dOffHours', $i)] != '') {
				$times[$j]['off'] = sprintf("%02d:%02d", $stats[sprintf('txtTimer%dOffHours', $i)], $stats[sprintf('txtTimer%dOffMinutes', $i)]);
				$j++;
			}
		}
		$this->geyserStats['timers'] = $times;
		if ($stats['switchholiday'] == 'on') return false;	// If in holiday mode we won't actually check whether we're supposed to be on or off
		foreach ($times as $t) {
			if (($thisTime > $t['on']) && ($thisTime < $t['off'])) {
				return true;
			}
		}
		return false;
		
	}

	function doLogin() {
		// Retrieve a URL (emulating Firefox by default).
		$url = "https://geyserwiseonline.com/RemoteManager/Login.aspx";
		$this->web = new WebBrowser(array("extractforms" => true));

		// Load the login form
		$result = $this->web->Process($url, $options);
		$this->checkResult($result);

		// Set login form data
		$form = $result["forms"][0];
		$form->SetFormValue("txtUserName", $this->settings['txtUserName']);
		$form->SetFormValue("txtPassword", $this->settings['txtPassword']);
		$result2 = $form->GenerateFormRequest();
		$result = $this->web->Process($result2["url"], $result2["options"]);
		$this->checkResult($result);
	}

	function getGeyserSettings() {
		// Retrieve the standard HTML parsing array for later use.
		$htmloptions = TagFilter::GetHTMLOptions();

		$this->doLogin();

		// Loged in, now let's go to the dashboard
		$url = "https://geyserwiseonline.com/RemoteManager/dashboard.aspx";
		$result = $this->web->Process($url, $options);

		// Now load the geyser data
		$form = $result["forms"][0];
		$form->SetFormValue("ddUnit", $this->settings['ddUnit']);	// Enter Geyser Unit number
		if ($this->isWeekend()) {
			$form->SetFormValue("optradio", 'rbWeekend');	// If it is weekend, get the weekend settings
		}
		
		$result2 = $form->GenerateFormRequest('btnSelectUnit','LOAD');
		$result = $this->web->Process($result2["url"], $result2["options"]);
		$this->checkResult($result);

		// Geyser unit's data should be loaded now, lets check stats
		$rawstats = $result["forms"][0];

		$stats = array(
			'optradio' => $rawstats->GetFormValue('optradio'),
			'txtTimer1OnHours' => $rawstats->GetFormValue('txtTimer1OnHours'),
			'txtTimer1OffHours' => $rawstats->GetFormValue('txtTimer1OffHours'),
			'txtTimer1OnMinutes' => $rawstats->GetFormValue('txtTimer1OnMinutes'),
			'txtTimer1OffMinutes' => $rawstats->GetFormValue('txtTimer1OffMinutes'),
			'txtTimer2OnHours' => $rawstats->GetFormValue('txtTimer2OnHours'),
			'txtTimer2OffHours' => $rawstats->GetFormValue('txtTimer2OffHours'),
			'txtTimer2OnMinutes' => $rawstats->GetFormValue('txtTimer2OnMinutes'),
			'txtTimer2OffMinutes' => $rawstats->GetFormValue('txtTimer2OffMinutes'),
			'txtTimer3OnHours' => $rawstats->GetFormValue('txtTimer3OnHours'),
			'txtTimer3OffHours' => $rawstats->GetFormValue('txtTimer3OffHours'),
			'txtTimer3OnMinutes' => $rawstats->GetFormValue('txtTimer3OnMinutes'),
			'txtTimer3OffMinutes' => $rawstats->GetFormValue('txtTimer3OffMinutes'),
			'txtTimer4OnHours' => $rawstats->GetFormValue('txtTimer4OnHours'),
			'txtTimer4OffHours' => $rawstats->GetFormValue('txtTimer4OffHours'),
			'txtTimer4OnMinutes' => $rawstats->GetFormValue('txtTimer4OnMinutes'),
			'txtTimer4OffMinutes' => $rawstats->GetFormValue('txtTimer4OffMinutes'),
			'ex17a' => $rawstats->GetFormValue('ex17a'),
			'ex17b' => $rawstats->GetFormValue('ex17b'),
			'ex17c' => $rawstats->GetFormValue('ex17c'),
			'ex17d' => $rawstats->GetFormValue('ex17d'),
			'switchgeyser' => $rawstats->GetFormValue('switchgeyser'),
			'switchholiday' => $rawstats->GetFormValue('switchholiday'),
			'geysertemp' => $this->geyserStats['geysertemp']
		);

		
		$this->geyserStats['manualOn'] = ($rawstats->GetFormValue('switchgeyser')) ? 'on' : 'off';
		$this->geyserStats['holidayMode'] = ($rawstats->GetFormValue('switchholiday')) ? 'on' : 'off';
		
		if ($this->shouldGeyserBeOn($stats)) {
			$this->geyserStats['geyserExpectedStatus'] = 'on';
		} else {
			$this->geyserStats['geyserExpectedStatus'] = 'off';
		}

	}

	function setGeyserWise($setting, $checked = array()) {
		// Loged in, now let's go to the dashboard

		$url = "https://geyserwiseonline.com/RemoteManager/dashboard.aspx";
		$result = $this->web->Process($url, $options);

		// Now set the geyser data
		$form = $result["forms"][0];
		$form->SetFormValue("ddUnit", $this->settings['ddUnit']);	// Enter Geyser Unit number
		// Some more hard coding, pain to pull the values of this, so we need to set it hard coded
		$setting['ex17a'] = 65;
		$setting['ex17b'] = 60;
		$setting['ex17c'] = 60;
		$setting['ex17d'] = 65;

		foreach ($setting as $k => $v) {
			$form->SetFormValue($k, $v, $checked[$k]);
		}

		$result2 = $form->GenerateFormRequest('btnSetAll','SET');
		$result = $this->web->Process($result2["url"], $result2["options"]);
		$this->checkResult($result);
//print_r($result);

	}

	function SetSwitchHoliday($mode) {

		$this->getGeyserSettings();

		$this->action = array(
			'action' => 'switchholiday',
			'value' => $mode,
			'result' => 'IGNORE'		// Assume we ignored this request unless stated otherwise
		);
		switch ($mode) {

		case 'on' : 	if ($this->geyserStats['holidayMode'] != 'on') {
					$this->setGeyserWise(array('switchholiday' => 'on'), array('switchholiday' => 1));
					$this->action['result'] = 'OK';
				}
				break;
		case 'off' : 	if ($this->geyserStats['holidayMode'] == 'on') {
					$this->setGeyserWise(array('switchholiday' => 'on'));	// We're saying value=on, but not checked. This is OK
					$this->action['result'] = 'OK';
				}
				break;
		}

	}

	function SetSwitchGeyser($mode) {

		$this->getGeyserSettings();

		$this->action = array(
			'action' => 'switchgeyser',
			'value' => $mode,
			'result' => 'IGNORE'		// Assume we ignored this request unless stated otherwise
		);
		switch ($mode) {

		case 'on' : 	if ($this->geyserStats['manualOn'] != 'on') {
					$this->setGeyserWise(array('switchgeyser' => 'on'), array('switchgeyser' => 1));
					$this->action['result'] = 'OK';
				}
				break;
		case 'off' : 	if ($this->geyserStats['manualOn'] == 'on') {
					$this->setGeyserWise(array('switchgeyser' => 'on'));	// We're saying value=on, but not checked. This is OK
					$this->action['result'] = 'OK';
				}
				break;
		}

	}

	function output() {
		$this->logMsg(json_encode($this->geyserStats));
		echo json_encode($this->geyserStats); exit;
	}


}
?>
