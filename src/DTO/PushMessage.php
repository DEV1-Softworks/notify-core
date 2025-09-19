<?php

namespace Dev1\NotifyCore\DTO;

/**
 * Represents the push content (title, body and extra payload data)
 */
class PushMessage
{
    /** @var string */
    public $title;

    /** @var string */
    public $body;

    /** @var string */
    public $data;

    /**
     * Overrides by platform (optional)
     * @var array<string, mixed>|null
     */
    public $platformOverrides;

    /**
     * @param array<string,mixed>|null $data
     * @param array<string,mixed>|null $platformOverrides
     */
    public function __construct($title, $body, $data = null, $platformOverrides = null)
    {
        $this->title = (string) $title;
        $this->body = (string) $body;
        $this->data = $data;
        $this->platformOverrides = $platformOverrides;
    }
}
