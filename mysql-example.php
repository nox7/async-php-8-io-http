<?php
	require_once __DIR__ . "/classes/Async.php";
	require_once __DIR__ . "/classes/AsyncMySQL/MySQLPool.php";

	header("content-type: text/plain");

	// Top-level Fiber (equivalent to top-level async/await in JavaScript)
	$main = new Fiber(function(){

		$mysqlPool = new MySQLPool(10, "localhost", "root", "", "test");

		$queryFiber = new Fiber(function() use ($mysqlPool){
			$result = Async::await($mysqlPool->execute(
				"SELECT * FROM test_table WHERE `id` = :id",
				[":id"=>1]
			));

			// Will return as soon as this query is done and does not wait on others
			print_r($result);
		});
		$queryFiber->start();

		// Hold here until all async operations are done
		Async::run();
	});

	// Start the top-level Fiber
	$main->start();
