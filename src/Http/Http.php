<?php
	namespace Http;

	require_once __DIR__ . "/../Async.php";
	require_once __DIR__ . "/../AsyncSocket/AsyncSocket.php";

	use AsyncSocket\AsyncSocket;

	/**
	* Asynchronous Http class for async GET and POST requests
	*/
	class Http{

		public AsyncSocket $socket;
		public string $method;
		public string $url;
		public string $host;
		public string $scheme;
		public int $port;
		public string $path;
		public string $query;

		public function __construct(string $method, string $url){
			$this->method = $method;
			$this->url = $url;

			// Break the URL into components
			$components = parse_url($url);
			$port = $components['port'] ?? "80"; // Port is a string here
			$this->port = (int) $port; // Force it as an integer

			$this->scheme = $components['scheme'];
			$this->host = $components['host'];
			$this->path = $components['path'] ?? "/";
			$this->query = $components['query'] ?? "";

			// For now, force SSL on 443, but in reality we know
			// you can serve an SSL certificate from any port.
			if ($this->scheme === "https"){
				if ($this->port === 80){
					$this->port = 443;
				}
			}

			$this->socket = new AsyncSocket($this->host, $this->port);
		}

		/**
		* Makes an asynchronous connection to the socket
		*/
		public function connect(): \Fiber{
			// Because TLS/SSL requires both parties to send
			// and receive data upon connection, ssl:// cannot be used
			// in the initial request. TCP must be used and then
			// crypto must be enabled below
			return new \Fiber(function(){
				\Async::await($this->socket->connect());

				if ($this->scheme === "https"){
					\Async::await($this->socket->enableCrypto());
				}
			});
		}

		/**
		* Begins fetching of the data
		*/
		public function fetch(): \Fiber{
			return new \Fiber(function(){
				if ($this->method === "get"){
					$getBody = sprintf("GET %s\r\n", $this->path);
					$getBody .= sprintf("Host: %s\r\n", $this->host);
					$getBody .= sprintf("Accept: */*\r\n");
					$getBody .= "\r\n";
					$beginTime = time();

					\Async::await($this->socket->write($getBody));
					$data = \Async::await($this->socket->readAllData());

					return $data;
				}
			});
		}
	}
