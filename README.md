# Strike Lightning Payment Gateway for WooCommerce

A WordPress plugin that enables Bitcoin Lightning Network payments via the Strike API for WooCommerce stores.

## Features

- **Bitcoin Lightning Payments**: Accept instant Bitcoin payments through the Lightning Network
- **Strike API Integration**: Uses the official Strike API v1 with receive requests
- **QR Code Display**: Automatically generates QR codes for easy mobile payments
- **Real-time Status Updates**: Automatically checks payment status every 10 seconds
- **Instant Payment Detection**: Detects Lightning payments within seconds
- **Webhook Support**: Receives instant notifications when payments are completed
- **Admin Dashboard**: Easy configuration and API key management with test tools
- **Debug Tools**: Built-in debugging interface for troubleshooting
- **Responsive Design**: Works on desktop and mobile devices
- **WooCommerce Blocks Support**: Compatible with modern WooCommerce checkout flows

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Strike API account and API key

## Installation

1. Upload the plugin files to `/wp-content/plugins/strike-lightning-payment/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Payments
4. Enable "Strike Lightning Payment"
5. Configure your Strike API settings

## Configuration

### Getting Your Strike API Key

1. Sign up for a Strike account at [strike.me](https://strike.me)
2. Go to your account dashboard and navigate to API settings
3. Generate a new API key with the following scopes:
   - `partner.receive-request.create` (Required for creating payment requests)
   - `partner.receive-request.read` (Required for checking payment status)
   - `partner.webhooks.manage` (Optional, for webhook management)
4. Copy the API key to your plugin settings

**Important**: Make sure your API key has the correct scopes, especially the receive-request permissions.

### Plugin Settings

1. **API Key**: Enter your Strike API key
2. **Environment**: Choose between Production or Sandbox
3. **Webhook URL**: Copy this URL to your Strike webhook settings (optional)
4. **Debug Tools**: Use the built-in debug interface to test API connectivity

### WooCommerce Settings

1. Go to WooCommerce > Settings > Payments
2. Find "Strike Lightning Payment" and click "Manage"
3. Configure the payment gateway settings:
   - Enable/Disable the gateway
   - Set the title and description
   - Configure API settings

## Usage

### For Customers

1. During checkout, select "Bitcoin Lightning Payment" as the payment method
2. Complete the order to be redirected to the payment page
3. Scan the QR code with a Lightning wallet or copy the Lightning invoice
4. Complete the payment in your Lightning wallet
5. The order will automatically update when payment is received

### For Store Owners

1. Configure your Strike API settings
2. Set up webhooks in your Strike account
3. Test the payment flow with small amounts
4. Monitor payments through the WooCommerce order management

## Supported Lightning Wallets

- Strike (iOS/Android)
- Coinos.io
- Aqua Wallet
- Phoenix Wallet
- Breez Wallet
- BlueWallet
- Zap
- And many other Lightning-compatible wallets

## Webhook Setup (Optional)

**Note**: Webhooks are optional. The plugin uses real-time polling and detects payments within seconds even without webhooks.

1. In your Strike account settings, add a webhook URL
2. Use the webhook URL provided in the plugin settings
3. Enable the following events:
   - `receive-request.receive-pending` (Payment detected)
   - `receive-request.receive-completed` (Payment confirmed)

## Troubleshooting

### Common Issues

1. **API Key Not Working**
   - Verify your API key is correct
   - Check if you're using the right environment (Production vs Sandbox)
   - Ensure your API key has the required scopes (`partner.receive-request.create` and `partner.receive-request.read`)
   - Use the debug tools in WordPress Admin → Settings → Strike Lightning

2. **Payments Not Updating**
   - The plugin polls for payment status every 10 seconds
   - Check WordPress error logs for API errors
   - Use the debug tools to test API connectivity
   - Verify the order has proper Strike payment data

3. **QR Code Not Displaying**
   - Ensure JavaScript is enabled
   - Check browser console for errors
   - Verify QR code library is loading
   - Check if the Strike API is properly creating receive requests

4. **"Payment detected, waiting for confirmation" appears before payment**
   - This is normal behavior when no receives are detected yet
   - The status will update to "Waiting for payment..." in recent versions

### Debug Mode

1. **Built-in Debug Tools**: Go to WordPress Admin → Settings → Strike Lightning
   - Test API connectivity
   - Debug specific orders
   - View detailed API responses

2. **WordPress Debug Mode**: Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

3. **Browser Console**: Check browser console for JavaScript errors and payment status updates

## Security

- All API communications use HTTPS
- Webhook signatures are verified
- API keys are stored securely
- No sensitive data is logged

## Support

For support with this plugin:
1. Check the troubleshooting section above
2. Review the Strike API documentation
3. Contact the plugin developer

For Strike API support:
- Visit [strike.me/docs](https://strike.me/docs)
- Contact Strike support

## Changelog

### Version 2.1.1
- Fixed payment detection for Strike API v1 paginated responses
- Added real-time status updates with proper messaging
- Improved API error handling and debugging
- Enhanced webhook support for receive-request events
- Added comprehensive debug tools in admin interface
- Fixed duplicate payment container issues
- Updated to use proper Strike API scopes

### Version 2.0.x
- Major refactor for Strike API v1 compatibility
- Added receive request support
- Improved payment status checking
- Enhanced error handling and logging
- Added WooCommerce Blocks support

### Version 1.0.0
- Initial release
- Basic Lightning payment integration
- QR code generation
- Webhook support
- Admin dashboard

## Related Plugins:

* [Payment with Coinos](https://github.com/Mnpezz/coinos-woo-payment)
* [Lightning Rewards with Coinos](https://github.com/Mnpezz/coinos-wordpress-rewards)

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please visit the [GitHub repository](https://github.com/mnpezz/strike-woo-payment) to:
- Submit pull requests
- Open issues for bugs and feature requests
- View the latest development updates

## Disclaimer

This plugin is not officially affiliated with Strike. Use at your own risk. Always test with small amounts before processing real payments.
