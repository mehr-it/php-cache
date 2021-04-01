<?php


	namespace MehrItPhpCacheTest\Unit;


	use InvalidArgumentException;
	use MehrIt\PhpCache\PhpCache;
	use MehrItPhpCacheTest\CreatesTestDirectories;
	use PHPUnit\Framework\TestCase;

	class PhpCacheTest extends TestCase
	{

		use CreatesTestDirectories;
		
		public function testSet_withoutStrictKeyValidation() {

			$tmp = $this->createTempDir();
			
			$cache = new PhpCache($tmp . '/cache', null, null, false);
			
			$this->assertTrue($cache->set('key:1', 'value'));
			$this->assertSame('value', $cache->get('key:1'));
			
		}
		
		public function testLock() {

			$tmp = $this->createTempDir();

			$cache = new PhpCache($tmp . '/cache');
			
			$l1 = $cache->lock('my_lock', LOCK_SH);
			
			$this->assertSame($l1, $l1->setMode(LOCK_EX));
			
			$this->assertSame($l1, $l1->release());
			$this->assertSame($l1, $l1->release());
						
		}
		
		
		public function testLock_withInvalidName() {

			$tmp = $this->createTempDir();

			$cache = new PhpCache($tmp . '/cache');
			
			
			$this->expectException(InvalidArgumentException::class);
			
			$cache->lock('invalidName:', LOCK_SH);
						
		}
		
		public function testLock_withInvalidName_tooLong() {

			$tmp = $this->createTempDir();

			$cache = new PhpCache($tmp . '/cache');
			
			
			$this->expectException(InvalidArgumentException::class);
			
			$cache->lock(str_repeat('a', 65), LOCK_SH);
						
		}
		
		
		
	}