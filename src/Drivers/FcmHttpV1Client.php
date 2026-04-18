<?php

namespace Dev1\NotifyCore\Drivers;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;
use Dev1\NotifyCore\Contracts\PlatformOptions;
use Dev1\NotifyCore\Contracts\PushClient;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Version;
use Psr\Http\Client\ClientExceptionInterface;
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
     *  - max_retries: int (optional, default 2) — attempts on 5xx/429/transport errors
     *  - retry_base_delay_ms: int (optional, default 200) — initial backoff, doubles per attempt
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
            'max_retries' => isset($config['max_retries']) ? max(0, (int) $config['max_retries']) : 2,
            'retry_base_delay_ms' => isset($config['retry_base_delay_ms']) ? max(0, (int) $config['retry_base_delay_ms']) : 200,
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

        if ($json === false) {
            $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json_encode failed';
            if ($this->logger) {
                $this->logger->error('FCM v1 payload encode failed', ['error' => $err]);
            }
            return new PushResult(false, null, 'ENCODE_ERROR', $err, null);
        }

        try {
            $token = $this->tokenProvider->getToken();
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('FCM v1 token acquisition failed', ['exception' => $e]);
            }
            return new PushResult(false, null, 'TOKEN_ERROR', $e->getMessage(), null);
        }

        $maxRetries = (int) $this->config['max_retries'];
        $baseDelayMs = (int) $this->config['retry_base_delay_ms'];

        $lastTransient = null;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->doSend($json, $token);
            } catch (TransientSendException $e) {
                $lastTransient = $e;
                if ($attempt === $maxRetries) {
                    break;
                }
                $this->sleepBackoff($baseDelayMs, $attempt);
            }
        }

        // All attempts exhausted on transient failure; surface as a non-success PushResult.
        return $lastTransient
            ? $lastTransient->result
            : new PushResult(false, null, 'TRANSPORT_ERROR', 'Unknown transient failure', null);
    }

    /**
     * @throws TransientSendException  On 5xx / 429 / PSR-18 transport errors.
     */
    private function doSend(string $json, string $token): PushResult
    {
        $request = $this->requestFactory->createRequest('POST', $this->config['endpoint'])
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', Version::USER_AGENT)
            ->withBody($this->streamFactory->createStream($json));

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            if ($this->logger) {
                $this->logger->warning('FCM v1 transport exception', ['exception' => $e]);
            }
            throw new TransientSendException(
                new PushResult(false, null, 'TRANSPORT_ERROR', $e->getMessage(), null)
            );
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 200 && $status < 300) {
            $decoded = json_decode($body, true);
            $id = is_array($decoded) && isset($decoded['name']) ? (string) $decoded['name'] : null;

            if ($this->logger) {
                $this->logger->info('FCM v1 send OK', ['id' => $id, 'status' => $status]);
            }

            return new PushResult(true, $id, null, null, is_array($decoded) ? $decoded : null);
        }

        $decoded = json_decode($body, true);
        $errorCode = null;
        $errorMessage = null;

        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            $error = $decoded['error'];

            // Prefer FcmError.errorCode (e.g. UNREGISTERED, QUOTA_EXCEEDED) over the
            // generic google.rpc.Status 'status' field (e.g. NOT_FOUND, PERMISSION_DENIED).
            $fcmErrorCode = null;
            if (isset($error['details']) && is_array($error['details'])) {
                foreach ($error['details'] as $detail) {
                    if (is_array($detail) && isset($detail['errorCode'])) {
                        $fcmErrorCode = (string) $detail['errorCode'];
                        break;
                    }
                }
            }

            if ($fcmErrorCode !== null) {
                $errorCode = $fcmErrorCode;
            } elseif (isset($error['status'])) {
                $errorCode = (string) $error['status'];
            } else {
                $errorCode = 'HTTP_' . $status;
            }

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

        $result = new PushResult(false, null, $errorCode, $errorMessage, is_array($decoded) ? $decoded : null);

        if ($status >= 500 || $status === 429) {
            throw new TransientSendException($result);
        }

        return $result;
    }

    private function sleepBackoff(int $baseDelayMs, int $attempt): void
    {
        $delayMs = $baseDelayMs * (1 << $attempt);
        $jitter = (int) ($delayMs * 0.25 * (mt_rand(0, 1000) / 1000));
        $sleepMicros = ($delayMs + $jitter) * 1000;
        if ($sleepMicros > 0) {
            usleep($sleepMicros);
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

        /** Notification (skip entirely for silent/data-only messages) */
        if ($message->title !== '' || $message->body !== '') {
            $msg['notification'] = [
                'title' => $message->title,
                'body' => $message->body,
            ];
        }

        /** Data */
        if (is_array($message->data) && !empty($message->data)) {
            $msg['data'] = [];

            foreach ($message->data as $key => $value) {
                if (is_scalar($value)) {
                    $msg['data'][(string) $key] = (string) $value;
                    continue;
                }

                $encoded = json_encode($value);
                $msg['data'][(string) $key] = $encoded === false ? '' : $encoded;
            }
        }

        /** Platform overrides */
        if (is_array($message->platformOverrides)) {
            $android = isset($message->platformOverrides['android']) ? $message->platformOverrides['android'] : null;
            if ($android !== null) {
                if ($android instanceof PlatformOptions) {
                    $msg['android'] = $android->toArray();
                } elseif (is_array($android)) {
                    $msg['android'] = $android;
                }
            }

            $apns = isset($message->platformOverrides['apns']) ? $message->platformOverrides['apns'] : null;
            if ($apns !== null) {
                if ($apns instanceof PlatformOptions) {
                    $msg['apns'] = $apns->toArray();
                } elseif (is_array($apns)) {
                    $msg['apns'] = $apns;
                }
            }

            $webpush = isset($message->platformOverrides['webpush']) ? $message->platformOverrides['webpush'] : null;
            if ($webpush !== null && is_array($webpush)) {
                $msg['webpush'] = $webpush;
            }
        }

        return $msg;
    }
}
