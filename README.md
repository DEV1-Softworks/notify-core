# DEV1 Notify Core

Driver-agnostic notifications core for PHP (starting with Firebase Cloud Messaging HTTP v1).  
Built in Mexico by **DEV1 Softworks Labs**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE) [![Version: 1.1](https://img.shields.io/badge/version-1.1-green.svg)](#)

---

## Features
- PSR-18 / PSR-17 compatible (agnostic HTTP client)
- Token provider via Google Service Account (JWT → OAuth2)
- Send push notifications with **Firebase Cloud Messaging (HTTP v1)**
- Extensible: future drivers (Twilio, OneSignal, APNs, etc.)
- Registry to manage multiple clients by name

---

## Installation

Require the library (PHP 7.4+):

```bash
composer require dev1/notify-core
```

For development and examples, you’ll need a PSR-18 client and PSR-17 factories.  
We recommend:

```bash
composer require nyholm/psr7 symfony/http-client --dev
```

---

## Usage Example

```php
use Dev1\NotifyCore\Auth\GoogleServiceAccountTokenProvider;
use Dev1\NotifyCore\DTO\{PushMessage, PushTarget};
use Dev1\NotifyCore\Factory\FcmClientFactory;
use Dev1\NotifyCore\Registry\ClientRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

// Load Google Service Account JSON
$sa = json_decode(file_get_contents('service-account.json'), true);

// PSR factories
$psr17 = new Psr17Factory();
$http  = new Psr18Client();

// Token provider
$tokenProvider = new GoogleServiceAccountTokenProvider(
    $http,
    $psr17,
    $psr17,
    null,
    [
        'client_email' => $sa['client_email'],
        'private_key'  => $sa['private_key'],
    ]
);

// Create FCM client
$fcmClient = FcmClientFactory::create(
    $http,
    $psr17,
    $psr17,
    $tokenProvider,
    null,
    ['project_id' => 'your-project-id']
);

// Registry
$registry = new ClientRegistry();
$registry->register('fcm', $fcmClient, true);

// Message
$message = new PushMessage(
    'Hello from DEV1 Notify',
    'This is a test notification',
    ['foo' => 'bar']
);
$target = new PushTarget('DEVICE_TOKEN');

// Send
$result = $registry->client()->send($message, $target);

var_dump($result);
```

---

## Requirements
- PHP ^7.4
- ext-openssl
- A Firebase Project with a Service Account JSON (Legacy FCM is not compatible)

---

## Roadmap
- [ ] Add more drivers (Twilio Notify, OneSignal, APNs)
- [x] Laravel adapter (`dev1/laravel-notify`)
- [ ] Symfony bundle (`dev1/symfony-notify-bundle`)
- [ ] Unit tests & CI
- [x] Advanced platform overrides (Android/APNs)

---

## License
MIT License © DEV1 Softworks Labs

---

## Contributing

We welcome contributions from the community! To get started:

1. Fork the repository and create a new branch for your changes.
2. Follow the existing code style and add tests where appropriate.
3. Open a pull request describing your changes and the motivation behind them.
4. Check the issues tab for open tasks or suggest new features.

If you have questions or need guidance, please open an issue or start a discussion. Thank you for considering contributing to DEV1 Notify Core!
