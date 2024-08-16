<?php
/*

Website Monitor
===============

Hello! This is the monitor script, which does the actual monitoring of websites
stored in monitors.json.

You can run this manually, but it’s probably better if you use a cron job.
Here’s an example of a crontab entry that will run it every minute:

* * * * * /usr/bin/php -f /path/to/monitor.php >/dev/null 2>&1

*/

include('configuration.php');

$monitors = json_decode(file_get_contents(PATH.'/monitors.json'));


// clear folder incidents
$files = glob(PATH.'/incidents/*');
foreach($files as $file) {
	if(is_file($file)) {
		unlink($file);
	}
}

foreach($monitors as $name => $url) {
	$response_data = array();
	$timestamp = time();
	$response_data[$timestamp]['timestamp'] = $timestamp;
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	$response = curl_exec($curl);
	if(curl_exec($curl) === false) {
		$response_data[$timestamp]['error'] = curl_error($curl);

		// create file in folder incidents
		$filename = PATH.'/incidents/'.'alert_'.date('Y-m-d-H-i-s').'-'.$name.'.md';
		$handle = fopen($filename, 'w');
		$data = '## '.$name.' is down'."\n\n";
		// $data .= '### Error'."\n\n";
		// $data .= $response_data[$timestamp]['error']."\n\n";
		fwrite($handle, $data);
		fclose($handle);

		// send email
		$to = "".ALERT_MAIL."";
		$subject = "Alert: ".$name." is down";
		$message = "The website ".$name." is down. Please check the incident file in the incidents folder.";
		$message .= wordwrap($message, 70, "\r\n");
		$message .= "".ALERT_MAIL_MESSAGE."";

		//send
		mail($to, $subject, $message);

	}
	else {
		$info = curl_getinfo($curl);
		$http_code = $info['http_code'];
		$ms = $info['total_time_us'] / 1000;
		$response_data[$timestamp]['time'] = $ms;
		$response_data[$timestamp]['response'] = $http_code;
	}
	
	curl_close($curl);
	if(file_exists(PATH.'/monitors/'.$name)) {
		$data = json_decode(file_get_contents(PATH.'/monitors/'.$name), TRUE);
	}
	else {
		$data = array();
	}
	$data = array_merge($data, $response_data);
	$data = array_slice($data, -60);
	file_put_contents(PATH.'/monitors/'.$name, json_encode($data, JSON_PRETTY_PRINT));
}
