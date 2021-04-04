<?php
	/**
	* Asynchronous Http class for async GET and POST requests
	*/
	class Http{

		public static array $hostnameCache = [];

		public $socket;
		public bool $connected;
		public string $method;
		public string $url;
		public string $host;
		public string $scheme;
		public string $port;
		public string $path;
		public string $query;
		public string $fragment;
		public string $ipAddress;

		public function __construct(string $method, string $url){
			$this->method = $method;
			$this->url = $url;

			$components = parse_url($url);
			$this->scheme = $components['scheme'];
			$this->host = $components['host'];
			$this->port = $components['port'] ?? "80";
			$this->path = $components['path'] ?? "/";
			$this->query = $components['query'] ?? "";
			$this->connected = false;

			if ($this->scheme === "https"){
				if ($this->port === "80"){
					$this->port = "443";
				}
			}

			$recordData = dns_get_record($this->host, DNS_A);
			$this->ipAddress = $recordData[0]['ip'];

		}

		/**
		* Makes an asynchronous connection to the socket
		*/
		public function connect(): Fiber{
			// Because TLS/SSL requires both parties to send
			// and receive data upon connection, ssl:// cannot be used
			// in the initial request. TCP must be used and then
			// crypto must be enabled below

			$this->socket = stream_socket_client(
				sprintf("%s://%s:%s", "tcp", $this->host, $this->port),
				$errorNumber,
				$errorString,
				null,
				STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
				stream_context_create([
					"socket"=>[
						"tcp_nodelay"=>false,
					],
				])
			);

			$socket = $this->socket;
			$scheme = $this->scheme;
			$connected = &$this->connected;

			return new Fiber(function() use ($socket, $scheme, &$connected){
				// Turn blocking of the socket off
				stream_set_blocking($socket, false);

				$read = [];
				$write = [$socket];
				$excepts = [];
				$socketsAvailable = stream_select($read, $write, $excepts, 5);

				// Wait for a connection
				while ($socketsAvailable === 0){
					$socketsAvailable = stream_select($read, $write, $excepts, 5);
					Fiber::suspend();
				}

				// Enable crypto
				// Don't check ports, check the scheme
				// SSL port is generally 443, but not all the time is this required
				if ($scheme === "https"){
					while (true){
						$socketsAvailable = stream_select($read, $write, $excepts, 5);
						if ($socketsAvailable > 0){
							$result = stream_socket_enable_crypto($socket, $enabled = true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
							if ($result === true){
								break;
							}elseif ($result === false){
								throw new Exception("SSL failed");
							}else{
								// Wait, it's still working...
								Fiber::suspend();
							}
						}
					}
				}

				$connected = true;
				return;
			});
		}

		/**
		* Begins fetching of the data
		*/
		public function fetch(): Fiber{
			$socket = $this->socket;
			$path = $this->path;
			$host = $this->host;
			$method = $this->method;
			return new Fiber(function() use ($socket, $path, $host, $method){
				if ($method === "get"){
					$getBody = sprintf("GET %s\r\n", $path);
					$getBody .= sprintf("Host: %s\r\n", $host);
					$getBody .= sprintf("Accept: */*\r\n");
					$getBody .= "\r\n";
					$startTime = time();
					$streamEnded = false;
					$reads = [];
					$writes = [$socket];
					$excepts = [];
					$socketsAvailable = stream_select($reads, $writes, $excepts, 5);

					// Wait for the stream to be writable
					while ($socketsAvailable === 0){
						Fiber::suspend();
						$socketsAvailable = stream_select($reads, $writes, $excepts, 5);
					}
					$bytes = fwrite($socket, $getBody);

					$buffer = "";
					while (time() - $startTime < 3 && $streamEnded === false){
						$reads = [$socket];
						$writes = [];
						$excepts = [];
						$socketsAvailable = stream_select($reads, $writes, $excepts, 5);
						$data = fread($socket, 1024);
						if ($data !== false){
							// fread will be blank upon initial connecting/decrypting
							if ($data === "" && $buffer !== ""){
								$streamEnded = true;
								break;
							}elseif ($data === "" && $buffer === ""){
								// Just keep waiting
								Fiber::suspend();
							}elseif ($data !== ""){
								// Read the data
								$buffer .= $data;
								Fiber::suspend();
							}
						}
					}

					if (!$streamEnded){
						throw new Exception("Connection timed out.");
					}else{
						// Finished normally
						return $buffer;
					}
				}
			});
		}
	}
