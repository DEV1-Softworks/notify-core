<?php

namespace Dev1\NotifyCore\Contracts;

interface AccessTokenProvider
{
    /**
     * Provides an OAuth 2.0 bearer token for calling Google FCM HTTP v1.
     * The returned value MUST NOT include the "Bearer " prefix.
     *
     * Implementations SHOULD cache valid tokens until they expire and
     * SHOULD retry transient failures (5xx / 429 / network errors) before
     * giving up.
     *
     * @throws \RuntimeException When token acquisition fails and cannot be
     *                           retried (e.g. invalid credentials, 4xx from
     *                           the OAuth endpoint, or exhausted retries).
     */
    public function getToken(): string;
}
