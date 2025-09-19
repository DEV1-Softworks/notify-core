<?php

declare(strict_types=1);

use Dev1\NotifyCore\Auth\GoogleServiceAccountTokenProvider;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Factory\FcmClientFactory;
use Dev1\NotifyCore\Registry\ClientRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

require __DIR__ . '/../vendor/autoload.php';

// ========= CONFIGURE FOR TESTS ===========
$projectId = 'YOUR_PROJECT_ID';
$deviceToken = 'YOUR_DEVICE_TOKEN';
$serviceAccountPath = __DIR__ . '/service-account.json';
// =========================================

if (!is_file($serviceAccountPath)) {
    fwrite(STDERR, "Service Account file not found: {$serviceAccountPath}\n");
    exit(1);
}

$sa = json_decode(file_get_contents($serviceAccountPath), true);

if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
    fwrite(STDERR, "Service Account JSON does not contain valid client_email/private_key.\n");
    exit(1);
}

$psr17 = new Psr17Factory();
$http  = new Psr18Client();

// Token Provider (Google SA → OAuth2)
$tokenProvider = new GoogleServiceAccountTokenProvider(
    $http,
    $psr17,
    $psr17,
    null, // LoggerInterface|null
    [
        'client_email' => $sa['client_email'],
        'private_key'  => $sa['private_key'],
        // 'token_uri' => 'https://oauth2.googleapis.com/token', // optional
        // 'scope'     => 'https://www.googleapis.com/auth/firebase.messaging', // optional
    ]
);

// Creates FCM v1 client with factory
$fcmClient = FcmClientFactory::create(
    $http,
    $psr17,
    $psr17,
    $tokenProvider,
    null, // LoggerInterface|null
    [
        'project_id' => $projectId,
        // 'endpoint' => 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send', // optional
        // 'timeout'  => 5, // optional
    ]
);

$registry = new ClientRegistry();
$registry->register('fcm', $fcmClient, true);

$message = new PushMessage(
    'Hello from DEV1 Notify',
    'If you see this notification, FCM v1 is working as a charm. Thanks for trusting in DEV1.',
    ['order_id' => '123', 'foo' => 'bar']
    // platformOverrides optional
);

$target = new PushTarget($deviceToken);

// Sends notification
$result = $registry->client()->send($message, $target);

// Results
echo "Success: " . ($result->success ? 'true' : 'false') . PHP_EOL;
echo "ID: " . ($result->id ?? '(none)') . PHP_EOL;
echo "ErrorCode: " . ($result->errorCode ?? '(none)') . PHP_EOL;
echo "ErrorMessage: " . ($result->errorMessage ?? '(none)') . PHP_EOL;
