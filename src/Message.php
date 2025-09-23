<?php

namespace Dev1\NotifyCore;

use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;

final class Message
{
    private ?AndroidOptions $androidOptions = null;

    private ?ApnsOptions $apnsOptions = null;

    public function withAndroid(?AndroidOptions $android): self
    {
        $clone = clone $this;
        $clone->androidOptions = $android;
        return $clone;
    }

    public function withApns(?ApnsOptions $apns): self
    {
        $clone = clone $this;
        $clone->apnsOptions = $apns;
        return $clone;
    }

    public function android(): ?AndroidOptions
    {
        return $this->androidOptions;
    }

    public function apns(): ?ApnsOptions
    {
        return $this->apnsOptions;
    }

    public function toArray(): array
    {
        $base = [];
        if ($this->androidOptions) {
            $base['android'] = $this->androidOptions->toArray();
        }
        if ($this->apnsOptions) {
            $base['apns'] = $this->apnsOptions->toArray();
        }
        return $base;
    }
}
