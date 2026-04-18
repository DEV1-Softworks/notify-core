<?php

namespace Dev1\NotifyCore\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Queue-driven PSR-18 client for tests. Accepts either a single response/throwable
 * or an ordered list; each sendRequest() pops the next entry from the queue.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var array<int, ResponseInterface|\Throwable> */
    private $queue;

    /** @var RequestInterface|null */
    public $lastRequest = null;

    /** @var string|null */
    public $lastBody = null;

    /** @var int */
    public $callCount = 0;

    /** @var array<int, string> */
    public $capturedBodies = [];

    /**
     * @param ResponseInterface|\Throwable|array<int, ResponseInterface|\Throwable> $responses
     */
    public function __construct($responses)
    {
        $this->queue = is_array($responses) ? array_values($responses) : [$responses];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->callCount++;
        $this->lastRequest = $request;
        $this->lastBody = (string) $request->getBody();
        $this->capturedBodies[] = $this->lastBody;

        if (empty($this->queue)) {
            throw new \RuntimeException('FakeHttpClient: no more responses queued');
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            if ($next instanceof ClientExceptionInterface) {
                throw $next;
            }
            throw $next;
        }

        return $next;
    }
}
