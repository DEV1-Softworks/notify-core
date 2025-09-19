<?php

namespace Dev1\NotifyCore\Contracts;

use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;

interface PushClient
{
    /**
     * Sends a push notification to destination (token, topic or condition).
     */
    public function send(PushMessage $message, PushTarget $target): PushResult;
}
