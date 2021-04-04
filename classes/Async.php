<?php
	class Async{

		public static array $activeAwaits = [];

		public static function await(Fiber $childFiber){
			self::$activeAwaits[] = [Fiber::this(), $childFiber];
			$childFiber->start();
			while ($childFiber->isTerminated() === false){
				$childFiber->resume();

				// Don't suspend here if the childFiber is terminated now
				// this will cause the parent fiber to never be resumed
				if (!$childFiber->isTerminated()){
					Fiber::suspend();
				}else{
					break;
				}
			}

			return $childFiber->getReturn();
		}


			while (count(self::$activeAwaits) > 0){
				$toRemove = [];
				foreach(self::$activeAwaits as $index=>$pair){
					$parentFiber = $pair[0];
					$childFiber = $pair[1];

					if ($parentFiber->isSuspended() && $parentFiber->isTerminated() === false){
						$parentFiber->resume();
					}elseif ($parentFiber->isTerminated()){
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
