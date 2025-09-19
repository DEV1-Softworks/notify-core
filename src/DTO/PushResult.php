<?php

namespace Dev1\NotifyCore\DTO;

class PushResult
{
    /** @var bool */
    public $success;

    /** @var string */
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
}
