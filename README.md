# PHP file cache (PSR-16)
Caching library using PHP files as backend implementing 
PSR-16.

## Introduction
Thanks to opcache, using PHP files as cache backend improves
performance as it benefits from PHPs bytecode cache.

This library is aware of opcache and also invalidates opcache
whenever a file is changed.

**ATTENTION:**
Since opcache is not shared between different PHP SAPIs, 
cache data and locks are independent for each SAPI. 
Even clearing the cache will only invalidate the cache 
for the current SAPI.

This library uses [flock()](https://www.php.net/manual/en/function.flock.php) for locking.


## Installation 

Use composer:
    
    composer require mehr-it/php-cache


## Usage

The constructor of `PhpCache` has the following arguments:

    new PhpCache(

        // The directory to store all cache data and locks
        // in. Will be created if not existing.
        $baseDir,

        // Ensures that all created files have the given
        // permission, eg. 0660 (optional)
        $filePermission,

        // Ensures that all created directories have the 
        // given permission, eg. 0770 (optional)
        $directoryPermission,

        // If set to false, keys are not checked to special 
        // chars according to PSR-16. This improves 
        // performance.
        $strictKeyValidation
    )

## Locks

In addition to the PSR-16 interface, the cache implements
the ability to create named locks:

    $cache = new PhpCache('/tmp/cache');

    // create a lock
    $lock = $cache->lock('my_lock', LOCK_SH);

    // change locking mode, if needed
    $lock->setMode(LOCK_EX);

    // release lock
    $lock->release();