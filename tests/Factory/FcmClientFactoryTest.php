<?php

namespace Dev1\NotifyCore\Tests\Factory;

use Dev1\NotifyCore\Drivers\FcmHttpV1Client;
use Dev1\NotifyCore\Factory\FcmClientFactory;
use Dev1\NotifyCore\Tests\Support\FakeHttpClient;
use Dev1\NotifyCore\Tests\Support\StaticTokenProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class FcmClientFactoryTest extends TestCase
{
    public function testCreateReturnsFcmHttpV1Client(): void
    {
        $psr17 = new Psr17Factory();
        $client = FcmClientFactory::create(
            new FakeHttpClient(new Response(200, [], '{}')),
            $psr17,
            $psr17,
            new StaticTokenProvider(),
            null,
            ['project_id' => 'demo']
        );

        $this->assertInstanceOf(FcmHttpV1Client::class, $client);
    }

    public function testCreatePropagatesConfigValidation(): void
    {
        $psr17 = new Psr17Factory();
        $this->expectException(\InvalidArgumentException::class);
        FcmClientFactory::create(
            new FakeHttpClient(new Response(200, [], '{}')),
            $psr17,
            $psr17,
            new StaticTokenProvider(),
            null,
            []
        );
    }
}
