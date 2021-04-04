# PHP Fibers - Async Examples Without External Dependencies
True asynchronous PHP I/O and HTTP without frameworks, extensions, or annoying code behemoths of libraries to install.

More examples to come - currently only HTTP GET is displayed.

## Requirement

Until PHP 8.1 is out, you must have the Fiber extension in your PHP version. I compiled the php_fiber.dll myself on Windows 10 for PHP 8.0.3 for this example and ran it from XAMPP.

## Brief Example

For the full example, checkout the example.php file. A small snippet is shown below.

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
