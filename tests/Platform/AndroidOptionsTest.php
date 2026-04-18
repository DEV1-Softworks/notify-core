<?php

namespace Dev1\NotifyCore\Tests\Platform;

use Dev1\NotifyCore\Platform\AndroidOptions;
use PHPUnit\Framework\TestCase;

final class AndroidOptionsTest extends TestCase
{
    public function testPriorityNormalizes(): void
    {
        $this->assertSame('HIGH', AndroidOptions::make()->withPriority('high')->toArray()['priority']);
        $this->assertSame('HIGH', AndroidOptions::make()->withPriority('MAX')->toArray()['priority']);
        $this->assertSame('NORMAL', AndroidOptions::make()->withPriority('default')->toArray()['priority']);
        $this->assertSame('NORMAL', AndroidOptions::make()->withPriority('low')->toArray()['priority']);
    }

    public function testPriorityRejectsUnknownValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AndroidOptions::make()->withPriority('URGENT');
    }

    public function testChannelIdEmittedExactlyOnce(): void
    {
        $out = AndroidOptions::make()->withChannelId('chan-1')->toArray();

        $this->assertSame('chan-1', $out['notification']['channel_id']);
        $encoded = json_encode($out);
        $this->assertSame(1, substr_count($encoded, '"channel_id"'));
    }

    public function testTtlIsSecondsString(): void
    {
        $out = AndroidOptions::make()->withTtl(120)->toArray();
        $this->assertSame('120s', $out['ttl']);
    }
}
