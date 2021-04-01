<?php


	namespace MehrIt\PhpCache\Exception;


	use Psr\SimpleCache\CacheException;
	use RuntimeException;

	class PhpCacheException extends RuntimeException implements CacheException
	{

	}