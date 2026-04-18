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

## [1.2] - 2026-04-18
### Added
- Optional PSR-16 token cache on `GoogleServiceAccountTokenProvider` (new `?CacheInterface $cache` constructor arg + `cache_key` config) so OAuth tokens can be shared across processes/requests.
- Automatic retry with exponential backoff + jitter on transient failures (5xx, 429, PSR-18 transport errors) in both the token provider and `FcmHttpV1Client`. Configurable via `max_retries` and `retry_base_delay_ms`.
- `FcmHttpV1Client` now extracts `error.details[*].errorCode` (e.g. `UNREGISTERED`, `QUOTA_EXCEEDED`) in preference to the generic `error.status`.
- `PushResult` helpers: `isUnregistered()`, `isInvalidArgument()`, `isQuotaExceeded()`, `isTransient()`.
- Data-only (silent) push support: `notification` block is omitted when `PushMessage` has empty title and body.
- `Dev1\NotifyCore\Version` constants + automatic `User-Agent: Dev1-Notify-Core/<ver>` header on every FCM and OAuth request.
- PHPUnit configuration (`phpunit.xml.dist`) and initial test suite covering DTOs, registry, FCM driver, token provider, retry behavior, and Android options.
- GitHub Actions CI/CD pipeline (`.github/workflows/ci.yml`): matrix tests on PHP 7.4–8.4 with pcov, enforces ≥80% line coverage on every push/PR, and on merges to `master` auto-publishes the `v<Version::VERSION>` tag (consumed by the Packagist webhook) and refreshes the shields.io coverage badge on the `badges` branch.

### Changed
- **Breaking:** `PushTarget` now throws `InvalidArgumentException` when more than one of `token`/`topic`/`condition` is provided (previously the driver silently preferred `token`).
- **Breaking:** `AndroidOptions::withPriority()` throws on unknown values instead of silently passing them through.
- `FcmHttpV1Client` no longer catches generic `\Throwable`; only PSR-18 `ClientExceptionInterface` is caught and surfaced as `TRANSPORT_ERROR`.
- Token acquisition errors now return a `PushResult` with `errorCode = TOKEN_ERROR` instead of crashing the send.
- `AndroidOptions::toArray()` emits `channel_id` exactly once.
- Missing return types added to `AndroidOptions::withChannelId()`, `AndroidOptions::merge()`, and `ApnsOptions::merge()`.
- `PushResult::$id` PHPDoc corrected to `string|null`.
- `ClientRegistry::remove()` now returns `bool` (true if a client was removed, false if unknown) while preserving idempotent semantics.
- `AccessTokenProvider::getToken()` PHPDoc now documents the `@throws` contract for failed acquisitions.
- Widened `psr/log` constraint to `^1.1 || ^2.0 || ^3.0` for compatibility with modern logger implementations.

### Fixed
- `FcmClientFactory::create` no longer uses a PHP 8-only trailing comma; library parses cleanly on PHP 7.4 again.
- `json_encode` failures in `buildAssertionJwt()` and the FCM payload are now surfaced explicitly instead of signing/sending the literal `false`.
- Non-scalar values in `PushMessage::$data` whose `json_encode` fails fall back to an empty string instead of the literal `"false"`.
- Dropped the deprecated `openssl_pkey_free()` call (no-op on PHP 8.x, GC handles both 7.4 and 8.x).
- Removed an unreachable `return ''` in `GoogleServiceAccountTokenProvider::getToken()`.

### Removed
- Orphan `Dev1\NotifyCore\Message` and `Dev1\NotifyCore\Builders\MessageBuilder` classes (never wired into the driver).
- Undocumented `timeout` config key on `FcmHttpV1Client` (it was never applied).

---

## [Unreleased]
- Support for additional providers (Twilio Notify, OneSignal, APNs).
- Adapters for Symfony.
