<?php

namespace Dev1\NotifyCore\Drivers;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;
use Dev1\NotifyCore\Contracts\PushClient;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class FcmHttpV1Client implements PushClient
{
    /** @var HttpClientInterface */
    private $http;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var AccessTokenProvider */
    private $tokenProvider;

    /** @var LoggerInterface|null */
    private $logger;

    /**
     * @var array<string,mixed>
     *  - project_id: string (required)
     *  - endpoint: string (optional, default: https://fcm.googleapis.com/v1/projects/{project_id}/messages:send)
     *  - timeout: int|float (optional)
     */
    private $config;

    public function __construct(
        HttpClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        AccessTokenProvider $tokenProvider,
        ?LoggerInterface $logger = null,
        array $config = []
    ) {
        if (empty($config['project_id'])) {
            throw new \InvalidArgumentException('FCMHttpV1Client requires "project_id".');
        }

        $defaultEndpoint = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';

        $endpoint = isset($config['endpoint']) && is_string($config['endpoint'])
            ? $config['endpoint']
            : $defaultEndpoint;

        $endpoint = str_replace('{project_id}', $config['project_id'], $endpoint);

        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->tokenProvider = $tokenProvider;
        $this->logger = $logger;
        $this->config = [
            'project_id' => $config['project_id'],
            'endpoint' => $endpoint,
            'timeout' => isset($config['timeout']) ? $config['timeout'] : null,
        ];
    }

    /**
     * Send a push notification.
     *
     * @param mixed $message
     * @return mixed
     */
    public function send(PushMessage $message, PushTarget $target): PushResult
    {
        $payload = ['message' => $this->buildMessagePayload($message, $target)];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $token = $this->tokenProvider->getToken();

        $request = $this->requestFactory->createRequest('POST', $this->config['endpoint'])
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json');

        $request = $request->withBody($this->streamFactory->createStream($json));

        try {
            $response = $this->http->sendRequest($request);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Success
            if ($status >= 200 && $status < 300) {
                $decoded = json_decode($body, true);

                $id = is_array($decoded) && isset($decoded['name']) ? (string) $decoded['name'] : null;

                if ($this->logger) {
                    $this->logger->info('FCM v1 send OK', ['id' => $id, 'status' => $status]);
                }

                return new PushResult(true, $id, null, null, is_array($decoded) ? $decoded : null);
            }

            // Error
            $decoded = json_decode($body, true);
            $errorCode = null;
            $errorMessage = null;

            if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
                $error = $decoded['error'];

                $errorCode = isset($error['status']) ? (string) $error['status'] : ('HTTP_' . $status);
                $errorMessage = isset($error['message']) ? (string) $error['message'] : 'FCM v1 error';
            } else {
                $errorCode = 'HTTP_' . $status;
                $errorMessage = $body !== '' ? $body : 'HTTP error';
            }

            if ($this->logger) {
                $this->logger->warning('FCM v1 send FAILED', [
                    'status' => $status,
                    'error' => $errorCode,
                    'message' => $errorMessage,
                ]);
            }

            return new PushResult(false, null, $errorCode, $errorMessage, is_array($decoded) ? $decoded : null);
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('FCM V1 exception', ['exception' => $e]);
            }

            return new PushResult(false, null, 'EXCEPTION', $e->getMessage(), null);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildMessagePayload(PushMessage $message, PushTarget $target)
    {
        $msg = [];

        if ($target->token !== null) {
            $msg['token'] = $target->token;
        } elseif ($target->topic !== null) {
            $msg['topic'] = $target->topic;
        } elseif ($target->condition !== null) {
            $msg['condition'] = $target->condition;
        }

        /** Notification */
        $notification = [
            'title' => $message->title,
            'body' => $message->body,
        ];

        $msg['notification'] = $notification;

        /** Data */
        if (is_array($message->data) && !empty($message->data)) {
            $msg['data'] = [];

            foreach ($message->data as $key => $value) {
                $msg['data'][(string)$key] = is_scalar($value) ? (string)$value : json_encode($value);
            }
        }

        /** Platform overrides */
        if (is_array($message->platformOverrides)) {
            if (isset($message->platformOverrides['android']) && is_array($message->platformOverrides['android'])) {
                $msg['android'] = $message->platformOverrides['android'];
            }
            if (isset($message->platformOverrides['apns']) && is_array($message->platformOverrides['apns'])) {
                $msg['apns'] = $message->platformOverrides['apns'];
            }
            if (isset($message->platformOverrides['webpush']) && is_array($message->platformOverrides['webpush'])) {
                $msg['webpush'] = $message->platformOverrides['webpush'];
            }
        }

        return $msg;
    }
}
