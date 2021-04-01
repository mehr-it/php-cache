<?php


	namespace MehrIt\PhpCache\Concerns;


	use FilesystemIterator;
	use Generator;
	use MehrIt\PhpCache\Exception\PhpCacheException;
	use SplFileInfo;

	trait Filesystem
	{
		protected $directoryPermission;

		protected $filePermission;

		/**
		 * @var callable|null
		 *
		 * This is cached in a local static variable to avoid instantiating a closure each time we need an empty handler
		 */
		private static $emptyErrorHandler;

		/**
		 * Prepares the directory tree, so all expected directories exist.
		 */
		protected abstract function initDirectoryStructure(): void;

		/**
		 * Gets the base directory
		 * @return string The base directory
		 */
		protected abstract function baseDir(): string;

		/**
		 * Reads the data for the given file
		 * @param string[] $path The path segments
		 * @param string $filename The file name
		 * @return array The data
		 */
		protected function readFile(array $path, string $filename): array {
			$data = null;

			$fullPath = $this->fullPath($path, $filename);

			// suppress error: this is the fastest method
			if (self::$emptyErrorHandler === null)
				self::$emptyErrorHandler = static function () {
				};
			set_error_handler(self::$emptyErrorHandler);

			/** @noinspection PhpIncludeInspection */
			$data = include $fullPath;

			restore_error_handler();

			if (!is_array($data))
				return [];

			return $data;
		}

		/**
		 * Puts the given data to the file
		 * @param string[] $path The path segments
		 * @param string $filename The file name
		 * @param array $data The data
		 */
		protected function writeFile(array $path, string $filename, array $data): void {

			$this->initDirectoryStructure();


			$fullPath = $this->fullPath($path, $filename);

			if ($data === []) {
				$this->deleteFile($fullPath);
			}
			else {
				// ensure the directory structure exists
				$this->ensureSubTreeExists($path);
				
				if (!@file_put_contents($fullPath, '<?php return ' . var_export($data, true) . ';'))
					throw new PhpCacheException("Could not write data to \"$fullPath\"");

				// ensure correct file permission
				$this->ensureFilePermission($fullPath);

				clearstatcache(true, $fullPath);

				opcache_invalidate($fullPath, true);
			}

			
		}

		/**
		 * Deletes the given file
		 * @param string $fullPath The full path
		 */
		protected function deleteFile(string $fullPath): void {

			if (!file_exists($fullPath))
				return;

			if (!@unlink($fullPath))
				throw new PhpCacheException("Could not delete file \"$fullPath\"");

			clearstatcache(true, $fullPath);

			opcache_invalidate($fullPath, true);
		}

		/**
		 * Gets the full path
		 * @param string[] $path The path segments
		 * @param string $filename The filename
		 * @return string The full path
		 */
		protected function fullPath(array $path, string $filename): string {
			return $this->baseDir() . '/' . implode('/', $path) . '/' . $filename;
		}

		/**
		 * Ensures that the given subtree exists
		 * @param string[] $subTree The subtree path segments
		 */
		protected function ensureSubTreeExists(array $subTree = []): void {

			$path = $this->baseDir();
			foreach ($subTree as $segment) {
				$path .= "/{$segment}";

				$this->ensureDirectoryExists($path);
			}
		}

		/**
		 * Ensures that the given directory exists. Permissions are only set for last directory level.
		 * @param string $path The path
		 * @param bool $recursive True if to create path recursively
		 */
		protected function ensureDirectoryExists(string $path, bool $recursive = false): void {

			if (!file_exists($path)) {

				// create directory
				if (!@mkdir($path, 0755, $recursive))
					throw new PhpCacheException("Could not create directory \"{$path}\".");

				// set correct permissions 
				if ($this->directoryPermission && !@chmod($path, $this->directoryPermission))
					throw new PhpCacheException("Failed to set directory permission for \"{$path}\".");
			}
		}

		/**
		 * Ensures the given file has correct permission
		 * @param string $path The path
		 */
		protected function ensureFilePermission(string $path): void {

			if (is_null($this->filePermission) ||
			    intval(substr(sprintf('%o', fileperms($path)), -4), 8) == $this->filePermission) {
				return;
			}

			if (!@chmod($path, $this->filePermission))
				throw new PhpCacheException("Failed to set file permission for \"{$path}\".");
		}

		/**
		 * Creates a generator for all files in the given directory recursively
		 * @param string $directory The directory
		 * @param string|null $skipSubDirectory Skips sub directories with given name
		 * @return Generator|SplFileInfo[] The files
		 */
		protected function filesInDirectory(string $directory, string $skipSubDirectory = null) {

			$items = new FilesystemIterator($directory);

			foreach ($items as $item) {

				if ($item->isDir() && !$item->isLink()) {
					
					if ($item->getBasename() !== $skipSubDirectory) {

						// iterate sub directory
						yield from $this->filesInDirectory($item->getPathname());
					}
					
				}
				else {
					yield $item;
				}
			}

		}


	}