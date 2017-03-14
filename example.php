<?php

require 'LSCurlX.class.php';

$curlx = new LSCurlX;

// maintain 10 curl connections until it finish.
// set 7 seconds process time for each request
// set curl option for each request
$curlx->setMaxConnection(10)->setTimeout(7)->setGlobal('option', array(
	CURLOPT_FOLLOWLOCATION => false,
	CURLOPT_FAILONERROR => false,
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_SSL_VERIFYHOST => false,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_VERBOSE => true,
	CURLOPT_CONNECTTIMEOUT => 20,
));

for($f=0;$f<3000;$f++){
	$curlx->get('https://api.ipify.org?format=json', function() use ($f){
		echo 'curl finished: '.$f.' index_id, ';
	});
}

$curlx->execute();
