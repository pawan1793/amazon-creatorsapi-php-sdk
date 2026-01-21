# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-21

### Added
- Initial release of Amazon Creators API PHP SDK
- Support for all Creators API operations:
  - GetItems - Retrieve detailed product information
  - SearchItems - Search for products
  - GetBrowseNodes - Get browse node information
  - GetVariations - Get product variations
  - GetFeed - Retrieve feed information
  - ListFeeds - List available feeds
  - GetReport - Retrieve report data
  - ListReports - List available reports
- OAuth2 authentication support
- Comprehensive model classes for all API responses
- Example code samples for all operations
- PSR-4 autoloading support
- Full documentation and setup instructions

### Requirements
- PHP 8.1 or higher
- cURL extension
- JSON extension
- mbstring extension
- Guzzle HTTP client (^7.3)
