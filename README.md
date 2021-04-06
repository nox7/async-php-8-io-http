# PHP Fibers - Async Examples Without External Dependencies
True asynchronous PHP I/O and HTTP without frameworks, extensions, or annoying code behemoths of libraries to install.

More examples to come - currently only HTTP GET and asynchronous MySQLi queries are shown.
## Requirement

Until PHP 8.1 is out, you must have the Fiber extension in your PHP version. I compiled the php_fiber.dll myself on Windows 10 for PHP 8.0.3 for this example and ran it from XAMPP.

## Async HTTP GET Example

For the full example, checkout the http-example.php file. A small snippet is shown below.

The classes `Async` and `Http` are provided in the `classes` folder of this repository.

```php
$request1 = new Http("get", "http://example.com");
$request2 = new Http("get", "http://example.com");

foreach ([$request1, $request2] as $request){
	$child = new Fiber(function() use ($request){
		// ::await only blocks the _current_ thread. All other Fibers can still run
		Async::await($request->connect());
		Async::await($request->fetch());

		// Code here runs as soon as this URL is done fetching
		// and doesn't wait for others to finish :)
	});
	$child->start();
}

// Currently, ::run() is blocking the program and nothing below this
// call will run until the fibers above are finished.
// In the future, a top-level fiber can be introduced to make the entire
// application fully asynchronous. One is used in the example.php file
Async::run();
```

## Async MySQL Query Example

For the full example, checkout the mysql-example.php file. A small snippet is shown below.

The classes `Async` and `Http` are provided in the `classes` folder of this repository.

```php

// Initial connections are blocking. All queries are asynchronous.
// Spawn 10 connections in the pool.
$connectionsPool = new MySQLPool(10, "localhost", "root", "", "test");
$queryFiber = new Fiber(function() use ($connectionsPool){
	// ::await only blocks the _current_ thread. All other Fibers can still run
	$result = Async::await($connectionsPool->execute(
		"SELECT * FROM `test_table` WHERE `name` = :name",
		[':name'=>"nox7"],
	));
	print_r($result->fetch_assoc());
});
$child->start();
Async::run();
```

## Result of the http-example.php run

Shown below from a run of http-example.php within an Apache server request, URLs connected and fetched as soon as they were ready, and not in linear order - but asynchronously.

![Asynchronous PHP Requests](https://user-images.githubusercontent.com/17110935/113648260-f01ecd80-9651-11eb-9532-73c9f606d318.png)

## The Future of This Repository
I will continue to implement standalone asynchronous methods for common waitable tasks. Currently HTTP is implemented with basic fetching of content from a URL. Moving forward, I will support different HTTP requests and abstractions, asynchronous MySQLi queries, and asynchronous local file I/O.

The **goal** is to have a location where you can get the smallest amount of open source code to perform asynchronous tasks in PHP. A framework like AmpPHP will always be superior if you would like to center your codebase around it, but my goal is to give you the option to leave a smaller footprint or simply made an addition to your existing codebase.

Want to spin up a quick Apache or nginx server on your Linux machine or via XAMPP for windows and grab a couple files for asynchronous MySQLi? That's what I want to give you the choice to do.
