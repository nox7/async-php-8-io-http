# PHP Fibers - Async Examples Without External Dependencies
True asynchronous PHP I/O and HTTP without frameworks, extensions, or annoying code behemoths of libraries to install.

More examples to come - currently only HTTP GET is displayed.

## Requirement

Until PHP 8.1 is out, you must have the Fiber extension in your PHP version. I compiled the php_fiber.dll myself on Windows 10 for PHP 8.0.3 for this example and ran it from XAMPP.

## Brief Example

For the full example, checkout the example.php file. A small snippet is shown below.

The classes `Async` and `Http` are provided in the `classes` folder of this repository.

```php
// Top-level fiber (same as top-level async/await in JavaScript)
$main = new Fiber(function(){
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

	// Currently, ::run() blocks the top-level fiber and will await all the child fibers above.
	// This will be changed to allow ::awaitAll() or simply ignoring it entirely for full asynchronous
	// processes.
	Async::run();
});
$main->start();
```
