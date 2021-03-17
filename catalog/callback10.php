<?php
/**
 * osCommerce Phoenix, Open Source E-Commerce Solutions
 * http://www.oscommerce.com
 *
 * Copyright (c) 2020 osCommerce
 *
 * Released under the GNU General Public License
 *
 * author: Genuineq office@genuineq.com
 */

require('includes/application_top.php');

/* Compatibility fixes */
if (!defined('DIR_WS_CLASSES')) define('DIR_WS_CLASSES','includes/classes/');
if (!defined('DIR_WS_LANGUAGES')) define('DIR_WS_LANGUAGES','includes/languages/');
if (!defined('FILENAME_CHECKOUT_PROCESS')) define('FILENAME_CHECKOUT_PROCESS','checkout_process.php');
if (!defined('FILENAME_ACCOUNT_HISTORY_INFO')) define('FILENAME_ACCOUNT_HISTORY_INFO','account_history_info.php');
if (!defined('TABLE_ORDERS')) define('TABLE_ORDERS','orders');
if (!defined('TABLE_ORDERS_STATUS_HISTORY')) define('TABLE_ORDERS_STATUS_HISTORY','orders_status_history');


include(DIR_WS_LANGUAGES . $language . '/modules/payment/unzer_advanced.php');

require(DIR_FS_CATALOG.DIR_WS_CLASSES.'UnzerApi.php');

$oid = MODULE_PAYMENT_UNZER_ADVANCED_ORDERPREFIX.sprintf('%04d', $_GET["oid"]);

$unzer = new UnzerApi;

$unzer->setOptions( MODULE_PAYMENT_UNZER_ADVANCED_USERAPIKEY);
if(MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION != "Normal"){
    $unzer->mode = 'subscriptions?order_id=';
}else{
    $unzer->mode = 'payments?order_id=';
}

// Commit the status request, checking valid transaction id
$str = $unzer->status($oid);

$log = "Callback request " . date('d-m-Y H:i:s') . "\n" . print_r($_REQUEST,true) . "\n";
//$log .= "Api status return " . date('d-m-Y H:i:s') . "\n" . print_r($str,true) . "\n";

$str[0]["operations"] = array_reverse($str[0]["operations"]);

$log .= "After reverse " . date('d-m-Y H:i:s') . "\n" . print_r($str,true) . "\n";

$unzer_status = $str[0]["operations"][0]["unzer_status_code"];
$unzer_type = strtolower($str[0]["type"]);
$unzer_operations_type = $str[0]["operations"][0]["type"];
$unzer_capture = $str[0]["link"]["auto_capture"];
$unzer_vars = $str[0]["variables"];
$unzer_id = $str[0]["id"];
$unzer_order_id = $_GET["oid"];
$unzer_aq_status_code = $str[0]["operations"][0]["aq_status_code"];
$unzer_aq_status_msg = $str[0]["operations"][0]["aq_status_msg"];
$unzer_cardtype = $str[0]["metadata"]["brand"];
$unzer_cardhash_nr = $str[0]["metadata"]["hash"];
$unzer_status_msg = $str[0]["operations"][0]["unzer_status_msg"]."\n"."Cardhash: ".$unzer_cardhash_nr."\n";
$unzer_cardnumber = "xxxx-xxxxxx-".$str[0]["metadata"]["last4"];
$unzer_amount = $str[0]["operations"][0]["amount"];
$unzer_currency = $str[0]["currency"];
$unzer_pending = ($str[0]["operations"][0]["pending"] == "true" ? " - pending ": "");
$unzer_expire = $str[0]["metadata"]["exp_month"]."-".$str[0]["metadata"]["exp_year"];
$unzer_cardhash = ($unzer_type == "subscription") ? " Subscription" : "";
/* TODO */
$unzer_currency_code = '';

$log .= "status $unzer_status " . "\n";
file_put_contents('unzer-api.log', $log, FILE_APPEND);
$log = '';

if (!$unzer_status) {
    // if (!$str[0]["id"]) {
    // Request is NOT authenticated or transaction does not exist

    $sql_data_array = array('cc_transactionid' => MODULE_PAYMENT_UNZER_ADVANCED_ERROR_TRANSACTION_DECLINED);
    tep_db_perform(TABLE_ORDERS, $sql_data_array, 'update', "orders_id = '" . $unzer_order_id . "'");

    $log .= "no id... " . "\n";
    $log .= "----------------- " . "\n";
    file_put_contents('unzer-api.log', $log, FILE_APPEND);

    exit();

}

$unzer_approved = false;
/*
20000  Approved
40000  Rejected By Acquirer
40001  Request Data Error
50000  Gateway Error
50300  Communications Error (with Acquirer)
*/

