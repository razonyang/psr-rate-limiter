<?php
namespace RazonYang\Psr\RateLimiter\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use RazonYang\Psr\RateLimiter\Middleware;
use RazonYang\TokenBucket\Manager\MemcachedManager;
use RazonYang\TokenBucket\ManagerInterface;

class MiddlewareTest extends TestCase
{
    private $httpFactory;

    public function setUp(): void
    {
        parent::setUp();

        $this->httpFactory =  new Psr17Factory();
    }

    public function tearDown(): void
    {
        $this->httpFactory = null;

        parent::tearDown();
    }

    private function createManager(int $capacity, float $rate): ManagerInterface
    {
        $memcached = new \Memcached();
        $memcached->addServer('localhost', 11211);
        $manager = new MemcachedManager($capacity, $rate, new NullLogger(), $memcached);
        return $manager;
    }

    private function createNameCallback(string $name): \Closure
    {
        return function (ServerRequestInterface $request) use ($name): string {
            return $name;
        };
    }

    private function createRateLimiter(int $capacity, float $rate, \Closure $nameCallback): Middleware
    {
        return new Middleware($this->createManager($capacity, $rate), $this->httpFactory, $nameCallback);
    }

    /**
     * @dataProvider dataLimitPeriod
     */
    public function testSetLimitPeriod(int $period): void
    {
        $limiter = $this->createRateLimiter(1, 1, $this->createNameCallback(''));
        $limiter->setLimitPeriod($period);
        $property = new \ReflectionProperty(Middleware::class, 'limitPeriod');
        $property->setAccessible(true);
        $this->assertSame($period, $property->getValue($limiter));
    }

    public function dataLimitPeriod(): array
    {
        return [
            [60],
            [3600],
        ];
    }
    
    public function testGetResponseFactory(): void
    {
        $limiter = $this->createRateLimiter(1, 1, $this->createNameCallback(''));
        $method = new \ReflectionMethod(Middleware::class, 'getResponseFactory');
        $method->setAccessible(true);
        $this->assertSame($this->httpFactory, $method->invoke($limiter));
    }

    public function testProcess(): void
    {
        $name = uniqid();
        $limiter = $this->createRateLimiter(1, 60, $this->createNameCallback($name));
        
        $request = $this->httpFactory->createServerRequest('GET', '/');

        $handler = $this->createHandler();
        $response = $limiter->process($request, $handler);
        $this->assertTrue($handler->isHandled());
        $this->assertSame(200, $response->getStatusCode());
        $headers = ['X-Rate-Limit-Limit', 'X-Rate-Limit-Remaining', 'X-Rate-Limit-Reset'];
        foreach ($headers as $header) {
            $this->assertTrue($response->hasHeader($header));
        }

        $handler2 = $this->createHandler();
        $response = $limiter->process($request, $handler2);
        $this->assertFalse($handler2->isHandled());
        $this->assertSame(429, $response->getStatusCode());
        foreach ($headers as $header) {
            $this->assertTrue($response->hasHeader($header));
        }
    }

    public function testProcessSkip(): void
    {
        $name = '';
        $limiter = $this->createRateLimiter(1, 60, $this->createNameCallback($name));
        
        $request = $this->httpFactory->createServerRequest('GET', 'http://localhost');

        $handler = $this->createHandler();
        $response = $limiter->process($request, $handler);
        $this->assertTrue($handler->isHandled());
        $this->assertSame(200, $response->getStatusCode());
        $headers = ['X-Rate-Limit-Limit', 'X-Rate-Limit-Remaining', 'X-Rate-Limit-Reset'];
        foreach ($headers as $header) {
            $this->assertFalse($response->hasHeader($header));
        }
    }

    private function createHandler()
    {
        return new class($this->httpFactory) implements RequestHandlerInterface {
            /**
             * @var ResponseFactoryInterface $factory
             */
            private $factory;

            private $handled = false;

            public function isHandled(): bool
            {
                return $this->handled === true;
            }

            public function __construct($factory)
            {
                $this->factory = $factory;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handled = true;
                return $this->factory->createResponse(200);
            }
        };
    }
}
