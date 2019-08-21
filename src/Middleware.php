<?php
namespace RazonYang\Psr\RateLimiter;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RazonYang\TokenBucket\ManagerInterface;

class Middleware implements MiddlewareInterface
{
    /**
     * @var ManagerInterface
     */
    private $manager;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * Returns response factory.
     *
     * @return ResponseFactoryInterface
     */
    protected function getResponseFactory(): ResponseFactoryInterface
    {
        return $this->responseFactory;
    }

    /**
     * @var \Closure $nameCallback the bucket name callback that accepts request parameter,
     * and returns bucket name, returns empty string for skipping rate limiting.
     */
    private $nameCallback;

    /**
     * @var int $limitPeriod calculates maximum count of requests during the period.
     */
    private $limitPeriod = 3600;

    /**
     * Sets limit period.
     *
     * @param int $period
     */
    public function setLimitPeriod(int $period)
    {
        $this->limitPeriod = $period;
    }

    /**
     * @param ManagerInterface $manager token bucket manager.
     * @param ResponseFactoryInterface $responseFactory response factory.
     * @param \Closure $nameCallback bucket name callback.
     */
    public function __construct(ManagerInterface $manager, ResponseFactoryInterface $responseFactory, \Closure $nameCallback)
    {
        $this->manager = $manager;
        $this->nameCallback = $nameCallback;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $name = call_user_func($this->nameCallback, $request);
        if ($name === '') {
            return $handler->handle($request);
        }
        
        $consumed = $this->manager->consume($name, $remaining, $reset);
        $limit = $this->manager->getLimit($this->limitPeriod);
        if (!$consumed) {
            $response = $this->createTooManyRequestResponse();
        } else {
            $response = $handler->handle($request);
        }

        return $this->addHeaders($response, $limit, $remaining, $reset);
    }

    /**
     * Creates a response about too many request.
     *
     * @return ResponseInterface
     */
    protected function createTooManyRequestResponse(): ResponseInterface
    {
        return $this->responseFactory->createResponse(429, 'Too Many Requests');
    }
    
    /**
     * Adds rate limiting headers.
     *
     * @param ResponseInterface $response
     * @param int               $limit
     * @param int               $remaining
     * @param int               $reset
     *
     * @return ResponseInterface
     */
    protected function addHeaders(ResponseInterface $response, int $limit, int $remaining, int $reset): ResponseInterface
    {
        /** @var Psr\Http\Message\ResponseInterface $response */
        return $response->withHeader('X-Rate-Limit-Limit', $limit)
            ->withHeader('X-Rate-Limit-Remaining', $remaining)
            ->withHeader('X-Rate-Limit-Reset', $reset);
    }
}
