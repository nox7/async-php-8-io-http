<?php
	require_once __DIR__ . "/classes/Http.php";

	$request = new Http("get", "http://animetavern.com");
	foreach($request->fetch() as $d){
		//var_dump($d);
	}
