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
			//$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

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
		public function connect(){

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

			// Turn blocking of the socket off
			stream_set_blocking($this->socket, false);

			$read = [];
			$write = [$this->socket];
			$excepts = [];
			$socketsAvailable = stream_select($read, $write, $excepts, 1);

			// Wait for a connection
			while ($socketsAvailable === 0){
				$socketsAvailable = stream_select($read, $write, $excepts, 1);
				yield;
			}

			// Enable crypto
			// Don't check ports, check the scheme
			// SSL port is generally 443, but not all the time is this required
			if ($this->scheme === "https"){
				while (true){
					$socketsAvailable = stream_select($read, $write, $excepts, 1);
					if ($socketsAvailable > 0){
						$result = stream_socket_enable_crypto($this->socket, $enabled = true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
						if ($result === true){
							break;
						}elseif ($result === false){
							throw new Exception("SSL failed");
						}else{
							// Wait, it's still working...
							yield;
						}
					}
				}
			}

			$this->connected = true;
			yield;
		}

		/**
		* Begins fetching of the data
		*/
		public function fetch(): Generator{
			if ($this->method === "get"){
				$getBody = sprintf("GET %s\r\n", $this->path);
				$getBody .= sprintf("Host: %s\r\n", $this->host);
				$getBody .= sprintf("Accept: */*\r\n");
				$getBody .= "\r\n";
				$startTime = time();
				$streamEnded = false;
				$reads = [];
				$writes = [$this->socket];
				$excepts = [];
				$socketsAvailable = stream_select($reads, $writes, $excepts, 1);

				// Wait for the stream to be writable
				while ($socketsAvailable === 0){
					$socketsAvailable = stream_select($reads, $writes, $excepts, 1);
					yield;
				}
				$bytes = fwrite($this->socket, $getBody);

				$buffer = "";
				while (time() - $startTime < 3 && $streamEnded === false){
					$reads = [$this->socket];
					$writes = [];
					$excepts = [];
					$socketsAvailable = stream_select($reads, $writes, $excepts, 1);
					$data = fread($this->socket, 1024);
					if ($data !== false){
						// fread will be blank upon initial connecting/decrypting
						if ($data === "" && $buffer !== ""){
							$streamEnded = true;
							break;
						}elseif ($data === "" && $buffer === ""){
							// Just keep waiting
							yield;
						}elseif ($data !== ""){
							// Read the data
							$buffer .= $data;
							yield;
						}
					}
				}


				if (!$streamEnded){
					print("Connection timed out");
				}else{
					// Finished normally
					var_dump($buffer);
					yield;
				}
			}
		}
	}