switch ($unzer_status) {
    case '20000':
        // approved
        $unzer_approved = true;

        break;

    case '40000':
    case '40001':
        // Error in request data.
        // write status message into order to retrieve it as error message on checkout_payment

        $sql_data_array = array(
            'cc_transactionid' => tep_db_input($unzer_status_msg),
            'last_modified' => 'now()',
            'orders_status_id' => MODULE_PAYMENT_UNZER_ADVANCED_REJECTED_ORDER_STATUS_ID
        );

        // reject order by updating status
        tep_db_perform(TABLE_ORDERS, $sql_data_array, 'update', "orders_id = '" . $unzer_order_id . "'");


      $sql_data_array = array(
            'orders_id' => $unzer_order_id,
            'orders_status_id' => MODULE_PAYMENT_UNZER_ADVANCED_REJECTED_ORDER_STATUS_ID,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Callback: Unzer Payment rejected [message: '.$unzer_operations_type.'-'. $unzer_status_msg . ' - '.$unzer_aq_status_msg.']'
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        break;

    default:
        $sql_data_array = array('cc_transactionid' => $unzer_status, 'last_modified' => 'now()');

        // approve order by updating status
        tep_db_perform(TABLE_ORDERS, $sql_data_array, 'update', "orders_id = '" . $unzer_order_id . "'");


        $sql_data_array = array(
            'orders_id' => $unzer_order_id,
            'orders_status_id' => MODULE_PAYMENT_UNZER_ADVANCED_ERROR_SYSTEM_FAILURE,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Callback: Unzer Payment approved [message: '.$unzer_operations_type.'-'. $unzer_status_msg . ' - '.$unzer_aq_status_msg.']'
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        break;
}

$log .= "----------------- " . "\n";
file_put_contents('unzer-api.log', $log, FILE_APPEND);

if ($unzer_approved) {
    $sql = "select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $unzer_order_id . "'";
    $order_query = tep_db_query($sql);

    if (tep_db_num_rows($order_query) > 0) {
        $order = tep_db_fetch_array($order_query);

        // $comment_status = "Transaction: ".$str["id"] . $unzer_pending.' (' . $unzer_cardtype . ' ' . $currencies->format($unzer_amount / 100, false, $unzer_currency) . ') '. $unzer_status_msg;

        // set order status as configured in the module
        $order_status_id = (MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID > 0 ? (int) MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID : (int) DEFAULT_ORDERS_STATUS_ID);

        $sql_data_array = array(
            'cc_transactionid' => $str[0]["id"],
            'cc_type' => $unzer_cardtype,
            'cc_number' => $unzer_cardnumber,
            'cc_expires' => ($unzer_expire ? $unzer_expire : 'N/A'),
            'cc_cardhash' => $unzer_cardhash,
            'orders_status' => $order_status_id,
            'last_modified' => 'now()'
        );

        // approve order by updating status
        tep_db_perform(TABLE_ORDERS, $sql_data_array, 'update', "orders_id = '" . $unzer_order_id . "'");


        // write/update into order history
        $sql_data_array = array(
            'orders_id' => $unzer_order_id,
            'orders_status_id' => $order_status_id,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Callback: Unzer Payment approved [message: '.$unzer_operations_type.'-'. $unzer_status_msg . ' - '.$unzer_aq_status_msg.']'
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        //subscription handling
        if($unzer_type == "subscription"){
            $apiorder= new UnzerApi();
            $apiorder->setOptions(MODULE_PAYMENT_UNZER_ADVANCED_USERAPIKEY);
            $apiorder->mode = "subscriptions/";
            $addlink = $unzer_id."/recurring/";
            $unzer_autocapture = (MODULE_PAYMENT_UNZER_ADVANCED_AUTOCAPTURE == "No" ? FALSE : TRUE);
            //create new unzer order
            $process_parameters["amount"]= $unzer_amount;
            $process_parameters["order_id"]= $unzer_order_id."-".$unzer_id;
            $process_parameters["auto_capture"]= $unzer_autocapture;
            $storder = $apiorder->createorder($unzer_order_id, /* TODO */$unzer_currency_code, $process_parameters, $addlink);
        }

		/* TODO */
        // payment link sent by admin approved, but customer is not logged in
        // if($unzer_approved && $str[0]["link"]["reference_title"] == "admin link" && !tep_session_is_registered('customer_id')){
        //
        //     /** Update order status */
        //     tep_db_query("update orders set orders_status = '" . (int)$order_status_id . "', last_modified = now() where orders_id = '" . (int)$unzer_order_id . "'");
        //     $customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
        //     $sql_data = [
        //       'orders_id' => $unzer_order_id,
        //       'orders_status_id' => (int)$order_status_id,
        //       'date_added' => 'now()',
        //       'customer_notified' => 1,
        //       'comments' => '',
        //     ];
        //     tep_db_perform('orders_status_history', $sql_data);
        //
        //     $GLOBALS['hooks']->register_pipeline('reset');
        // }
    }
}

require('includes/application_bottom.php');

?>











<!-- NEW API VERSION RESPONSE

[id] => 232438533
[merchant_id] => 115558
[order_id] => 3ph_0002
[accepted] => 1
[type] => Payment
[text_on_statement] =>
[branding_id] =>
[variables] => Array
    (
    )

[currency] => USD
[state] => new
[metadata] => Array
    (
        [type] => card
        [origin] => form
        [brand] => visa
        [bin] => 100000
        [corporate] =>
        [last4] => 0000
        [exp_month] => 12
        [exp_year] => 2022
        [country] => SLV
        [is_3d_secure] =>
        [issued_to] =>
        [hash] => 1ba03a551813439f18f50OxQB2no29tSImaQdZT3wbIKlZJbKZW
        [number] =>
        [customer_ip] => 92.86.156.28
        [customer_country] => RO
        [fraud_suspected] =>
        [fraud_remarks] => Array
            (
            )

        [fraud_reported] =>
        [fraud_report_description] =>
        [fraud_reported_at] =>
        [nin_number] =>
        [nin_country_code] =>
        [nin_gender] =>
        [shopsystem_name] => OsCommerce Phoenix
        [shopsystem_version] => 1.0.2
    )

[link] => Array
    (
        [url] => https://payment.unzerdirect.com/payments/b59758c803113a9f6cac916389e444f0c83a3c9320a0f310193c20df07f9b688
        [agreement_id] => 417144
        [language] => en
        [amount] => 1349
        [continue_url] => http://2778e6c060e4.ngrok.io/unzer/CE-Phoenix-1.0.7.12/checkout_process.php?cart_Unzer_ID=20761-2
        [cancel_url] => http://2778e6c060e4.ngrok.io/unzer/CE-Phoenix-1.0.7.12/checkout_payment.php?payment_error=unzer_advanced
        [callback_url] => http://2778e6c060e4.ngrok.io/unzer/CE-Phoenix-1.0.7.12/callback10.php?oid=2
        [payment_methods] => creditcard
        [auto_fee] =>
        [auto_capture] =>
        [branding_id] =>
        [google_analytics_client_id] =>
        [google_analytics_tracking_id] =>
        [version] => v10
        [acquirer] =>
        [deadline] =>
        [framed] =>
        [branding_config] => Array
            (
            )

        [invoice_address_selection] =>
        [shipping_address_selection] =>
        [customer_email] =>
    )

[shipping_address] => Array
    (
        [name] => Beck Harding
        [att] =>
        [street] => Labore inventore quo
        [city] => Molestiae eaque et c
        [zip_code] => Ipsum necessitatibus
        [region] => Consequatur Aut fug
        [country_code] => LUX
        [vat_no] =>
        [company_name] =>
        [house_number] =>
        [house_extension] =>
        [phone_number] =>
        [mobile_number] => +1 (977) 622-9781
        [email] => test@test.com
    )

[invoice_address] => Array
    (
        [name] => Beck Harding
        [att] =>
        [street] => Labore inventore quo
        [city] => Molestiae eaque et c
        [zip_code] => Ipsum necessitatibus
        [region] => Consequatur Aut fug
        [country_code] => LUX
        [vat_no] =>
        [company_name] =>
        [house_number] =>
        [house_extension] =>
        [phone_number] =>
        [mobile_number] => +1 (977) 622-9781
        [email] => test@test.com
    )

[basket] => Array
    (
        [0] => Array
            (
                [qty] => 1
                [item_no] => 9
                [item_name] => Lime
                [item_price] => 8
                [vat_rate] =>
            )

    )

[shipping] =>
[operations] => Array
    (
        [0] => Array
            (
                [id] => 1
                [type] => authorize
                [amount] => 1349
                [pending] =>
                [unzer_status_code] => 20000
                [unzer_status_msg] => Approved
                [aq_status_code] => 20000
                [aq_status_msg] => Approved
                [data] => Array
                    (
                    )

                [callback_url] => http://2778e6c060e4.ngrok.io/unzer/CE-Phoenix-1.0.7.12/callback10.php?oid=2
                [callback_success] =>
                [callback_response_code] =>
                [callback_duration] =>
                [acquirer] => clearhaus
                [3d_secure_status] =>
                [callback_at] =>
                [created_at] => 2021-02-17T13:32:44Z
            )

    )

[test_mode] => 1
[acquirer] => clearhaus
[facilitator] =>
[created_at] => 2021-02-17T13:32:35Z
[updated_at] => 2021-02-17T13:32:44Z
[retented_at] =>
[balance] => 0
[fee] =>
[deadline_at] =>

-->








<!-- OLD API VERSION RESPONSE {
  "id": 7,
  "order_id": "Order7",
  "accepted": true,
  "test_mode": true,
  "branding_id": null,
  "variables": {},
  "acquirer": "nets",
  "operations": [
    {
      "id": 1,
      "type": "authorize",
      "amount": 123,
      "pending": false,
      "unzer_status_code": "20000",
      "unzer_status_msg": "Approved",
      "aq_status_code": "000",
      "aq_status_msg": "Approved",
      "data": {},
      "created_at": "2015-03-05T10:06:18+00:00"
    }
  ],
  "metadata": {
    "type": "card",
    "brand": "unzer-test-card",
    "last4": "0008",
    "exp_month": 8,
    "exp_year": 2019,
    "country": "DK",
    "is_3d_secure": false,
    "customer_ip": "195.41.47.54",
    "customer_country": "DK"
  },
  "created_at": "2015-03-05T10:06:18Z",
  "balance": 0,
  "currency": "DKK"
}-->
