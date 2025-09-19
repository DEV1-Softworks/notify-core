<?php

namespace Dev1\NotifyCore\Registry;

use Dev1\NotifyCore\Contracts\PushClient;

class ClientRegistry
{
    /** @var array<string, PushClient> */
    private $clients = [];

    /** @var string|null */
    private $defaultName = null;

    /**
     * Registers a client with its name, you may mark it as default
     */
    public function register(string $name, PushClient $client, bool $asDefault = false)
    {
        $this->clients[$name] = $client;

        if ($asDefault || $this->defaultName === null) {
            $this->defaultName = $name;
        }
    }

    /**
     * Obtains a client by name, default if not specified.
     * 
     * @throws \RuntimeException if client does not exist or there's no default.
     */
    public function client(?string $name = null): PushClient
    {
        $key = $name !== null ? $name : $this->defaultName;

        if ($key === null || !isset($this->clients[$key])) {
            throw new \RuntimeException('No registered client for: ' . ($name !== null ? $name : '(default)'));
        }

        return $this->clients[$key];
    }

    /**
     * Explicitly defines the default client
     * 
     * @throws \RuntimeException if name is not registered
     */
    public function setDefault(string $name)
    {
        if (!isset($this->clients[$name])) {
            throw new \RuntimeException("Client does not exist: " . $name);
        }

        $this->defaultName = $name;
    }

    /**
     * Verifies if a registered client exists.
     */
    public function has(string $name): bool
    {
        return isset($this->clients[$name]);
    }

    /**
     * Lists the registered clients.
     */
    public function names(): array
    {
        return array_keys($this->clients);
    }

    /**
     * Removes a client from registry. If it was default, default is cleared.
     */
    public function remove(string $name)
    {
        if (isset($this->clients[$name])) {
            unset($this->clients[$name]);

            if ($this->defaultName === $name) {
                $this->defaultName = null;
            }
        }
    }

    /**
     * Returns the default client name (null if none).
     */
    public function defaultName(): ?string
    {
        return $this->defaultName;
    }
}
