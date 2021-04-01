<?php


	namespace MehrItPhpCacheTest\Unit;


	use Cache\IntegrationTests\SimpleCacheTest;
	use MehrIt\PhpCache\PhpCache;
	use MehrItPhpCacheTest\CreatesTestDirectories;

	class SimplePhpCacheTest extends SimpleCacheTest
	{
		use CreatesTestDirectories;
		
		/**
		 * @inheritDoc
		 */
		public function createSimpleCache() {
			
			$tmp = $this->createTempDir();
			
			return new PhpCache($tmp . '/cache');			
		}


	}