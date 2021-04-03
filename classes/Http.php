<?php
	/**
	* Asynchronous Http class for async GET and POST requests
	*/
	class Http{

		public static array $hostnameCache = [];

		public Socket $socket;
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
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

			$components = parse_url($url);
			$this->scheme = $components['scheme'];
			$this->host = $components['host'];
			$this->port = $components['port'] ?? "80";
			$this->path = $components['path'] ?? "/";
			$this->query = $components['query'] ?? "";

			$recordData = dns_get_record($this->host, DNS_A);
			$this->ipAddress = $recordData[0]['ip'];

			$status = socket_connect($this->socket, $this->ipAddress, $this->port);
			if ($status === false){
				var_dump(socket_strerror(socket_last_error($this->socket)));
				die();
			}

			// Turn blocking of the socket off
			socket_set_nonblock($this->socket);

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
				socket_write($this->socket, $getBody);

				while (time() - $startTime < 5 && $streamEnded === false){
					$data = @socket_read($this->socket, 1024);
					if ($data !== false){
						if ($data === ""){
							$streamEnded = true;
						}else{
							var_dump($data);
						}
					}
					yield;
				}

				if (!$streamEnded){
					print("TIMEOUT");
				}else{
					// Finished normally
				}
			}
		}
	}
