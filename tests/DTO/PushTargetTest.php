<?php

namespace Dev1\NotifyCore\Tests\DTO;

use Dev1\NotifyCore\DTO\PushTarget;
use PHPUnit\Framework\TestCase;

final class PushTargetTest extends TestCase
{
    public function testRejectsEmptyTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PushTarget();
    }

    public function testStoresTokenAsString(): void
    {
        $target = new PushTarget('abc');
        $this->assertSame('abc', $target->token);
        $this->assertNull($target->topic);
        $this->assertNull($target->condition);
    }

    public function testStoresTopic(): void
    {
        $target = new PushTarget(null, 'news');
        $this->assertNull($target->token);
        $this->assertSame('news', $target->topic);
    }

    public function testStoresCondition(): void
    {
        $target = new PushTarget(null, null, "'a' in topics");
        $this->assertSame("'a' in topics", $target->condition);
    }

    public function testRejectsAmbiguousTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PushTarget('tok', 'news');
    }

    public function testRejectsAllThreeSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PushTarget('tok', 'news', "'a' in topics");
    }
}
