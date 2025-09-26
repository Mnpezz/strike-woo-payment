# Changelog

All notable changes to the Strike Lightning Payment Gateway for WooCommerce will be documented in this file.

## [2.1.1] - 2025-01-26

### 🎉 Major Improvements
- **Fixed payment detection for Strike API v1 paginated responses** - Resolved issue where plugin was reading pagination metadata instead of actual payment data
- **Enhanced real-time status updates** - Improved payment status messaging flow
- **Added comprehensive debug tools** - Built-in admin interface for troubleshooting (WordPress Admin → Settings → Strike Lightning)

### 🔧 Technical Fixes
- Fixed duplicate payment container issues
- Improved API error handling and logging
- Enhanced webhook support for receive-request events
- Updated to use proper Strike API scopes (`partner.receive-request.create` and `partner.receive-request.read`)
- Cleaned up temporary debug code for production readiness

### 📚 Documentation
- Updated README with comprehensive setup instructions
- Added troubleshooting guide with common issues
- Documented required API scopes
- Added debug tools usage instructions

## [2.0.x] - 2024

### 🚀 Major Refactor
- **Strike API v1 compatibility** - Complete rewrite for Strike's modern API
- **Added receive request support** - Uses Strike's recommended receive request flow
- **WooCommerce Blocks support** - Compatible with modern WooCommerce checkout
- **Improved payment status checking** - Real-time polling every 10 seconds
- **Enhanced error handling** - Better error messages and logging

## [1.0.0] - Initial Release

### ✨ Core Features
- Basic Lightning payment integration
- QR code generation for Lightning invoices
- Webhook support for payment notifications
- Admin dashboard for configuration
- Strike API integration

---

For detailed information about each release, visit the [GitHub repository](https://github.com/mnpezz/strike-woo-payment/releases).
