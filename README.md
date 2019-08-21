PSR Rate Limiter Middleware
===========================

[![Build Status](https://travis-ci.org/razonyang/psr-rate-limiter.svg?branch=master)](https://travis-ci.org/razonyang/psr-rate-limiter)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/razonyang/psr-rate-limiter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/razonyang/psr-rate-limiter/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/razonyang/psr-rate-limiter/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/razonyang/psr-rate-limiter/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/razonyang/psr-rate-limiter.svg)](https://packagist.org/packages/razonyang/psr-rate-limiter)
[![Total Downloads](https://img.shields.io/packagist/dt/razonyang/psr-rate-limiter.svg)](https://packagist.org/packages/razonyang/psr-rate-limiter)
[![LICENSE](https://img.shields.io/github/license/razonyang/psr-rate-limiter)](LICENSE)

It was built on top of [Token Bucket](https://github.com/razonyang/php-token-bucket).

Installation
------------

```
composer require razonyang/psr-rate-limiter
```

Usage
-----

Let's take 5000 requests every hours as example:

```php
// creates a token bucket manager, redis or memcached(built-in)
$capacity = 5000; // each bucket capacity, in other words, maximum number of tokens.
$rate = 0.72; // 3600/5200, how offen the token will be added to bucket
$logger = new \Psr\Log\NullLogger(); // PSR logger
$ttl = 3600; // time to live.
$prefix = 'rateLimiter:'; // prefix.
$manager = new \RazonYang\TokenBucket\Manager\RedisManager($capacity, $rate, $logger, $redis, $ttl, $prefix);

// PSR HTTP response factory
$responseFactory = new \Nyholm\Psr7\Factory\Psr17Factory();

// bucket name callback, let's we treat ip:path as bucket name here
$nameCallback = function (\Psr\Http\Message\ServerRequestInterface $request): string {
    $parameters = $request->getServerParams();
    $path = '';
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($parameters[$key])) {
            return $parameters[$key] . ':' . $request->getUri()->getPath();
        }
    }

    // rate limiting will be skipped when an empty bucket name returned
    return '';
};

$rateLimiter = new \RazonYang\Psr\RateLimiter\Middleware($manager, $responseFactory, $nameCallback);
```
