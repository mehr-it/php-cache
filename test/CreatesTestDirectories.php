<?php


	namespace MehrItPhpCacheTest;
	

	trait CreatesTestDirectories
	{
		protected $tmpDirectories = [];
		

		protected function createTempDir() {
			$path = sys_get_temp_dir() . uniqid('/PhpUnitTest_');

			mkdir($path);

			$this->tmpDirectories[] = $path;

			return $path;
		}

		protected function rrmdir($dir) {
			if (is_dir($dir)) {
				$objects = scandir($dir);
				foreach ($objects as $object) {
					if ($object != "." && $object != "..") {
						if (is_dir($dir . "/" . $object))
							$this->rrmdir($dir . "/" . $object);
						else
							unlink($dir . "/" . $object);
					}
				}
				rmdir($dir);
			}
		}

		/**
		 * @after
		 */
		public function clearTempDirectories() {
			foreach ($this->tmpDirectories as $curr) {
				$this->rrmdir($curr);
			}
		}
	}