<?php
	require_once __DIR__ . "/src/Async.php";
	require_once __DIR__ . "/src/Http/Http.php";

	use Http\Http;

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

		$startTime = microtime(true);
		foreach($requests as $request){
			// Async function. All awaits are non-blocking
			$childFiber = new Fiber(function() use ($request, &$finishedLoops){
				Async::await($request->connect());
				print(sprintf("Connected %s\n", $request->host));
				$response = Async::await($request->fetch());
				print(sprintf("Finished %s\n", $request->host));
			});
			$childFiber->start();
		}

		// Start the event loop of all available fibers. This is blocking
		// TODO Make this yield as well!
		Async::run();

		// Microtime is seconds on Windows as a float
		printf("All requests finished asynchronously in %fs\n", microtime(true) - $startTime);
	});

	// Start the top-level Fiber
	$main->start();
