<?php

namespace Dev1\NotifyCore\DTO;

class PushResult
{
    /** @var bool */
    public $success;

    /** @var string|null */
    public $id;

    /** @var string|null */
    public $errorCode;

    /** @var string|null */
    public $errorMessage;

    /** @var array<string,mixed>|null */
    public $raw;

    /**
     * @param array<string,mixed>|null $raw
     */
    public function __construct($success, $id = null, $errorCode = null, $errorMessage = null, $raw = null)
    {
        $this->success = (bool) $success;
        $this->id = $id !== null ? (string) $id : null;
        $this->errorCode = $errorCode !== null ? (string) $errorCode : null;
        $this->errorMessage = $errorMessage !== null ? (string) $errorMessage : null;
        $this->raw = $raw;
    }

    /**
     * Returns true when the device token should be removed from storage
     * (FCM reported it as unknown, unregistered, or no longer valid).
     */
    public function isUnregistered(): bool
    {
        return $this->errorCode === 'UNREGISTERED'
            || $this->errorCode === 'NOT_FOUND';
    }

    /**
     * FCM rejected the payload (bad token format, bad field shape, etc.).
     */
    public function isInvalidArgument(): bool
    {
        return $this->errorCode === 'INVALID_ARGUMENT';
    }

    /**
     * FCM quota exceeded — caller should back off.
     */
    public function isQuotaExceeded(): bool
    {
        return $this->errorCode === 'QUOTA_EXCEEDED'
            || $this->errorCode === 'RESOURCE_EXHAUSTED';
    }

    /**
     * Transient server-side failure; safe to retry later.
     */
    public function isTransient(): bool
    {
        return $this->errorCode === 'UNAVAILABLE'
            || $this->errorCode === 'INTERNAL'
            || $this->errorCode === 'DEADLINE_EXCEEDED'
            || $this->errorCode === 'TRANSPORT_ERROR';
    }
}
