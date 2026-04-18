<?php

namespace Dev1\NotifyCore\Tests\Platform;

use Dev1\NotifyCore\Platform\ApnsOptions;
use PHPUnit\Framework\TestCase;

final class ApnsOptionsTest extends TestCase
{
    public function testEmptyOptionsProduceEmptyArray(): void
    {
        $this->assertSame([], ApnsOptions::make()->toArray());
    }

    public function testHeadersOnlyEmitsHeadersKey(): void
    {
        $out = ApnsOptions::make()
            ->withHeaders(['apns-priority' => '10', 'apns-push-type' => 'alert'])
            ->toArray();

        $this->assertSame([
            'headers' => ['apns-priority' => '10', 'apns-push-type' => 'alert'],
        ], $out);
        $this->assertArrayNotHasKey('body', $out);
    }

    public function testApsIsNestedUnderBody(): void
    {
        $out = ApnsOptions::make()
            ->withAps(['alert' => ['title' => 'Hi', 'body' => 'There'], 'sound' => 'default'])
            ->toArray();

        $this->assertSame([
            'alert' => ['title' => 'Hi', 'body' => 'There'],
            'sound' => 'default',
        ], $out['body']['aps']);
    }

    public function testCustomPayloadMergesAtBodyRoot(): void
    {
        $out = ApnsOptions::make()
            ->withAps(['alert' => ['title' => 't']])
            ->withCustom(['correlation_id' => 'abc123', 'nested' => ['k' => 'v']])
            ->toArray();

        $this->assertSame('abc123', $out['body']['correlation_id']);
        $this->assertSame(['k' => 'v'], $out['body']['nested']);
        $this->assertSame(['title' => 't'], $out['body']['aps']['alert']);
    }

    public function testWithHeadersMergesRecursively(): void
    {
        $opts = ApnsOptions::make()
            ->withHeaders(['apns-priority' => '10'])
            ->withHeaders(['apns-expiration' => '0']);

        $out = $opts->toArray();
        $this->assertSame('10', $out['headers']['apns-priority']);
        $this->assertSame('0', $out['headers']['apns-expiration']);
    }

    public function testMergePrefersOtherForScalarsAndMergesMaps(): void
    {
        $a = ApnsOptions::make()
            ->withHeaders(['apns-priority' => '5'])
            ->withAps(['sound' => 'soft', 'badge' => 1])
            ->withCustom(['trace' => 'A']);

        $b = ApnsOptions::make()
            ->withHeaders(['apns-priority' => '10'])
            ->withAps(['badge' => 2])
            ->withCustom(['trace' => 'B', 'extra' => true]);

        $merged = $a->merge($b)->toArray();

        $this->assertSame('10', $merged['headers']['apns-priority']);
        $this->assertSame('soft', $merged['body']['aps']['sound']);
        $this->assertSame(2, $merged['body']['aps']['badge']);
        $this->assertSame('B', $merged['body']['trace']);
        $this->assertTrue($merged['body']['extra']);
    }

    public function testCustomWithoutApsStillProducesBody(): void
    {
        $out = ApnsOptions::make()
            ->withCustom(['only' => 'custom'])
            ->toArray();

        $this->assertSame(['only' => 'custom'], $out['body']);
        $this->assertArrayNotHasKey('aps', $out['body']);
    }
}
