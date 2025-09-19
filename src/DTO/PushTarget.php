<?php

namespace Dev1\NotifyCore\DTO;

/**
 * Defines push destination: token, topic or condition.
 */
class PushTarget
{
    /** @var string|null */
    public $token;

    /** @var string|null */
    public $topic;

    /** @var string|null */
    public $condition;

    public function __construct($token = null, $topic = null, $condition = null)
    {
        if ($token === null && $topic === null && $condition === null) {
            throw new \InvalidArgumentException("PushTarget requires token, topic or condition.");
        }

        $this->token = $token !== null ? (string) $token : null;
        $this->topic = $topic !== null ? (string) $topic : null;
        $this->condition = $condition !== null ? (string) $condition : null;
    }
}
