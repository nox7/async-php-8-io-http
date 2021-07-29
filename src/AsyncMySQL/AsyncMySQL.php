<?php
	namespace AsyncMySQL;

	use const MYSQLI_ASYNC;
	use const MYSQLI_REPORT_ERROR;
	use const MYSQLI_REPORT_STRICT;
	use const MYSQLI_STORE_RESULT;

	require_once __DIR__ . "/exceptions/OperationsOutOfSync.php";
	require_once __DIR__ . "/exceptions/QueryTimedOut.php";

	/**
	* Wrapper for MySQLi to run asynchronous queries.
	* No attempt at asynchronous connection is made.
	*/
	class AsyncMySQL{

		public static string $defaultNamesEncoding = "utf8mb4";
		public static string $defaultCollation = "utf8mb4_unicode_ci";
		public static int $defaultQueryTimeout = 5;

		public \mysqli $connection;

		/** Whether or not this class is available to perform another query. */
		public bool $isAvailable = true;

		public function __construct(
			public string $host,
			public string $user,
			private string $password,
			public string $database,
			public int $port = 3306
		){
			\mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

			$connection = new \mysqli($host, $user, $password, $database, $port);
			$this->connection = $connection;

			// Run this synchronously
			$connection->query(sprintf("SET NAMES %s COLLATE %s", self::$defaultNamesEncoding, self::$defaultCollation));
		}

		/**
		 * Asynchronously executes a query with named arguments.
		 * Will return the result of the query.
		 * @throws OperationsOutOfSync
		 */
		public function execute(string $query, array $namedArgs = []): \Fiber{

			if (!$this->isAvailable){
				throw new OperationsOutOfSync("Cannot run execute on this AsyncMySQL object at this time. It is currently handling another query. Consider using the MySQLPool class.");
			}

			$this->isAvailable = false;

			return new \Fiber(function() use ($query, $namedArgs){
				if (count($namedArgs) > 0){
					$query = $this->buildQueryFromNamedArgs($query, $namedArgs);
				}

				$this->connection->query($query, MYSQLI_STORE_RESULT | MYSQLI_ASYNC);
				$toPoll = [$this->connection];
				$errors = [];
				$rejections = [];
				$beginTime = time();
				\Fiber::suspend();

				$numReadyQueries;
				do{
					$numReadyQueries = (int) \mysqli::poll($toPoll, $errors, $rejections, self::$defaultQueryTimeout);
					if ($numReadyQueries > 0){
						break;
					}

					\Fiber::suspend();
				} while ((time() - $beginTime <= self::$defaultQueryTimeout));

				$this->isAvailable = true;

				if ($numReadyQueries > 0){
					$result = $this->connection->reap_async_query();
					if ($result === true){
						// This was an UPDATE, INSERT, or DELETE
						return $this->connection;
					}else{
						return $result;
					}
				}else{
					throw new QueryTimedOut();
				}
			});
		}

		/**
		* Builds an escaped query from the provided named arguments
		*/
		private function buildQueryFromNamedArgs(string $query, array $namedArgs): string{
			foreach($namedArgs as $parameterName=>$parameterValue){
				$parameterValue = $this->connection->real_escape_string($parameterValue);
				if (is_string($parameterValue)){
					$query = str_replace($parameterName, sprintf('"%s"', $parameterValue), $query);
				}elseif (is_bool($parameterValue)){
					$query = str_replace($parameterName, sprintf('%s', $parameterValue ? "true" : "false"), $query);
				}elseif (is_double($parameterValue)){
					$query = str_replace($parameterName, sprintf('%f', $parameterValue), $query);
				}elseif (is_int($parameterValue)){
					$query = str_replace($parameterName, sprintf('%d', $parameterValue), $query);
				}
			}

			return $query;
		}
	}
