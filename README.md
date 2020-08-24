# quickpay-oscommerce-module-phoenix
Quickpay payment module for osCommerce Phoenix
Modular package.

Version 1.0.0 20/08/2020

Compatibility:
Quickpay API v10 with
- osCommerce Phoenix v1.0.7.7

Built by Genuineq (office@genuineq.com) from the existing quickpay payment module: https://github.com/QuickPay/oscommerce-module
Version 1.0 sponsored by Quickpay.net

Support thread on osCommerce forums:
https://forums.oscommerce.com/topic/412146-quickpay-payment-module-for-23/

Changelog
1.0.0
- Indented all code to ease future development.
- Fixed not defined variable warnings:
  * Warning: Use of undefined constant MODULE_PAYMENT_QUICKPAY_ZONE
  * Warning: Use of undefined constant MODULE_PAYMENT_QUICKPAY_ADVANCED_APIKEY
- Added all quickpay payment options logos.
- Added translations for missing payment options.
- Two files updated for minor compatibility issues. Symptoms:
   * on databases set up by a previous addon version, all orders were treated as if quickpay leading to Warning: array_reverse expects parameter 1 to be an array
  * link in confirmation email sent customer to FILENAME_ACCOUNT_HISTORY_INFO
