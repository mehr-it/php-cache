<?php


	namespace MehrIt\PhpCache\Concerns;


	use InvalidArgumentException;
	use MehrIt\PhpCache\Exception\PhpCacheException;
	use MehrIt\PhpCache\FileLock;

	trait Locking
	{

		/**
		 * Gets the locks directory
		 * @return string The directory
		 */
		protected abstract function getLocksDirectory(): string;

		/**
		 * Ensures that the given file has correct permission
		 * @param string $path The path
		 */
		protected abstract function ensureFilePermission(string $path): void;

		/**
		 * Prepares the directory tree, so all expected directories exist.
		 */
		protected abstract function initDirectoryStructure(): void;


		/**
		 * Creates a new file base lock
		 * @param string $filename The filename
		 * @param int $mode The lock mode, LOCK_SH or LOCK_EX
		 * @return FileLock
		 */
		protected function createLock(string $filename, int $mode = LOCK_SH): FileLock {
			
			$fh = @fopen($filename, 'c');
			if (!$fh)
				throw new PhpCacheException("Could not open lock file \"{$filename}\"");

			$success = false;
			try {
				// ensure correct file permissions
				$this->ensureFilePermission($filename);

				if (!@flock($fh, $mode))
					throw new PhpCacheException("Could not obtain lock for \"{$filename}\"");
				
				$success = true;
			}
			finally {
				if (!$success && is_resource($fh))
					fclose($fh);
			}
			
			return new FileLock($fh);
		}

		/**
		 * Executes the given callback while locking the given item data file 
		 * @param string $itemBaseFileName The base name of the file containing the item data
		 * @param int $mode The loc mode, LOCK_SH or LOCK_EX
		 * @param callable $callback The callback
		 * @return mixed The callback return
		 */
		protected function withFileItemLock(string $itemBaseFileName, int $mode, callable $callback) {

			if ($mode !== LOCK_SH && $mode !== LOCK_EX)
				throw new InvalidArgumentException("Invalid lock mode \"{$mode}\"");
			
			$this->initDirectoryStructure();

			$lockFile = $this->getItemFileLockFilename($itemBaseFileName);
			
			try {
				$lock = $this->createLock($lockFile, $mode);

				return call_user_func($callback, $lock);
			}
			finally {
				if (!empty($lock))
					$lock->release();
			}
		}

		/**
		 * Create a named lock
		 * @param string $name The name
		 * @param int $mode The mode
		 * @return FileLock
		 */
		protected function namedLock(string $name, int $mode): FileLock {
			$this->initDirectoryStructure();
			
			return $this->createLock($this->getNamedLockFilename($name), $mode);
		}

		/**
		 * Gets the lock file name for the given item data file
		 * @param string $itemBaseFileName The base name of the file containing the item data
		 * @return string The lock filename
		 */
		protected function getItemFileLockFilename(string $itemBaseFileName): string {

			return $this->getLocksDirectory() . '/i-' . substr($itemBaseFileName, 0, 4) . '.lock';
		}

		/**
		 * Gets the file name for a named lock
		 * @param string $name The name
		 * @return string The file name
		 */
		protected function getNamedLockFilename(string $name): string {
			
			if (!preg_match('/^[A-Za-z0-9\\-_]+$/', $name))
				throw new InvalidArgumentException('Lock name contains invalid chars.');
			if (strlen($name) > 64)
				throw new InvalidArgumentException('Lock name exceeds maximum length of 64.');

			return $this->getLocksDirectory() . '/n-' . $name . '.lock';
		}


	}