<?php

namespace Dev1\NotifyCore\Contracts;

interface AccessTokenProvider
{
    /**
     * Provides an OAuth 2.0 token for calling to Google FCM HTTP v1.
     * It must return the bearer token, WITHOUT "Bearer " prefix.
     */
    public function getToken(): string;
}
