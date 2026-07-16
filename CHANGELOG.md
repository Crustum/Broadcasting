# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0]

### Added

- `Broadcasting::bulk()` / `queueBulk()` for personalized multi-channel fan-out; `BulkBroadcastJob` for chunked queue processing
- `BroadcasterInterface::bulkBroadcast()` with shared normalize helpers on `BaseBroadcaster`; `PusherBroadcaster` uses `triggerBatch` (default chunk 100, configurable via `bulk.max_batch_size`)
- `RedisBroadcaster::bulkBroadcast()` publishes personalized channel/message pairs in one Lua `eval` per chunk
- `LogBroadcaster::bulkBroadcast()` writes a single summary log line for the batch
- TestSuite helpers for bulk queue assertions (`assertBulkBroadcastQueued*`, `getQueuedBulkBroadcastJobs` / `Items`)
- Optional JSONP responses for Pusher channel auth when `jsonp` is enabled on the connection (`allowJsonp`); auth controller passes through `Response` bodies
- `UniqueBroadcastJob` with Cake Queue uniqueness (`$shouldBeUnique`); fluent `PendingBroadcast::unique()` and optional event `broadcastUnique()` / `broadcastUniqueKey()`
- `uniqueCache` documentation on the broadcasting queue connection config
- Broadcaster creation errors wrap the original failure with the connection name when `fallback` is `false`
- Integration tests for facade/send/queue paths and fluent `PendingBroadcast` APIs
- Unique-job dedupe simulation in `TestQueueAdapter`

### Changed

- Queue name resolution uses a dedicated `resolveBroadcastQueue()` helper

### Fixed

- Pusher auth JSONP wrapping when the client sends a `callback` query parameter and JSONP is allowed

### Documentation

- Document manual `/broadcasting/auth` route registration when plugin routes are not loaded
- Document Echo React/Vue `useConnectionStatus` hook and status values
- Document Echo `stopListeningForNotification` for notification channels

## [1.1.0]

### Added

- `BroadcastingTrait` for table classes to provide broadcasting functionality
- `BroadcastingTraitInterface` for type-safe broadcasting method contracts
- `TestQueueAdapter` for testing queued broadcasts in test suites
- Queue assertion methods to `BroadcastingTrait` test helper (`assertBroadcastQueued`, `assertBroadcastQueuedToChannel`, `assertNoBroadcastsQueued`, etc.)
- Comprehensive test suite for `BroadcastingTrait` with full coverage

### Changed

- **BREAKING**: `BroadcastingBehavior` now requires tables to use `BroadcastingTrait` and implement `BroadcastingTraitInterface`
- Broadcasting logic moved from `BroadcastingBehavior` to `BroadcastingTrait` for better separation of concerns
- Updated PHPUnit from 10.5 to 12.5

## [1.0.3]

### Fixed

- Safe channel registration during bootstrap when broadcaster not yet initialized

## [1.0.2]

### Added

- Channels file loading during plugin bootstrap
- Optional broadcast message logging with `Broadcasting.log` configuration

### Changed

- Broadcaster configuration now uses `className` instead of `driver` (BREAKING)

## [1.0.1]

### Added

- Plugin manifest integration for automated configuration installation
- `manifest()` method to `BroadcastingPlugin` implementing `ManifestInterface`
- Automatic installation of `config/broadcasting.php` configuration file
- Automatic installation of `config/channels.php` file from example template
- Automatic bootstrap file configuration loading
- GitHub star repository prompt support

### Changed

- Plugin now uses manifest system for configuration setup
- Configuration files are installed via `bin/cake manifest install --plugin Crustum/Broadcasting`

## [1.0.0]

### Added

- Core broadcasting system for WebSocket-based real-time event broadcasting with support for public, private, presence, and encrypted private channels
- Multiple broadcaster drivers (Pusher Channels, Redis, Log, Null) with queue adapter system for async broadcasting
- Channel authorization system with callbacks and channel classes, plus authentication controller and routes
- Model broadcasting behavior for automatic entity event broadcasting, with conditional and queueable interfaces
- Testing utilities with BroadcastingTrait for comprehensive test assertions
