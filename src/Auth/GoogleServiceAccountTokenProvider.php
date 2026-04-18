<?php

namespace Dev1\NotifyCore\Auth;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;
use Dev1\NotifyCore\Version;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Generates an OAuth 2.0 access token (JWT -> token) for FCM HTTP v1
 * using a Google Service Account.
 *
 * Expected configurations:
 * - client_email (string, required)
 * - private_key (string, required, BEGIN_PRIVATE_KEY ... END_PRIVATE_KEY)
 * - token_uri (string, optional) default: https://oauth2.googleapis.com/token
 * - scope (string|string[], optional) default: https://www.googleapis.com/auth/firebase.messaging
 * - cache_leeway (int, optional) seconds of margin before considered expired (default 30)
 * - cache_key (string, optional) override for PSR-16 cache key (default derived from client_email)
 * - max_retries (int, optional) retry attempts on transient 5xx / transport failures (default 2)
 * - retry_base_delay_ms (int, optional) initial backoff in ms; doubles each attempt (default 200)
 */
class GoogleServiceAccountTokenProvider implements AccessTokenProvider
{
    /** @var HttpClientInterface */
    private $http;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var LoggerInterface|null */
    private $logger;

    /** @var CacheInterface|null */
    private $cache;

    /** @var array<string,mixed> */
    private $config;

    /** @var string|null */
    private $cachedToken;

