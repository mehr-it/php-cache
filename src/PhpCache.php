<?php /** @noinspection PhpMissingReturnTypeInspection */


	namespace MehrIt\PhpCache;


	use DateInterval;
	use DateTime;
	use MehrIt\PhpCache\Concerns\DataEntries;
	use MehrIt\PhpCache\Concerns\Filesystem;
	use MehrIt\PhpCache\Concerns\Locking;
	use MehrIt\PhpCache\Exception\PhpCacheInvalidArgumentException;
	use Psr\SimpleCache\CacheInterface;
	use Traversable;

	class PhpCache implements CacheInterface
	{
		use Locking;
		use DataEntries;
		use Filesystem;

		const LOCKS_DIR = 'lock';

		/**
		 * @var string|null
		 */
		private static $sapiName;


		/**
		 * @var string
		 */
		protected $baseDir;


		/**
		 * @var bool
		 */
		protected $directoriesInit = false;

		/**
		 * @var bool
		 */
		protected $strictKeyValidation;


		/**
		 * Creates a new cache
		 * @param string $baseDir The base directory
		 * @param int|null $filePermission The file permission to ensure
		 * @param int|null $directoryPermission The directory permission to ensure
		 * @param bool $strictKeyValidation If false, keys are not checked to special chars according to PSR-16
		 */
		public function __construct(string $baseDir, int $filePermission = null, int $directoryPermission = null, bool $strictKeyValidation = true) {
			
			$this->baseDir = rtrim($baseDir, '/');

			$this->filePermission      = $filePermission;
			$this->directoryPermission = $directoryPermission;
			$this->strictKeyValidation = $strictKeyValidation;

			// determine SAPI
			if (self::$sapiName === null)
				self::$sapiName = php_sapi_name();
		}


		/**
		 * Create a named lock
		 * @param string $name The name
		 * @param int $mode The mode
		 * @return FileLock
		 */
		public function lock(string $name, int $mode): FileLock {
			return $this->namedLock($name, $mode);
		}


		/**
		 * @inheritDoc
		 */
		public function get($key, $default = null) {

			$this->validateKey($key);

			[$path, $filename] = $this->getFilename($key);

			return $this->withFileItemLock($filename, LOCK_SH, function (FileLock $lock) use ($key, $default, $path, $filename) {

				// read file content
				$data = $this->readFile($path, $filename);

				// extract value
				$hit         = false;
				$shouldClean = false;
				$value       = $this->getValue($data, $key, $hit, $shouldClean);


				// is there data to cleanup, so we can save disk space?
				if ($shouldClean) {

					// convert to exclusive lock
					$lock->setMode(LOCK_EX);

					// remove value
					$this->removeValue($data, $key);

					// save
					$this->writeFile($path, $filename, $data);
				}


				return $hit ? $value : $default;
			});

		}

		/**
		 * @inheritDoc
		 */
		public function set($key, $value, $ttl = null) {

			$this->validateKey($key);


			$expiresAt = 0;
			if ($ttl instanceof DateInterval) {
				$now = new DateTime();

				$expiresAt = $now->add($ttl)->getTimestamp();
			}
			else {

				switch (gettype($ttl)) {
					case 'integer';
						$expiresAt = time() + $ttl;
						break;

					case 'NULL';
						break;

					default:
						throw new PhpCacheInvalidArgumentException('Invalid TTL of type ' . gettype($ttl) . ' given');
				}

			}


			[$path, $filename] = $this->getFilename($key);

			$this->withFileItemLock($filename, LOCK_EX, function () use ($key, $value, $expiresAt, $path, $filename) {

				// read file content
				$data = $this->readFile($path, $filename);

				// set value
				$this->setValue($data, $key, $value, $expiresAt);

				// save
				$this->writeFile($path, $filename, $data);
			});

			return true;
		}

		/**
		 * @inheritDoc
		 */
		public function delete($key) {

			$this->validateKey($key);

			[$path, $filename] = $this->getFilename($key);

			$this->withFileItemLock($filename, LOCK_EX, function () use ($key, $path, $filename) {

				// read file content
				$data = $this->readFile($path, $filename);

				// set value
				$this->removeValue($data, $key);

				// save
				$this->writeFile($path, $filename, $data);
			});

			return true;
		}

		/**
		 * @inheritDoc
		 */
		public function has($key) {

			$this->validateKey($key);

			[$path, $filename] = $this->getFilename($key);

			return $this->withFileItemLock($filename, LOCK_SH, function () use ($key, $path, $filename) {


				// read file content
				$data = $this->readFile($path, $filename);

				return $this->hasValue($data, $key);
			});
		}

		/**
		 * @inheritDoc
		 */
		public function clear() {

			$this->initDirectoryStructure();

			// iterator for all files (except lock files)
			$files = $this->filesInDirectory($this->baseDir(), self::LOCKS_DIR);

			foreach ($files as $currFile) {

				// lock the file and delete it
				$this->withFileItemLock($currFile->getBasename(), LOCK_EX, function () use ($currFile) {
					$this->deleteFile($currFile->getPathname());
				});
			}

			return true;
		}

		/**
		 * @inheritDoc
		 */
		public function getMultiple($keys, $default = null) {

			if (!is_array($keys) && !($keys instanceof Traversable))
				throw new PhpCacheInvalidArgumentException('Keys must either be an array or a traversable');

			$ret = [];

			foreach ($keys as $currKey) {
				$ret[$currKey] = $this->get($currKey, $default);
			}

			return $ret;
		}

		/**
		 * @inheritDoc
		 */
		public function setMultiple($values, $ttl = null) {

			if (!is_array($values) && !($values instanceof Traversable))
				throw new PhpCacheInvalidArgumentException('Values must either be an array or a traversable');

			foreach ($values as $key => $value) {

				if (is_int($key))
					$key = (string)$key;

				$this->set($key, $value, $ttl);
			}

			return true;
		}

		/**
		 * @inheritDoc
		 */
		public function deleteMultiple($keys) {

			if (!is_array($keys) && !($keys instanceof Traversable))
				throw new PhpCacheInvalidArgumentException('Keys must either be an array or a traversable');

			foreach ($keys as $currKey) {
				$this->delete($currKey);
			}

			return true;
		}


		/**
		 * Gets the file name for the given key
		 * @param string $key The key
		 * @return array The path segments and the file name
		 */
		protected function getFilename(string $key): array {

			$keyHash = sha1($key);

			return [
				[
					substr($keyHash, 0, 2),
					substr($keyHash, 2, 2),
				],
				"{$keyHash}.php"
			];

		}

		/**
		 * @inheritDoc
		 */
		protected function baseDir(): string {
			return $this->baseDir . '/' . self::$sapiName;
		}

		/**
		 * @inheritDoc
		 */
		protected function getLocksDirectory(): string {
			return $this->baseDir() . '/' . self::LOCKS_DIR;
		}

		/**
		 * @inheritDoc
		 */
		protected function initDirectoryStructure(): void {

			if (!$this->directoriesInit) {

				// base dir
				$this->ensureDirectoryExists($this->baseDir, true);
				$this->ensureDirectoryExists($this->baseDir . '/' . self::$sapiName, true);

				// locks directory
				$this->ensureSubTreeExists([self::LOCKS_DIR]);


				$this->directoriesInit = true;
			}
		}


		/**
		 * Validates the given key
		 * @param mixed $key The key
		 */
		protected function validateKey($key): void {
			if (gettype($key) !== 'string' || $key === '')
				throw new PhpCacheInvalidArgumentException('Invalid cache key given');

			if ($this->strictKeyValidation) {
				$key = (string)$key;
				foreach (['{', '}', '(', ')', '/', '\\', '@', ':'] as $curr) {
					if (strpos($key, $curr) !== false)
						throw new PhpCacheInvalidArgumentException("Invalid cache key \"{$key}\" given");
				}
			}
		}
	}