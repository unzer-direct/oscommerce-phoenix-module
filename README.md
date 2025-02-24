# unzer-oscommerce-module-phoenix
Unzer payment module for osCommerce Phoenix
Modular package.

Version 1.0.10 - 14.05.2022

Compatibility:
Unzer API v10 with
- osCommerce Phoenix v1.0.7.15

## Installation guide & steps can be found into `docs` folder
#

Built by Genuineq (office@genuineq.com) from the existing quickpay payment module: https://github.com/unzer-direct/oscommerce-module
Version 1.0 sponsored by Unzerdirect.com

Support thread on osCommerce forums:
https://forums.oscommerce.com/topic/412146-unzer-payment-module-for-23/

Changelog
#### 1.0.10
- Added checks for Direct Debit method
#### 1.0.9
- Added check for customer country for direct invoice method
#### 1.0.8
- Added check for Safari/Chrome browser for payment methods to be displayed
- Fix icon aspect ratio & table align
#### 1.0.7
- Keep only few payment methods, creditcard types
- Added german translation
- Added payment method description
#### 1.0.6
- Added conditions for Unzer Direct Invoice to show in frontend
#### 1.0.5
- Added Sofort payment method
#### 1.0.4
- Added Unzer Direct Invoice payment method
#### 1.0.3
- Added Apple Pay & Google pay payment methods
#### 1.0.2
- Added all payment request fields in accordance to the documentation.
- Removed custom variables from payment request.
#### 1.0.1
- Added possibility to configure the text displayed for the payment options.
#### 1.0.0
- Indented all code to ease future development.
- Fixed not defined variable warnings:
  * Warning: Use of undefined constant MODULE_PAYMENT_UNZER_ZONE
  * Warning: Use of undefined constant MODULE_PAYMENT_UNZER_ADVANCED_APIKEY
- Added all unzer payment options logos.
- Added translations for missing payment options.
- Two files updated for minor compatibility issues. Symptoms:
   * on databases set up by a previous addon version, all orders were treated as if unzer leading to Warning: array_reverse expects parameter 1 to be an array
  * link in confirmation email sent customer to FILENAME_ACCOUNT_HISTORY_INFO
