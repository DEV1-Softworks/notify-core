<?php

namespace Dev1\NotifyCore\Builders;

use Dev1\NotifyCore\Message;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;

final class MessageBuilder
{
    private Message $message;

    public function __construct()
    {
        $this->message = new Message();
    }

    public function withAndroid(AndroidOptions $android): self
    {
        $this->message = $this->message->withAndroid($android);
        return $this;
    }

    public function withApns(ApnsOptions $apns): self
    {
        $this->message = $this->message->withApns($apns);
        return $this;
    }

    public function withAndroidChannelId(string $channelId): self
    {
        $android = $this->message->android() ?: AndroidOptions::make();
        $android = $android->withChannelId($channelId);
        return $this->withAndroid($android);
    }

    public function withApnsPriority(string $priority): self
    {
        $apns = $this->message->apns() ?: ApnsOptions::make();
        $apns = $apns->withAps(['priority' => $priority]);
        return $this->withApns($apns);
    }

    public function build(): Message
    {
        return $this->message;
    }
}
