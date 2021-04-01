<?php


	namespace MehrIt\PhpCache;


	use InvalidArgumentException;
	use MehrIt\PhpCache\Exception\PhpCacheException;

	class FileLock
	{
		

		/**
		 * @var resource 
		 */		
		protected $handle;

		/**
		 * @var bool 
		 */
		protected $locked = true;

		/**
		 * Creates a new instance
		 * @param resource $handle The handle
		 */
		public function __construct($handle) {
			$this->handle = $handle;
		}

		/**
		 * Sets the lock mode
		 * @param int $mode The mode
		 * @return $this
		 */
		public function setMode(int $mode): FileLock {
			
			switch($mode) {
				case LOCK_SH:
				case LOCK_EX:
					if (!@flock($this->handle, $mode))
						throw new PhpCacheException('Failed to change lock mode');
					break;
					
				default:
					throw new InvalidArgumentException("Invalid lock mode \"{$mode}\".");
			}
			
			return $this;
		}

		/**
		 * Releases the lock
		 * @return $this
		 */
		public function release(): FileLock {
			
			if ($this->locked) {
				if (!@flock($this->handle, LOCK_UN))
					throw new PhpCacheException('Failed to release lock');
				
				$this->locked = false;

				if (is_resource($this->handle))
					fclose($this->handle);
			}
			
			return $this;
		}
	}