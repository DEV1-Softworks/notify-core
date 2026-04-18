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

    public function testNegativeTtlClampsToZero(): void
    {
        $out = AndroidOptions::make()->withTtl(-30)->toArray();
        $this->assertSame('0s', $out['ttl']);
    }

    public function testWithNotificationMergesIntoNotificationMap(): void
    {
        $out = AndroidOptions::make()
            ->withNotification(['icon' => 'ic_launcher', 'color' => '#FF0000'])
            ->withNotification(['color' => '#00FF00', 'sound' => 'chime'])
            ->toArray();

        $this->assertSame('ic_launcher', $out['notification']['icon']);
        $this->assertSame('#00FF00', $out['notification']['color']);
        $this->assertSame('chime', $out['notification']['sound']);
    }

    public function testChannelIdCoexistsWithNotificationMap(): void
    {
        $out = AndroidOptions::make()
            ->withNotification(['icon' => 'ic_launcher'])
            ->withChannelId('alerts')
            ->toArray();

        $this->assertSame('ic_launcher', $out['notification']['icon']);
        $this->assertSame('alerts', $out['notification']['channel_id']);
    }

    public function testWithDataReplacesPreviousData(): void
    {
        $out = AndroidOptions::make()
            ->withData(['a' => '1'])
            ->withData(['b' => '2'])
            ->toArray();

        $this->assertArrayNotHasKey('a', $out['data']);
        $this->assertSame('2', $out['data']['b']);
    }

    public function testWithExtraMergesIntoOutputRoot(): void
    {
        $out = AndroidOptions::make()
            ->withPriority('high')
            ->withExtra(['restricted_package_name' => 'com.example.app'])
            ->toArray();

        $this->assertSame('HIGH', $out['priority']);
        $this->assertSame('com.example.app', $out['restricted_package_name']);
    }

    public function testMergePrefersOtherForScalarsAndMergesMaps(): void
    {
        $a = AndroidOptions::make()
            ->withChannelId('a-chan')
            ->withPriority('normal')
            ->withTtl(60)
            ->withCollapseKey('k-a')
            ->withNotification(['icon' => 'a', 'color' => '#111'])
            ->withData(['x' => '1', 'y' => '2'])
            ->withExtra(['extra_a' => true]);

        $b = AndroidOptions::make()
            ->withPriority('high')
            ->withNotification(['color' => '#222', 'sound' => 'b'])
            ->withData(['y' => '99', 'z' => '3'])
            ->withExtra(['extra_b' => true]);

        $merged = $a->merge($b)->toArray();

        $this->assertSame('HIGH', $merged['priority']);
        $this->assertSame('60s', $merged['ttl']);
        $this->assertSame('k-a', $merged['collapse_key']);
        $this->assertSame('a', $merged['notification']['icon']);
        $this->assertSame('#222', $merged['notification']['color']);
        $this->assertSame('b', $merged['notification']['sound']);
        $this->assertSame('a-chan', $merged['notification']['channel_id']);
        $this->assertSame(['x' => '1', 'y' => '99', 'z' => '3'], $merged['data']);
        $this->assertTrue($merged['extra_a']);
        $this->assertTrue($merged['extra_b']);
    }

    public function testMergeOtherOverridesChannelAndTtl(): void
    {
        $a = AndroidOptions::make()->withChannelId('a')->withTtl(10);
        $b = AndroidOptions::make()->withChannelId('b')->withTtl(99);

        $merged = $a->merge($b)->toArray();

        $this->assertSame('b', $merged['notification']['channel_id']);
        $this->assertSame('99s', $merged['ttl']);
    }
}
