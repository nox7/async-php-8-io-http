<?php
	require_once __DIR__ . "/classes/Http.php";

	$request = new Http("get", "https://animetavern.com");
	foreach($request->connect() as $d){
		if ($request->connected){
			foreach($request->fetch() as $d){
				//var_dump($d);
			}
		}
	}
