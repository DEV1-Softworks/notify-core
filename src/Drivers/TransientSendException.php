<?php

namespace Dev1\NotifyCore\Drivers;

use Dev1\NotifyCore\DTO\PushResult;

/**
 * Internal marker for FCM send failures that are safe to retry
 * (5xx responses, 429, PSR-18 transport errors). Carries the
 * PushResult that would be returned if retries are exhausted.
 */
final class TransientSendException extends \RuntimeException
{
    /** @var PushResult */
    public $result;

    public function __construct(PushResult $result)
    {
        parent::__construct($result->errorMessage ?: 'Transient FCM failure');
        $this->result = $result;
    }
}
