<?php

namespace Dev1\NotifyCore\Platform;

use Dev1\NotifyCore\Contracts\PlatformOptions;

final class ApnsOptions implements PlatformOptions
{
    /** @var array<string,mixed> */
    private array $headers = [];

    /** @var array<string,mixed> */
    private array $aps = [];

    /** @var array<string,mixed> */
    private array $custom = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_replace_recursive($this->headers, $headers);
        return $this;
    }

    /**
     * @param array<string,mixed> $aps
     */
    public function withAps(array $aps): self
    {
        $this->aps = array_replace_recursive($this->aps, $aps);
        return $this;
    }

    /**
     * @param array<string,mixed> $custom
     */
    public function withCustom(array $custom): self
    {
        $this->custom = array_replace_recursive($this->custom, $custom);
        return $this;
    }

    public function merge(self $other)
    {
        $merged = new self();
        $merged->headers = array_replace($this->headers, $other->headers);
        $merged->aps = array_replace_recursive($this->aps, $other->aps);
        $merged->custom = array_replace_recursive($this->custom, $other->custom);
        return $merged;
    }

    public function toArray(): array
    {
        $payload = [];
        if (!empty($this->headers)) {
            $payload['headers'] = $this->headers;
        }

        $body = [];
        if (!empty($this->aps)) {
            $body['aps'] = $this->aps;
        }
        if (!empty($this->custom)) {
            $body = array_merge($body, $this->custom);
        }
        if (!empty($body)) {
            $payload['body'] = $body;
        }

        return $payload;
    }
}
