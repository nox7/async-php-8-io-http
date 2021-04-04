<?php
	require_once __DIR__ . "/classes/Async.php";
	require_once __DIR__ . "/classes/Http.php";

	header("content-type: text/plain");

	$main = new Fiber(function(){

		$requests = [
			new Http("get", "https://animetavern.com"),
			new Http("get", "https://animetavern.com"),
			new Http("get", "https://animetavern.com"),
			new Http("get", "https://footbridgemedia.com"),
			new Http("get", "https://www.mcmahonplumbing.com/"),
			new Http("get", "https://www.prowaterheatersnj.com/"),
		];

		foreach($requests as $request){
			$childFiber = new Fiber(function() use ($request){

				print(sprintf("Connecting to %s at %s\n", $request->url, microtime(true)));
				Async::await($request->connect(), $request->host, 1);

				print(sprintf("Connected to %s\n", $request->url));
				$data = Async::await($request->fetch(), $request->host, 2);

				print(sprintf("Data fetched from %s. %d bytes\n", $request->url, strlen($data)));
			});
			Async::load($childFiber);
		}

		Async::run();

	});

	$main->start();