    /** @var int|null */
    private $expiresAt;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        HttpClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        array $config = [],
        ?CacheInterface $cache = null
    ) {
        if (empty($config['client_email']) || empty($config['private_key'])) {
            throw new \InvalidArgumentException('GoogleServiceAccountTokenProvider requires client_email and private_key');
        }

        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger;
        $this->cache = $cache;

        $clientEmail = (string) $config['client_email'];

        $this->config = [
            'client_email' => $clientEmail,
            'private_key' => (string) $config['private_key'],
            'token_uri' => isset($config['token_uri']) ? (string) $config['token_uri'] : 'https://oauth2.googleapis.com/token',
            'scope' => isset($config['scope']) ? $config['scope'] : 'https://www.googleapis.com/auth/firebase.messaging',
            'cache_leeway' => isset($config['cache_leeway']) ? (int) $config['cache_leeway'] : 30,
            'cache_key' => isset($config['cache_key'])
                ? (string) $config['cache_key']
                : 'dev1_notify_core.google_oauth.' . sha1($clientEmail),
            'max_retries' => isset($config['max_retries']) ? max(0, (int) $config['max_retries']) : 2,
            'retry_base_delay_ms' => isset($config['retry_base_delay_ms']) ? max(0, (int) $config['retry_base_delay_ms']) : 200,
        ];

        $this->cachedToken = null;
        $this->expiresAt = null;
    }

    /**
     * Get an OAuth 2.0 access token.
     *
     * @return string
     */
    public function getToken(): string
    {
        $now = time();
        $leeway = (int) $this->config['cache_leeway'];

        if ($this->cachedToken !== null && $this->expiresAt !== null && $now < ($this->expiresAt - $leeway)) {
            return $this->cachedToken;
        }

        $cached = $this->loadFromCache($now, $leeway);
        if ($cached !== null) {
            return $cached;
        }

        $token = $this->requestNewTokenWithRetry($now);
        return $token;
    }

    /**
     * @return string|null  Cached token if still valid, null otherwise.
     */
    private function loadFromCache(int $now, int $leeway): ?string
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $entry = $this->cache->get($this->config['cache_key']);
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Token cache read failed', ['exception' => $e]);
            }
            return null;
        }

        if (!is_array($entry) || !isset($entry['token'], $entry['expires_at'])) {
            return null;
        }

        $token = (string) $entry['token'];
        $expiresAt = (int) $entry['expires_at'];

        if ($token === '' || $now >= ($expiresAt - $leeway)) {
            return null;
        }

        $this->cachedToken = $token;
        $this->expiresAt = $expiresAt;
        return $token;
    }

    private function requestNewTokenWithRetry(int $now): string
    {
        $maxRetries = (int) $this->config['max_retries'];
        $baseDelayMs = (int) $this->config['retry_base_delay_ms'];

        $lastException = null;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->requestNewToken($now);
            } catch (TransientAuthException $e) {
                $lastException = $e;
                if ($attempt === $maxRetries) {
                    break;
                }
                $this->sleepBackoff($baseDelayMs, $attempt);
            }
        }

        throw $lastException ?: new \RuntimeException('OAuth token request failed');
    }

    private function requestNewToken(int $now): string
    {
        $jwt = $this->buildAssertionJwt($now);

        $form = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], '', '&');

        $request = $this->requestFactory
            ->createRequest('POST', $this->config['token_uri'])
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('User-Agent', Version::USER_AGENT)
            ->withBody($this->streamFactory->createStream($form));

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            if ($this->logger) {
                $this->logger->warning('Google OAuth transport error', ['exception' => $e]);
            }
            throw new TransientAuthException('OAuth transport error: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($status >= 200 && $status < 300 && is_array($decoded) && isset($decoded['access_token'])) {
            $token = (string) $decoded['access_token'];
            $expiresIn = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3500;
            $expiresAt = $now + $expiresIn;

            $this->cachedToken = $token;
            $this->expiresAt = $expiresAt;
            $this->saveToCache($token, $expiresAt);

            if ($this->logger) {
                $this->logger->info('Obtained Google OAuth access token', ['expires_in' => $expiresIn]);
            }

            return $token;
        }

        $err = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : null;
        $desc = is_array($decoded) && isset($decoded['error_description']) ? $decoded['error_description'] : null;

        if ($this->logger) {
            $this->logger->warning('Google OAuth token request failed', [
                'status' => $status,
                'error' => $err,
                'error_description' => $desc,
                'body' => $body,
            ]);
        }

        $msg = 'OAuth token error (HTTP ' . $status . '): ' . ($desc ?: $body);

        if ($status >= 500 || $status === 429) {
            throw new TransientAuthException($msg);
        }

        throw new \RuntimeException($msg);
    }

    private function saveToCache(string $token, int $expiresAt): void
    {
        if ($this->cache === null) {
            return;
        }

        $ttl = $expiresAt - time();
        if ($ttl <= 0) {
            return;
        }

        try {
            $this->cache->set(
                $this->config['cache_key'],
                ['token' => $token, 'expires_at' => $expiresAt],
                $ttl
            );
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Token cache write failed', ['exception' => $e]);
            }
        }
    }

    private function sleepBackoff(int $baseDelayMs, int $attempt): void
    {
        $delayMs = $baseDelayMs * (1 << $attempt); // 200, 400, 800, ...
        // Jitter up to 25% to avoid thundering herd.
        $jitter = (int) ($delayMs * 0.25 * (mt_rand(0, 1000) / 1000));
        $sleepMicros = ($delayMs + $jitter) * 1000;
        if ($sleepMicros > 0) {
            usleep($sleepMicros);
        }
    }

    /**
     * Builds the signed JWT (RS256) for JWT Bearer flow.
     */
    private function buildAssertionJwt(int $now): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $scope = $this->config['scope'];

        if (is_array($scope)) {
            $scope = implode(' ', array_map('strval', $scope));
        } else {
            $scope = (string) $scope;
        }

        $claims = [
            'iss' => $this->config['client_email'],
            'scope' => $scope,
            'aud' => $this->config['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $headerJson = json_encode($header);
        $claimsJson = json_encode($claims);

        if ($headerJson === false || $claimsJson === false) {
            $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json_encode failed';
            throw new \RuntimeException('Failed to encode JWT segments: ' . $err);
        }

        $segments = [
            $this->base64UrlEncode($headerJson),
            $this->base64UrlEncode($claimsJson),
        ];
        $signingInput = implode('.', $segments);

        $signature = $this->rsaSign($signingInput, $this->config['private_key']);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @param string $data
     * @param string $privateKeyPem
     * @return string binary signature
     */
    private function rsaSign($data, $privateKeyPem)
    {
        $pKey = openssl_pkey_get_private($privateKeyPem);

        if ($pKey === false) {
            throw new \RuntimeException('Invalid private key (openssl_pkey_get_private failed)');
        }

        $signature = '';

        // Private key resource/object is released by the garbage collector;
        // we avoid an explicit free call because it is deprecated on PHP 8.x.
        $ok = openssl_sign($data, $signature, $pKey, OPENSSL_ALGO_SHA256);

        if (!$ok) {
            throw new \RuntimeException("OpenSSL failed to sign data.");
        }

        return $signature;
    }

    /**
     * Base64 URL-safe (without padding).
     * @param string $data
     */
    private function base64UrlEncode($data)
    {
        $enc = base64_encode($data);
        $enc = strtr($enc, '+/', '-_');
        return rtrim($enc, '=');
    }
}
