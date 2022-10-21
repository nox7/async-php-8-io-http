# PHP Fibers - Async Examples Without External Dependencies
True asynchronous PHP I/O and HTTP without frameworks, extensions, or annoying code behemoths of libraries to install.

More examples to come - currently only HTTP GET and asynchronous MySQLi queries are shown.
## Requirement

Must be running PHP 8.1 - that is when PHP Fibers were introduced.

## Why do Fibers Help Make Async Code Possible?
Asynchronous code can be simplified in layman's terms to basically read as "code that runs when there is time to run" - it doesn't mean it runs in parallel to other code. Imagine you send two HTTP requests in your code. Why should you wait for one to finish before starting the second one? This is asynchronous code - because we're waiting on the first request to finish, we can use that time to make another.

This "waiting" is where Fibers can help us manage multiple code blocks. If you've ever used a language like JavaScript which has native asynchronous code support (async/await on Promises) then you've never had to worry about how it works behind the scenes.

Fibers allow you to **code your own asynchronous architecture**. Asynchronous code relies on an underlying "Event Loop" (JavaScript handles this for you, for example). 

Consider that your main application has a loop similar to the pseudo code below:

```
fibers = [];

fibers[] = new Fiber(function(){
	// Imagine that run_my_code() has new Fibers added to the fibers array
	run_my_code();
});

do{
	foreach($fibers as $fiber){
		if ($fiber->isSuspended() && $fiber->isTerminated() === false_{
			$fiber->resume();
		}else{
			// Remove it from the fibers array
		}
	}
}while(!empty(fibers));
```

If the function `run_my_code()` creates new fibers, the loop will keep looping. It's slightly more technical than this - you have to check if the fibers are terminated/finished or are still pending. But the gist is that Fibers allow you to make your own asynchronous framework to write asynchronous code - they don't magically make code asynchronous on their own. Suspending a fiber is similar to "waiting" and letting the next fiber run while we wait on that suspended fiber.

The main loop (the "Event Loop") will make sure the PHP process doesn't end until all Fibers have terminated and thus all the code in your application is done running.

## Rundown of How It Works

Because the internal PHP functions (such as `file_get_contents`) are blocking by default, to implement asynchronous HTTP with PHP 8.1 Fibers, you must rebuild an HTTP request wrapper with native `socket_` functions. Sockets in PHP can be set to be non-blocking. With this, a simple event loop can be created that can run all the Fibers in the event loop stack. If a socket isn't ready to be read (such as, the HTTP request is still pending) the Fiber will suspend and the next one can run.

This is all done in native PHP (version 8.1+) with no libraries. A language, such as JavaScript, has an internal event loop (which PHP does not) so that is why we have to create our own event loop (in the `src/Async/Async.php` file) to manage our Fibers and run them.

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
