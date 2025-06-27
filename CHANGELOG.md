# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-06-27

### Added
- Initial release of LogDash PHP SDK
- Logger with multiple log levels (error, warn, info, http, verbose, debug, silly)
- Metrics API with set and mutate operations
- HTTP synchronization for logs and metrics
- Support for local-only logging when no API key is provided
- Colored console output for improved debugging
- Non-blocking error handling for production environments
- PSR-4 autoloading support
- Comprehensive documentation and examples

### Features
- Zero-configuration setup
- Real-time cloud synchronization
- Framework-agnostic design
- PHP 8.1+ compatibility
- Guzzle HTTP client integration
- PHPUnit test suite
- PHPStan static analysis
- PHP CodeSniffer integration
