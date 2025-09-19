<?php

namespace Dev1\NotifyCore\Auth;

use Dev1\NotifyCore\Contracts\AccessTokenProvider;

use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates an OAuth 2.0 access token (JWT -> token) for FCM HTTP v1
 * using a Google Service Account.
 * 
 * Expected configurations:
 * - client_email (string, required)
 * - private_key (string, required, BEGIN_PRIVATE_KEY ... END_PRIVATE_KEY)
 * - token_uri (string, optional) default: https://oauth2.googleapis.com/token
 * - scope (string|string[], optional) default: https://www.googleapis.com/auth/firebase.messaging
 * - cache_leeway (int, optional) time remaining for renewal (default 30)
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
        array $config = []
    ) {
        if (empty($config['client_email']) || empty($config['private_key'])) {
            throw new \InvalidArgumentException('GoogleServiceAccountTokenProvider requires client_email and private_key');
        }

        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger;

        $this->config = [
            'client_email' => (string) $config['client_email'],
            'private_key' => (string) $config['private_key'],
            'token_uri' => isset($config['token_uri']) ? (string) $config['token_uri'] : 'https://oauth2.googleapis.com/token',
            'scope' => isset($config['scope']) ? $config['scope'] : 'https://www.googleapis.com/auth/firebase.messaging',
            'cache_leeway' => isset($config['cache_leeway']) ? (int) $config['cache_leeway'] : 30,
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

        if ($this->cachedToken !== null && $this->expiresAt !== null) {
            $leeway = (int) $this->config['cache_leeway'];

            if ($now < ($this->expiresAt - $leeway)) {
                return $this->cachedToken;
            }
        }

        // Build JWT
        $jwt = $this->buildAssertionJwt($now);

        $form = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], '', '&');

        $request = $this->requestFactory
            ->createRequest('POST', $this->config['token_uri'])
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $request = $request->withBody($this->streamFactory->createStream($form));

        try {
            $response = $this->http->sendRequest($request);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            if ($status >= 200 && $status < 300 && is_array($decoded) && isset($decoded['access_token'])) {
                $token = (string) $decoded['access_token'];
                $expiresIn = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3500;
                $this->cachedToken = $token;
                $this->expiresAt = $now + $expiresIn;

                if ($this->logger) {
                    $this->logger->info('Obtained Google OAuth access token', ['expires_in' => $expiresIn]);
                }

                return $token;
            }

            $msg = 'OAuth token error';
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

            throw new \RuntimeException($msg . ' (HTTP ' . $status . '): ' . ($desc ?: $body));
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('Google OAuth token request exception', ['exception' => $e]);
            }

            throw $e;
        }

        return '';
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

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($claims)),
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

        $ok = openssl_sign($data, $signature, $pKey, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($pKey);

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
