<?php
	require_once __DIR__ . "/classes/Async.php";
	require_once __DIR__ . "/classes/Http.php";

	header("content-type: text/plain");

	// Top-level Fiber (equivalent to top-level async/await in JavaScript)
	$main = new Fiber(function(){

		$requests = [
			new Http("get", "https://animetavern.com"),
			new Http("get", "https://footbridgemedia.com"),
			new Http("get", "https://www.mcmahonplumbing.com/"),
			new Http("get", "https://www.prowaterheatersnj.com/"),
			new Http("get", "https://codeburst.io/top-10-discord-servers-for-developers-86570fcdbff3"),
			new Http("get", "https://www.php.net/manual/en/stream.errors.php"),
			new Http("get", "https://discord.me/devcord"),
		];

		foreach($requests as $request){
			// Async function. All awaits are non-blocking
			$childFiber = new Fiber(function() use ($request, &$finishedLoops){
				Async::await($request->connect());
				$response = Async::await($request->fetch());
			});
			$childFiber->start();
		}

		// Start the event loop of all available fibers. This is blocking
		// TODO Make this yield as well!
		Async::run();

		print("All requests finished asynchronously!\n");
	});

	// Start the top-level Fiber
	$main->start();
