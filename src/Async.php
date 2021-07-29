<?php
	class Async{

		public static array $activeAwaits = [];

		/**
		* Awaits the child fiber to finish execution and suspends the calling
		* parent fiber upon the child fiber suspending/yielding.
		*/
		public static function await(Fiber $childFiber): mixed{
			self::$activeAwaits[] = [Fiber::getCurrent(), $childFiber];
			$childFiber->start();
			while ($childFiber->isTerminated() === false){
				$childFiber->resume();

				// Don't suspend here if the childFiber is now terminated - it's
				// a wasted suspension.
				if (!$childFiber->isTerminated()){
					Fiber::suspend();
				}else{
					break;
				}
			}

			return $childFiber->getReturn();
		}

		/**
		* Starts the blocking event loop that runs all registered fibers.
		* TODO This could also yield by using Fiber::this() to detect if
		* this event loop is part of another parent fiber.
		*/
		public static function run(): void{

			while (count(self::$activeAwaits) > 0){
				$toRemove = [];
				foreach(self::$activeAwaits as $index=>$pair){
					$parentFiber = $pair[0];
					$childFiber = $pair[1];

					if ($parentFiber->isSuspended() && $parentFiber->isTerminated() === false){
						// Resume the parent fiber
						$parentFiber->resume();
					}elseif ($parentFiber->isTerminated()){
						// Register this fiber index to be removed from the activeAwaits
						$toRemove[] = $index;
					}
				}

				foreach($toRemove as $indexToRemove){
					unset(self::$activeAwaits[$indexToRemove]);
				}

				// Re-index the array
				self::$activeAwaits = array_values(self::$activeAwaits);
			}

		}
	}
