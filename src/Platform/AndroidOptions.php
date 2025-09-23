<?php

namespace Dev1\NotifyCore\Platform;

use Dev1\NotifyCore\Contracts\PlatformOptions;

final class AndroidOptions implements PlatformOptions
{
    private ?string $channelId = null;

    private ?string $priority = null;

    private ?int $ttl = null;

    private ?string $collapseKey = null;

    /** @var array<string,mixed> */
    private array $notification = [];

    /** @var array<string,mixed> */
    private array $data = [];

    /** @var array<string,mixed> */
    private array $extra = [];

    public static function make(): self
    {
        return new self();
    }

    public function withChannelId(string $channelId)
    {
        $this->channelId = $channelId;
        $this->notification['channel_id'] = $channelId;
        return $this;
    }

    public function withPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function withTtl(int $seconds): self
    {
        $this->ttl = $seconds;
        return $this;
    }

    public function witlCollapseKey(string $key): self
    {
        $this->collapseKey = $key;
        return $this;
    }

    /**
     * @param array<string,mixed> $notification
     */
    public function withNotification(array $notification): self
    {
        $this->notification = array_replace_recursive($this->notification, $notification);
        return $this;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function withData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param array<string,mixed> $extra
     */
    public function withExtra(array $extra): self
    {
        $this->extra = array_replace_recursive($this->extra, $extra);
        return $this;
    }

    public function merge(self $other)
    {
        $merged = new self();

        $merged->channelId = $other->channelId !== null ? $other->channelId : $this->channelId;
        $merged->priority = $other->priority !== null ? $other->priority : $this->priority;
        $merged->ttl = $other->ttl !== null ? $other->ttl : $this->ttl;
        $merged->collapseKey = $other->collapseKey !== null ? $other->collapseKey : $this->collapseKey;
        $merged->notification = array_replace_recursive($this->notification, $other->notification);
        $merged->data = array_replace($this->data, $other->data);
        $merged->extra = array_replace_recursive($this->extra, $other->extra);

        return $merged;
    }

    public function toArray(): array
    {
        $output = [];

        if ($this->priority !== null) {
            $output['priority'] = $this->priority;
        }

        if ($this->ttl !== null) {
            $output['ttl'] = $this->ttl;
        }

        if ($this->collapseKey !== null) {
            $output['collapse_key'] = $this->collapseKey;
        }

        if (!empty($this->notification)) {
            $output['notification'] = $this->notification;
        }

        if (!empty($this->data)) {
            $output['data'] = $this->data;
        }

        if (!empty($this->extra)) {
            $output = array_replace_recursive($output, $this->extra);
        }

        if ($this->channelId !== null) {
            // Ensure channel_id is always set in notification for backward compatibility
            if (!isset($output['notification'])) {
                $output['notification'] = [];
            }
            $output['notification']['channel_id'] = $this->channelId;
        }

        return $output;
    }
}
