<?php

declare(strict_types=1);

use Dev1\NotifyCore\Auth\GoogleServiceAccountTokenProvider;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Factory\FcmClientFactory;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;
use Dev1\NotifyCore\Registry\ClientRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

require __DIR__ . '/../vendor/autoload.php';

// ========= CONFIGURE FOR TESTS ===========
$projectId =  'YOUR_PROJECT_ID';
$deviceToken = 'YOUR_DEVICE_TOKEN';
$serviceAccountPath = __DIR__ . '/service-account.json';
// ========= CONFIGURE FOR TESTS IN CUSTOM CHANNELS ===========
$androidChannelId   = 'your_android_custom_channel_id';
$ttlSeconds         = 3600; // se formatea a "Xs" en el transport
$apnsPriority       = '10';
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

// ============ NEW PLATFORM OVERRIDES =============
// ANDROID
$android = AndroidOptions::make()
    ->withChannelId($androidChannelId)
    ->withPriority('high')
    ->withTtl($ttlSeconds)
    ->withNotification([
        'sound' => 'default',
        'color' => '#FF0000',
    ])
    ->withData([
        'order_id' => '123',
        'foo'      => 'bar',
    ]);

// APNs
$apns = ApnsOptions::make()
    ->withHeaders([
        'apns-priority' => $apnsPriority, // 10 = immediate, 5 = background
        'apns-push-type' => 'alert', // VoIP = for CallKit apps, 'alert' = regular notifications, 'background' = silent notifications
        // 'apns-expiration' => '0', // 0 = immediately, or unix timestamp
        // 'apns-topic' => 'com.your.app.bundle', // your app bundle id
    ])
    ->withAps([
        'sound' => 'default',
        'mutable-content' => 1,
        // 'content-available' => 1, // for silent notifications
    ])
    ->withCustom([
        'order_id' => '123',
        'foo'      => 'bar',
    ]);

$platformOverrides = [
    'android' => $android,
    'apns'    => $apns,
];

$message = new PushMessage(
    'Hello from DEV1 Notify with Custom Channels',
    'If you see this notification, FCM v1 with custom channels is working as a charm. Thanks for trusting in DEV1.',
    [], // global data
    $platformOverrides
);

$target = new PushTarget($deviceToken);

$result = $registry->client()->send($message, $target);

// ============ PRINTS RESULT =============
echo "Success: "      . ($result->success ? 'true' : 'false') . PHP_EOL;
echo "ID: "           . ($result->id ?? '(none)') . PHP_EOL;
echo "ErrorCode: "    . ($result->errorCode ?? '(none)') . PHP_EOL;
echo "ErrorMessage: " . ($result->errorMessage ?? '(none)') . PHP_EOL;
