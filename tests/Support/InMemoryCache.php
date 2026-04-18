<?php

namespace Dev1\NotifyCore\Tests\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal PSR-16 cache backed by an array, for tests only.
 */
final class InMemoryCache implements CacheInterface
{
    /** @var array<string, array{value:mixed, expires_at:int|null}> */
    private $store = [];

    /** @var int */
    public $writes = 0;

    /** @var int */
    public $reads = 0;

    public function get($key, $default = null)
    {
        $this->reads++;
        if (!isset($this->store[$key])) {
            return $default;
        }
        $entry = $this->store[$key];
        if ($entry['expires_at'] !== null && $entry['expires_at'] <= time()) {
            unset($this->store[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->writes++;
        $expiresAt = null;
        if ($ttl instanceof DateInterval) {
            $expiresAt = (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        } elseif (is_int($ttl)) {
            $expiresAt = time() + $ttl;
        }
        $this->store[$key] = ['value' => $value, 'expires_at' => $expiresAt];
        return true;
    }

    public function delete($key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->get($k, $default);
        }
        return $out;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v, $ttl);
        }
        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $k) {
            $this->delete($k);
        }
        return true;
    }

    public function has($key): bool
    {
        return $this->get($key, '__miss__') !== '__miss__';
    }
}
