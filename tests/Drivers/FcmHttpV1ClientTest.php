<?php

namespace Dev1\NotifyCore\Tests\Drivers;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;
use Dev1\NotifyCore\Drivers\FcmHttpV1Client;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;
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

    public function testTopicTargetSerializesAsTopic(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $client->send(new PushMessage('t', 'b'), new PushTarget(null, 'alerts'));

        $payload = json_decode($http->lastBody, true);
        $this->assertSame('alerts', $payload['message']['topic']);
        $this->assertArrayNotHasKey('token', $payload['message']);
    }

    public function testConditionTargetSerializesAsCondition(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $expr = "'alerts' in topics";
        $client->send(new PushMessage('t', 'b'), new PushTarget(null, null, $expr));

        $payload = json_decode($http->lastBody, true);
        $this->assertSame($expr, $payload['message']['condition']);
        $this->assertArrayNotHasKey('topic', $payload['message']);
    }

    public function testApnsPlatformOptionsAreSerialized(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $apns = ApnsOptions::make()
            ->withHeaders(['apns-priority' => '10'])
            ->withAps(['sound' => 'default']);

        $message = new PushMessage('t', 'b', null, ['apns' => $apns]);
        $client->send($message, new PushTarget('tok'));

        $payload = json_decode($http->lastBody, true);
        $this->assertSame('10', $payload['message']['apns']['headers']['apns-priority']);
        $this->assertSame('default', $payload['message']['apns']['body']['aps']['sound']);
    }

    public function testRawArrayOverridesArePassedThroughUntouched(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = $this->makeClient($http);

        $message = new PushMessage('t', 'b', null, [
            'android' => ['priority' => 'HIGH'],
            'apns' => ['headers' => ['apns-push-type' => 'alert']],
            'webpush' => ['headers' => ['TTL' => '60']],
        ]);
        $client->send($message, new PushTarget('tok'));

        $payload = json_decode($http->lastBody, true);
        $this->assertSame('HIGH', $payload['message']['android']['priority']);
        $this->assertSame('alert', $payload['message']['apns']['headers']['apns-push-type']);
        $this->assertSame('60', $payload['message']['webpush']['headers']['TTL']);
    }

    public function testTokenAcquisitionFailureReturnsTokenErrorResult(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"never"}'));
        $provider = new class implements AccessTokenProvider {
            public function getToken(): string
            {
                throw new \RuntimeException('no creds');
            }
        };

        $client = new FcmHttpV1Client(
            $http,
            $this->psr17,
            $this->psr17,
            $provider,
            null,
            ['project_id' => 'demo']
        );

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertFalse($result->success);
        $this->assertSame('TOKEN_ERROR', $result->errorCode);
        $this->assertSame('no creds', $result->errorMessage);
        $this->assertSame(0, $http->callCount, 'HTTP must not be called if token acquisition failed');
    }

    public function testNonJsonErrorBodyFallsBackToHttpStatusCode(): void
    {
        $http = new FakeHttpClient(new Response(403, [], 'Forbidden (not JSON)'));
        $client = $this->makeClient($http);

        $result = $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertFalse($result->success);
        $this->assertSame('HTTP_403', $result->errorCode);
        $this->assertSame('Forbidden (not JSON)', $result->errorMessage);
    }

    public function testCustomEndpointInterpolatesProjectId(): void
    {
        $http = new FakeHttpClient(new Response(200, [], '{"name":"x"}'));
        $client = new FcmHttpV1Client(
            $http,
            $this->psr17,
            $this->psr17,
            new StaticTokenProvider('test-token'),
            null,
            [
                'project_id' => 'demo',
                'endpoint' => 'https://example.test/v1/projects/{project_id}/messages:send',
            ]
        );

        $client->send(new PushMessage('t', 'b'), new PushTarget('tok'));

        $this->assertSame(
            'https://example.test/v1/projects/demo/messages:send',
            (string) $http->lastRequest->getUri()
        );
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
