<?php

namespace Dev1\NotifyCore\Tests\DTO;

use Dev1\NotifyCore\DTO\PushResult;
use PHPUnit\Framework\TestCase;

final class PushResultTest extends TestCase
{
    public function testSuccessDefaults(): void
    {
        $result = new PushResult(true, 'projects/p/messages/m1');
        $this->assertTrue($result->success);
        $this->assertSame('projects/p/messages/m1', $result->id);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->raw);
    }

    public function testFailurePropagatesErrorFields(): void
    {
        $raw = ['error' => ['status' => 'NOT_FOUND']];
        $result = new PushResult(false, null, 'NOT_FOUND', 'Token missing', $raw);

        $this->assertFalse($result->success);
        $this->assertNull($result->id);
        $this->assertSame('NOT_FOUND', $result->errorCode);
        $this->assertSame('Token missing', $result->errorMessage);
        $this->assertSame($raw, $result->raw);
    }

    public function testUnregisteredHelper(): void
    {
        $this->assertTrue((new PushResult(false, null, 'UNREGISTERED'))->isUnregistered());
        $this->assertTrue((new PushResult(false, null, 'NOT_FOUND'))->isUnregistered());
        $this->assertFalse((new PushResult(false, null, 'INVALID_ARGUMENT'))->isUnregistered());
    }

    public function testInvalidArgumentHelper(): void
    {
        $this->assertTrue((new PushResult(false, null, 'INVALID_ARGUMENT'))->isInvalidArgument());
        $this->assertFalse((new PushResult(false, null, 'UNREGISTERED'))->isInvalidArgument());
    }

    public function testQuotaExceededHelper(): void
    {
        $this->assertTrue((new PushResult(false, null, 'QUOTA_EXCEEDED'))->isQuotaExceeded());
        $this->assertTrue((new PushResult(false, null, 'RESOURCE_EXHAUSTED'))->isQuotaExceeded());
        $this->assertFalse((new PushResult(false, null, 'UNREGISTERED'))->isQuotaExceeded());
    }

    public function testTransientHelper(): void
    {
        $this->assertTrue((new PushResult(false, null, 'UNAVAILABLE'))->isTransient());
        $this->assertTrue((new PushResult(false, null, 'TRANSPORT_ERROR'))->isTransient());
        $this->assertTrue((new PushResult(false, null, 'INTERNAL'))->isTransient());
        $this->assertTrue((new PushResult(false, null, 'DEADLINE_EXCEEDED'))->isTransient());
        $this->assertFalse((new PushResult(false, null, 'INVALID_ARGUMENT'))->isTransient());
    }
}
