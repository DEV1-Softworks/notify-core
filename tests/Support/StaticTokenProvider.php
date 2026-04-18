<?php

namespace Dev1\NotifyCore\Tests\Support;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;

final class StaticTokenProvider implements AccessTokenProvider
{
    /** @var string */
    private $token;

    public function __construct(string $token = 'test-token')
    {
        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
