<?php

namespace Dev1\NotifyCore\Tests\Drivers;

use Dev1\NotifyCore\Drivers\FcmHttpV1Client;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Tests\Support\FakeHttpClient;
use Dev1\NotifyCore\Tests\Support\StaticTokenProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class FcmHttpV1ClientRetryTest extends TestCase
{
    /** @var Psr17Factory */
    private $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testRetriesOn503ThenSucceeds(): void
    {
        $http = new FakeHttpClient([
            new Response(503, [], 'unavailable'),
            new Response(200, [], '{"name":"projects/p/messages/ok"}'),
        ]);
        $client = $this->makeClient($http, ['max_retries' => 2, 'retry_base_delay_ms' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertTrue($result->success);
        $this->assertSame('projects/p/messages/ok', $result->id);
        $this->assertSame(2, $http->callCount);
    }

    public function testRetriesOnTransportException(): void
    {
        $transient = new class('down') extends \RuntimeException implements ClientExceptionInterface {};

        $http = new FakeHttpClient([
            $transient,
            new Response(200, [], '{"name":"projects/p/messages/ok"}'),
        ]);
        $client = $this->makeClient($http, ['max_retries' => 1, 'retry_base_delay_ms' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertTrue($result->success);
        $this->assertSame(2, $http->callCount);
    }

    public function testRetriesOn429(): void
    {
        $http = new FakeHttpClient([
            new Response(429, [], json_encode([
                'error' => ['status' => 'RESOURCE_EXHAUSTED', 'message' => 'slow down'],
            ])),
            new Response(200, [], '{"name":"projects/p/messages/ok"}'),
        ]);
        $client = $this->makeClient($http, ['max_retries' => 1, 'retry_base_delay_ms' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertTrue($result->success);
        $this->assertSame(2, $http->callCount);
    }

    public function testExhaustedRetriesReturnsTransientResult(): void
    {
        $http = new FakeHttpClient([
            new Response(500, [], 'boom-1'),
            new Response(500, [], 'boom-2'),
            new Response(500, [], 'boom-3'),
        ]);
        $client = $this->makeClient($http, ['max_retries' => 2, 'retry_base_delay_ms' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertFalse($result->success);
        $this->assertSame('HTTP_500', $result->errorCode);
        $this->assertSame(3, $http->callCount);
    }

    public function testClientErrorDoesNotRetry(): void
    {
        $http = new FakeHttpClient([
            new Response(400, [], json_encode([
                'error' => [
                    'status' => 'INVALID_ARGUMENT',
                    'message' => 'bad token',
                ],
            ])),
            new Response(200, [], '{"name":"never"}'),
        ]);
        $client = $this->makeClient($http, ['max_retries' => 3, 'retry_base_delay_ms' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertFalse($result->success);
        $this->assertSame('INVALID_ARGUMENT', $result->errorCode);
        $this->assertTrue($result->isInvalidArgument());
        $this->assertSame(1, $http->callCount);
    }

    public function testFcmErrorCodeDetailOverridesStatus(): void
    {
        $body = json_encode([
            'error' => [
                'status' => 'NOT_FOUND',
                'message' => 'Requested entity was not found.',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ],
                ],
            ],
        ]);
        $http = new FakeHttpClient(new Response(404, [], $body));
        $client = $this->makeClient($http);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertSame('UNREGISTERED', $result->errorCode);
        $this->assertTrue($result->isUnregistered());
    }

    public function testPushResultTransientHelper(): void
    {
        $http = new FakeHttpClient([
            new Response(500, [], 'x'),
            new Response(500, [], 'x'),
            new Response(500, [], 'x'),
        ]);
        $client = $this->makeClient($http, ['max_retries' => 2, 'retry_base_delay_ms' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));
        $this->assertFalse($result->success);
        $this->assertSame('HTTP_500', $result->errorCode);
        $this->assertFalse($result->isTransient(), 'HTTP_500 is not mapped to the RPC transient set');
    }

    /**
     * @param array<string,mixed> $config
     */
    private function makeClient(FakeHttpClient $http, array $config = []): FcmHttpV1Client
    {
        return new FcmHttpV1Client(
            $http,
            $this->psr17,
            $this->psr17,
            new StaticTokenProvider('test-token'),
            null,
            array_merge(['project_id' => 'demo'], $config)
        );
    }
}
