# Changelog

All notable changes to **DEV1 Notify Core** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.0] - 2025-09-18
### Added
- First public release of **DEV1 Notify Core**.
- Contracts and DTOs: `PushClient`, `PushMessage`, `PushTarget`, `PushResult`.
- Token provider: `AccessTokenProvider` and `GoogleServiceAccountTokenProvider`.
- `FcmHttpV1Client` driver to send push notifications via **Firebase Cloud Messaging HTTP v1**.
- `ClientRegistry` to manage multiple clients by name.
- `FcmClientFactory` to easily instantiate FCM clients.
- Working example in `examples/send_fcm.php`.

---

## [1.1] - 2025-09-22
### Added
- Support for custom notification channels in **Firebase Cloud Messaging**.
- New example in `examples/send_fcm_channels.php`.
- `AndroidOptions` and `ApnsOptions` to configure platform-specific options.
- `platformOverrides` in `PushMessage` to set platform-specific options.

---

## [1.1] - 2025-09-22
### Modified
- Corrected APNs payload structure in `ApnsOptions` to comply with FCM v1 requirements.
- Fixed Android notification channel handling in `AndroidOptions`.
- Improved error handling in `FcmHttpV1Client`.

---

## [Unreleased]
- Support for additional providers (Twilio Notify, OneSignal, APNs).
- Adapters for Laravel and Symfony.
- Unit tests and CI/CD pipeline.
