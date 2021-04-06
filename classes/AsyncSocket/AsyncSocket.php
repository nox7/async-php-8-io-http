<?php
	require_once __DIR__ . "/exceptions/SocketConnectionTimedOut.php";
	require_once __DIR__ . "/exceptions/SocketEnableCryptoTimeout.php";
	require_once __DIR__ . "/exceptions/SocketEnableCryptoFailed.php";
	require_once __DIR__ . "/exceptions/SocketNotConnected.php";

	/**
	* General asynchronous socket implementation
	*/
	class AsyncSocket{

		public static array $hostnameCache = [];
		public static int $defaultConnectionTimeoutSeconds = 3;
		public static int $defaultSSLNegotiationsTimeoutSeconds = 3;
		public static int $defaultWriteTimeoutSeconds = 30; // Max time a socket can be in writing
		public static int $defaultReadTimeoutSeconds = 30; // Max time a socket can be in reading
		public static int $maxWriteBytesPerIteration = 1024 * 5; // 5KB write per async loop
		public static int $maxReadBytesPerIteration = 1024 * 5; // 5KB read per async loop

		public $socket;
		public bool $connected = false;

		public function __construct(
			public string $host,
			public int $port
		){

		}

		/**
		* Makes an asynchronous connection to the socket
		*/
		public function connect(): Fiber{

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

			return new Fiber(function(){
				// Turn blocking of the socket off
				stream_set_blocking($this->socket, false);

				$read = [];
				$write = [$this->socket];
				$excepts = [];

				// Wait for a connection
				$beginTime = time();
				do{
					$socketsAvailable = stream_select($read, $write, $excepts, null);
					if ($socketsAvailable === 1){
						$this->connected = true;
						return;
					}else{
						Fiber::suspend();
					}
				} while (time() - $beginTime <= self::$defaultConnectionTimeoutSeconds);

				// If the code got here, then the socket didn't connect before the timeout
				throw new SocketConnectionTimedOut("The socket could not make a connection and timed out.");
			});
		}

		/**
		* Asynchronously enable crypto mode/SSL/TLS on the socket
		*/
		public function enableCrypto(): Fiber{
			return new Fiber(function(){
				$beginTime = time();
				$result;
				do{
					$result = stream_socket_enable_crypto($this->socket, $enabled = true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
					if ($result === true){
						break;
					}elseif ($result === false){
						break;
					}else{
						// Wait, it's still working...
						Fiber::suspend();
					}
				}while ($result !== true && (time() - $beginTime <= self::$defaultSSLNegotiationsTimeoutSeconds));

				if ($result === 0){
					throw new SocketEnableCryptoTimeout;
				}elseif ($result === false){
					throw new SocketEnableCryptoFailed(sprintf("SSL failed for host %s", $this->host));
				}

				// Success
			});
		}

		/**
		* Asynchronous socket write
		*/
		public function write(string $data): Fiber{
			if (!$this->connected){
				throw new SocketNotConnected("Cannot write to a socket that is not connected.");
			}

			return new Fiber(function() use ($data){
				$bytesWritten = 0;
				$reads = [];
				$writes = [$this->socket];
				$excepts = [];

				$beginTime = time();
				do{
					$socketsAvailable = stream_select($reads, $writes, $excepts, null);
					if ($socketsAvailable === 1){
						$bytes = fwrite($this->socket, $data, self::$maxWriteBytesPerIteration);
						if ($bytes !== false){
							$data = substr($data, self::$maxWriteBytesPerIteration);
						}else{
							// break;
						}
					}

					// Always suspend
					Fiber::suspend();
				} while ($data !== "" && (time() - $beginTime) <= self::$defaultWriteTimeoutSeconds);
			});
		}

		/**
		* Asynchronous socket full-read until no more data is being sent to the socket
		*/
		public function readAllData(): Fiber{
			if (!$this->connected){
				throw new SocketNotConnected("Cannot read from a socket that is not connected.");
			}

			return new Fiber(function(){
				$buffer = "";
				$reads = [$this->socket];
				$writes = [];
				$excepts = [];

				$beginTime = time();
				do{
					$socketsAvailable = stream_select($reads, $writes, $excepts, null);
					if ($socketsAvailable === 1){
						$data = fread($this->socket, self::$maxReadBytesPerIteration);
						if ($data !== false){
							if ($data === ""){
								if ($buffer !== ""){
									// All data has been read
									break;
								}else{
									// The socket is still waiting on data to be sent
									// or the server is not sending data.
									// We can't actually know which here, so we just have
									// to wait for the timeout.
								}
							}else{
								// Data is not blank, add it to the buffer
								$buffer .= $data;
							}
						}
					}
					// Always suspend
					Fiber::suspend();
				} while (time() - $beginTime <= self::$defaultReadTimeoutSeconds);

				// TODO Should an exception be thrown if data WAS read
				// and a default read timeout happened?/
				// For now, just return the buffer

				return $buffer;
			});
		}
	}
