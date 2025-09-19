<?php

namespace Dev1\NotifyCore\Factory;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;
use Dev1\NotifyCore\Drivers\FcmHttpV1Client;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class FcmClientFactory
{
    public static function create(
        HttpClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        AccessTokenProvider $tokenProvider,
        ?LoggerInterface $logger,
        array $config,
    ): FcmHttpV1Client {
        return new FcmHttpV1Client(
            $http,
            $requestFactory,
            $streamFactory,
            $tokenProvider,
            $logger,
            $config
        );
    }
}
