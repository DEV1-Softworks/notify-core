<?php

namespace Dev1\NotifyCore\Tests\Registry;

use Dev1\NotifyCore\Contracts\PushClient;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Registry\ClientRegistry;
use PHPUnit\Framework\TestCase;

final class ClientRegistryTest extends TestCase
{
    public function testFirstRegisteredBecomesDefault(): void
    {
        $registry = new ClientRegistry();
        $a = $this->makeClient();
        $b = $this->makeClient();

        $registry->register('a', $a);
        $registry->register('b', $b);

        $this->assertSame('a', $registry->defaultName());
        $this->assertSame($a, $registry->client());
        $this->assertSame($b, $registry->client('b'));
    }

    public function testAsDefaultSwitchesDefault(): void
    {
        $registry = new ClientRegistry();
        $registry->register('a', $this->makeClient());
        $b = $this->makeClient();
        $registry->register('b', $b, true);

        $this->assertSame('b', $registry->defaultName());
        $this->assertSame($b, $registry->client());
    }

    public function testUnknownClientThrows(): void
    {
        $registry = new ClientRegistry();
        $this->expectException(\RuntimeException::class);
        $registry->client('missing');
    }

    public function testRemoveClearsDefault(): void
    {
        $registry = new ClientRegistry();
        $registry->register('a', $this->makeClient());
        $this->assertTrue($registry->remove('a'));

        $this->assertFalse($registry->has('a'));
        $this->assertNull($registry->defaultName());
    }

    public function testRemoveUnknownReturnsFalse(): void
    {
        $registry = new ClientRegistry();
        $this->assertFalse($registry->remove('missing'));
    }

    public function testSetDefaultSwitchesDefault(): void
    {
        $registry = new ClientRegistry();
        $registry->register('a', $this->makeClient());
        $b = $this->makeClient();
        $registry->register('b', $b);

        $registry->setDefault('b');
        $this->assertSame('b', $registry->defaultName());
        $this->assertSame($b, $registry->client());
    }

    public function testSetDefaultUnknownThrows(): void
    {
        $registry = new ClientRegistry();
        $this->expectException(\RuntimeException::class);
        $registry->setDefault('missing');
    }

    public function testNamesListsRegisteredClients(): void
    {
        $registry = new ClientRegistry();
        $registry->register('a', $this->makeClient());
        $registry->register('b', $this->makeClient());

        $this->assertSame(['a', 'b'], $registry->names());
    }

    private function makeClient(): PushClient
    {
        return new class implements PushClient {
            public function send(PushMessage $message, PushTarget $target): PushResult
            {
                return new PushResult(true);
            }
        };
    }
}
