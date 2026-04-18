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
        $set = 0;
        if ($token !== null)     { $set++; }
        if ($topic !== null)     { $set++; }
        if ($condition !== null) { $set++; }

        if ($set === 0) {
            throw new \InvalidArgumentException('PushTarget requires token, topic or condition.');
        }
        if ($set > 1) {
            throw new \InvalidArgumentException(
                'PushTarget accepts exactly one of token, topic or condition — got ' . $set . '.'
            );
        }

        $this->token = $token !== null ? (string) $token : null;
        $this->topic = $topic !== null ? (string) $topic : null;
        $this->condition = $condition !== null ? (string) $condition : null;
    }
}
