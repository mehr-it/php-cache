<?php


	namespace MehrItPhpCacheTest\Unit;


	use Cache\IntegrationTests\SimpleCacheTest;
	use MehrIt\PhpCache\PhpCache;
	use MehrItPhpCacheTest\CreatesTestDirectories;

	class SimplePhpCacheWithPermissionTest extends SimpleCacheTest
	{
		use CreatesTestDirectories;

		/**
		 * @inheritDoc
		 */
		public function createSimpleCache() {

			$tmp = $this->createTempDir();

			return new PhpCache($tmp . '/cache', 0660, 0770);
		}

		
		protected function assertPermission($path, $permission) {
			if (!(intval(substr(sprintf('%o', fileperms($path)), -4), 8) == $permission))
				$this->fail("Permission for {$path} is " . substr(sprintf('%o', fileperms($path)), -4) . ' but expected 0' . sprintf('%o', $permission));
		}

		/**
		 * @after
		 */
		public function validateDirectoryPermission() {
			
			// here we validate, that all files in the cache directory have correct permission
			foreach($this->tmpDirectories as $curr) {

				if (file_exists($curr . '/cache')) {
					$this->assertPermission($curr . '/cache', 0770);

					$this->validatePermissions($curr . '/cache');
				}
			}
		}

		protected function validatePermissions($dir) {

			if (is_dir($dir)) {
				$objects = scandir($dir);
				foreach ($objects as $object) {
					if ($object != "." && $object != "..") {

						//echo $dir . "/" . $object . "\n";

						if (is_dir($dir . "/" . $object)) {
							$this->assertPermission($dir . "/" . $object, 0770);
							
							$this->validatePermissions($dir . "/" . $object);
						}
						else {
							$this->assertPermission($dir . "/" . $object, 0660);
						}
					}
				}
				
			}
			
		}
		
	}