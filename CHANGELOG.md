# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
