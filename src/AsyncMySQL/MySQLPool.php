<?php
	namespace AsyncMySQL;

	require_once __DIR__ . "/AsyncMySQL.php";

	class MySQLPool{

		protected array $asyncMySQLs = [];

		public function __construct(
			public int $numConnections,
			public string $host,
			public string $user,
			private string $password,
			public string $database,
			public int $port = 3306
		){
			for ($i = 0; $i < $numConnections; $i++){
				$this->asyncMySQLs[] = new AsyncMySQL($host, $user, $password, $database, $port);
			}
		}

		/**
		* Runs an asynchronous ::execute() on an available AsyncMySQL object.
		* If none are available, will wait until one is.
		*/
		public function execute(string $query, array $namedArgs = []): \Fiber{
			$asyncMySQL = null;

			return new \Fiber(function() use ($query, $namedArgs){
				do{
					foreach ($this->asyncMySQLs as $obj){
						if ($obj->isAvailable){
							$asyncMySQL = $obj;
							break;
						}
					}

					if ($asyncMySQL === null){
						\Fiber::suspend();
					}
				} while ($asyncMySQL === null);

				return \Async::await($asyncMySQL->execute($query, $namedArgs));
			});

		}
	}
