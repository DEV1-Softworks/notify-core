<?php

namespace Dev1\NotifyCore\Tests\Auth;

use Dev1\NotifyCore\Auth\GoogleServiceAccountTokenProvider;
use Dev1\NotifyCore\Auth\TransientAuthException;
use Dev1\NotifyCore\Tests\Support\FakeHttpClient;
use Dev1\NotifyCore\Tests\Support\InMemoryCache;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class GoogleServiceAccountTokenProviderTest extends TestCase
{
    /** @var Psr17Factory */
    private $psr17;

    /** @var string */
    private static $privateKey;

    public static function setUpBeforeClass(): void
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $pem);
        self::$privateKey = $pem;
    }

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testRequiresEmailAndKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GoogleServiceAccountTokenProvider(
            new FakeHttpClient(new Response(200, [], '{}')),
            $this->psr17,
            $this->psr17,
            null,
            []
        );
    }

    public function testSuccessfulTokenIsCachedInMemory(): void
    {
        $http = new FakeHttpClient(new Response(200, [], json_encode(['access_token' => 'tok-1', 'expires_in' => 3600])));
        $provider = $this->makeProvider($http);

        $this->assertSame('tok-1', $provider->getToken());
        $this->assertSame('tok-1', $provider->getToken());
        $this->assertSame(1, $http->callCount, 'In-memory cache should prevent a second round-trip');
    }

    public function testPsr16CacheIsPopulatedAndReused(): void
    {
        $cache = new InMemoryCache();

        $http1 = new FakeHttpClient(new Response(200, [], json_encode(['access_token' => 'tok-cached', 'expires_in' => 3600])));
        $p1 = $this->makeProvider($http1, [], $cache);
        $this->assertSame('tok-cached', $p1->getToken());
        $this->assertSame(1, $cache->writes);

        // Fresh instance shares the cache; no HTTP call should be made.
        $http2 = new FakeHttpClient(new Response(500, [], 'should-not-be-called'));
        $p2 = $this->makeProvider($http2, [], $cache);
        $this->assertSame('tok-cached', $p2->getToken());
        $this->assertSame(0, $http2->callCount);
    }

    public function testRetriesOn5xxThenSucceeds(): void
    {
        $http = new FakeHttpClient([
            new Response(503, [], 'unavailable'),
            new Response(200, [], json_encode(['access_token' => 'tok-retry', 'expires_in' => 3600])),
        ]);
        $provider = $this->makeProvider($http, ['max_retries' => 2, 'retry_base_delay_ms' => 0]);

        $this->assertSame('tok-retry', $provider->getToken());
        $this->assertSame(2, $http->callCount);
    }

    public function testRetriesOnTransportExceptionThenSucceeds(): void
    {
        $transient = new class('boom') extends \RuntimeException implements ClientExceptionInterface {};

        $http = new FakeHttpClient([
            $transient,
            new Response(200, [], json_encode(['access_token' => 'tok-after-transport', 'expires_in' => 3600])),
        ]);
        $provider = $this->makeProvider($http, ['max_retries' => 1, 'retry_base_delay_ms' => 0]);

        $this->assertSame('tok-after-transport', $provider->getToken());
        $this->assertSame(2, $http->callCount);
    }

    public function testExhaustedRetriesThrowTransient(): void
    {
        $http = new FakeHttpClient([
            new Response(500, [], 'fail-1'),
            new Response(500, [], 'fail-2'),
            new Response(500, [], 'fail-3'),
        ]);
        $provider = $this->makeProvider($http, ['max_retries' => 2, 'retry_base_delay_ms' => 0]);

        $this->expectException(TransientAuthException::class);
        try {
            $provider->getToken();
        } finally {
            $this->assertSame(3, $http->callCount);
        }
    }

    public function testNon5xxErrorDoesNotRetry(): void
    {
        $http = new FakeHttpClient([
            new Response(400, [], json_encode(['error' => 'invalid_grant'])),
            new Response(200, [], json_encode(['access_token' => 'never-reached'])),
        ]);
        $provider = $this->makeProvider($http, ['max_retries' => 3, 'retry_base_delay_ms' => 0]);

        $this->expectException(\RuntimeException::class);
        try {
            $provider->getToken();
        } finally {
            $this->assertSame(1, $http->callCount, 'Client errors must not trigger retries');
        }
    }

    /**
     * @param array<string,mixed> $extraConfig
     */
    private function makeProvider(FakeHttpClient $http, array $extraConfig = [], ?InMemoryCache $cache = null): GoogleServiceAccountTokenProvider
    {
        $config = array_merge([
            'client_email' => 'sa@example.iam.gserviceaccount.com',
            'private_key' => self::$privateKey,
        ], $extraConfig);

        return new GoogleServiceAccountTokenProvider(
            $http,
            $this->psr17,
            $this->psr17,
            null,
            $config,
            $cache
        );
    }
}
