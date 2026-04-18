<?php

namespace Dev1\NotifyCore\Tests\Drivers;

use Dev1\NotifyCore\Drivers\FcmHttpV1Client;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Tests\Support\FakeHttpClient;
use Dev1\NotifyCore\Tests\Support\StaticTokenProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class FcmHttpV1ClientTest extends TestCase
{
    /** @var Psr17Factory */
    private $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testConstructorRequiresProjectId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FcmHttpV1Client(
            new FakeHttpClient(new Response(200, [], '{}')),
            $this->psr17,
            $this->psr17,
            new StaticTokenProvider(),
            null,
            []
        );
    }

    public function testEndpointInterpolatesProjectId(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"projects/p/messages/abc"}'));
        $client = $this->makeClient($http);

        $client->send(new PushMessage('t', 'b'), new PushTarget('device-token'));

        $this->assertNotNull($http->lastRequest);
        $this->assertSame(
            'https://fcm.googleapis.com/v1/projects/demo/messages:send',
            (string) $http->lastRequest->getUri()
        );
        $this->assertSame('Bearer test-token', $http->lastRequest->getHeaderLine('Authorization'));
        $this->assertStringStartsWith('Dev1-Notify-Core/', $http->lastRequest->getHeaderLine('User-Agent'));
    }

    public function testSuccessfulSendReturnsId(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"projects/p/messages/abc"}'));
        $client = $this->makeClient($http);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertTrue($result->success);
        $this->assertSame('projects/p/messages/abc', $result->id);
        $this->assertNull($result->errorCode);
    }

    public function testFailureMapsFcmErrorStatus(): void
    {
        $body = json_encode([
            'error' => [
                'status' => 'UNREGISTERED',
                'message' => 'Requested entity was not found.',
            ],
        ]);
        $http = new FakeHttpClient(new Response(404, [], $body));
        $client = $this->makeClient($http);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertFalse($result->success);
        $this->assertSame('UNREGISTERED', $result->errorCode);
        $this->assertSame('Requested entity was not found.', $result->errorMessage);
    }

    public function testDataOnlyPushOmitsNotificationBlock(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $client->send(
            new PushMessage('', '', ['k' => 'v']),
            new PushTarget('tok')
        );

        $payload = json_decode($http->lastBody, true);
        $this->assertArrayNotHasKey('notification', $payload['message']);
        $this->assertSame(['k' => 'v'], $payload['message']['data']);
    }

    public function testDataValuesAreStringified(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $client->send(
            new PushMessage('t', 'b', ['n' => 123, 'b' => true, 'arr' => ['x' => 1]]),
            new PushTarget('tok')
        );

        $payload = json_decode($http->lastBody, true);
        $this->assertSame('123', $payload['message']['data']['n']);
        $this->assertSame('1', $payload['message']['data']['b']);
        $this->assertSame('{"x":1}', $payload['message']['data']['arr']);
    }

    public function testTargetPrefersToken(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $payload = json_decode($http->lastBody, true);
        $this->assertSame('tok', $payload['message']['token']);
        $this->assertArrayNotHasKey('topic', $payload['message']);
    }

    public function testAndroidOptionsAreSerialized(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $android = AndroidOptions::make()
            ->withChannelId('chan-1')
            ->withPriority('high')
            ->withTtl(120)
            ->withCollapseKey('k');

        $message = new PushMessage('t', 'b', null, ['android' => $android]);
        $client->send($message, new PushTarget('tok'));

        $payload = json_decode($http->lastBody, true);
        $this->assertSame('HIGH', $payload['message']['android']['priority']);
        $this->assertSame('120s', $payload['message']['android']['ttl']);
        $this->assertSame('k', $payload['message']['android']['collapse_key']);
        $this->assertSame('chan-1', $payload['message']['android']['notification']['channel_id']);
    }

    public function testTransportExceptionReturnsTransportErrorResult(): void
    {
        $exception = new class('boom') extends \RuntimeException implements ClientExceptionInterface {};
        $http = new FakeHttpClient($exception);
        $client = $this->makeClient($http, ['max_retries' => 0]);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertFalse($result->success);
        $this->assertSame('TRANSPORT_ERROR', $result->errorCode);
        $this->assertSame('boom', $result->errorMessage);
    }

    /**
     * @param array<string,mixed> $extraConfig
     */
    private function makeClient(FakeHttpClient $http, array $extraConfig = []): FcmHttpV1Client
    {
        return new FcmHttpV1Client(
            $http,
            $this->psr17,
            $this->psr17,
            new StaticTokenProvider('test-token'),
            null,
            array_merge(['project_id' => 'demo'], $extraConfig)
        );
    }
}
