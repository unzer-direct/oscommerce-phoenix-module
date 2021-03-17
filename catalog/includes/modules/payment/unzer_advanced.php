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

/** Compatibility fixes */
if (!defined('DIR_WS_CLASSES')) define('DIR_WS_CLASSES','includes/classes/');
if (!defined('DIR_WS_CATALOG_IMAGES')) define('DIR_WS_CATALOG_IMAGES', DIR_WS_CATALOG . 'images/');
if (!defined('DIR_WS_ICONS')) define('DIR_WS_ICONS','images/icons/');
if (!defined('FILENAME_CHECKOUT_CONFIRMATION')) define('FILENAME_CHECKOUT_CONFIRMATION','checkout_confirmation.php');
if (!defined('FILENAME_CHECKOUT_PAYMENT')) define('FILENAME_CHECKOUT_PAYMENT','checkout_payment.php');
if (!defined('FILENAME_CHECKOUT_PROCESS')) define('FILENAME_CHECKOUT_PROCESS','checkout_process.php');
if (!defined('FILENAME_CHECKOUT_SUCCESS')) define('FILENAME_CHECKOUT_SUCCESS','checkout_success.php');

if (!defined('FILENAME_ACCOUNT_HISTORY_INFO')) define('FILENAME_ACCOUNT_HISTORY_INFO','account_history_info.php');
if (!defined('FILENAME_SHIPPING')) define('FILENAME_SHIPPING','shipping.php');

/** You can extend the following cards-array and upload corresponding titled images to images/icons */
if (!defined('MODULE_AVAILABLE_CREDITCARDS'))
define('MODULE_AVAILABLE_CREDITCARDS',array(
    '3d-dankort',
    '3d-jcb',
    '3d-visa',
    '3d-mastercard',
    'mastercard',
    'mastercard-debet',
    'american-express',
    'dankort',
    'diners',
    'jcb',
    'visa',
    'visa-electron',
    'viabill',
    'fbg1886',
    'paypal',
    'sofort',
    'mobilepay',
    'bitcoin',
    'swish',
    'trustly',
    'klarna',
    'maestro',
    'ideal',
    'paysafecard',
    'resurs',
    'vipps',
));

include(DIR_FS_CATALOG.DIR_WS_CLASSES.'UnzerApi.php');
include(DIR_FS_CATALOG.DIR_WS_CLASSES.'UnzerISO3166.php');

class unzer_advanced extends abstract_payment_module {
    const CONFIG_KEY_BASE = 'MODULE_PAYMENT_UNZER_ADVANCED_';

    public $code = 'unzer_advanced';

    /** Customize this setting for the number of payment groups needed */
    public $num_groups = 5;
    public $creditcardgroup;
    public $email_footer;
    public $order_status;

    private $api_version = '1.00';

    public function __construct() {
        parent::__construct();
        global $order,$cardlock;

        if (isset($_POST['cardlock'])) {
            $cardlock = $_POST['cardlock'];
        }

        $this->description = MODULE_PAYMENT_UNZER_ADVANCED_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_UNZER_ADVANCED_SORT_ORDER') ? MODULE_PAYMENT_UNZER_ADVANCED_SORT_ORDER : 0;
        $this->enabled = (defined('MODULE_PAYMENT_UNZER_ADVANCED_STATUS') && MODULE_PAYMENT_UNZER_ADVANCED_STATUS == 'True') ? (true) : (false);
        $this->creditcardgroup = array();
        $this->email_footer = ($cardlock == "viabill" || $cardlock == "viabill" ? DENUNCIATION : '');
        $this->order_status = (defined('MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID > 0)) ? ((int)MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID) : (0);

        if (is_object($order)) {
            $this->update_status();
        }

        /** V10 */
        if (isset($_POST['unzerIT']) && ("go" == $_POST['unzerIT']) && !isset($_SESSION['qlink'])) {
            $this->form_action_url = 'https://payment.unzerdirect.com/';
        } else {
            $this->form_action_url = tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL');
        }
    }

    /** Class methods */
    public function update_status() {
        global $order, $unzer_fee, $HTTP_POST_VARS, $unzer_card;

        if (($this->enabled == true) && defined('MODULE_PAYMENT_UNZER_ZONE') && ((int) MODULE_PAYMENT_UNZER_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_UNZER_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }

        if (!tep_session_is_registered('unzer_card')) {
            tep_session_register('unzer_card');
        }

        if (isset($_POST['unzer_card'])) {
            $unzer_card = $_POST['unzer_card'];
        }

        if (!tep_session_is_registered('cart_Unzer_ID')) {
            tep_session_register('cart_Unzer_ID');
        }

        if (isset($_GET['cart_Unzer_ID'])) {
            $unzer_card = $_GET['cart_Unzer_ID'];
        }

        if (!tep_session_is_registered('unzer_fee')) {
            tep_session_register('unzer_fee');
        }
    }

    public function javascript_validation() {
        $js = '  if (payment_value == "' . $this->code . '") {' . "\n" .
              '      var unzer_card_value = null;' . "\n" .
              '      if (document.checkout_payment.unzer_card.length) {' . "\n" .
              '          for (var i=0; i<document.checkout_payment.unzer_card.length; i++) {' . "\n" .
              '              if (document.checkout_payment.unzer_card[i].checked) {' . "\n" .
              '                  unzer_card_value = document.checkout_payment.unzer_card[i].value;' . "\n" .
              '              }' . "\n" .
              '          }' . "\n" .
              '      } else if (document.checkout_payment.unzer_card.checked) {' . "\n" .
              '          unzer_card_value = document.checkout_payment.unzer_card.value;' . "\n" .
              '      } else if (document.checkout_payment.unzer_card.value) {' . "\n" .
              '          unzer_card_value = document.checkout_payment.unzer_card.value;' . "\n" .
              '          document.checkout_payment.unzer_card.checked=true;' . "\n" .
              '      }' . "\n" .
              '      if (unzer_card_value == null) {' . "\n" .
              '          error_message = error_message + "' . MODULE_PAYMENT_UNZER_ADVANCED_TEXT_SELECT_CARD . '";' . "\n" .
              '          error = 1;' . "\n" .
              '      }' . "\n" .
              '      if (document.checkout_payment.cardlock.value == null) {' . "\n" .
              '          error_message = error_message + "' . MODULE_PAYMENT_UNZER_ADVANCED_TEXT_SELECT_CARD . '";' . "\n" .
              '          error = 1;' . "\n" .
              '      }' . "\n" .
              '  }' . "\n";
        return $js;
    }

    /* Define payment method selector on checkout page */
    public function selection() {
        global $order, $currencies, $unzer_card, $cardlock;
        $qty_groups = 0;

        /** Count how many MODULE_PAYMENT_UNZER_ADVANCED_GROUP are configured. */
        for ($i = 1; $i <= $this->num_groups; $i++) {
            if (constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i) == '') {
                continue;
            }

            $qty_groups++;
        }

        if($qty_groups > 1) {
            $selection = array('id' => $this->code, 'module' => $this->title. tep_draw_hidden_field('cardlock', $cardlock ));
        }

        /** Parse all the configured MODULE_PAYMENT_UNZER_ADVANCED_GROUP */
        $selection['fields'] = array();
        $msg = '<table width="100%"><tr style="background-color: transparent !important;border-top: 0 !important;"><td style="background-color: transparent !important;border-top: 0 !important;">';
        $optscount=0;
        for ($i = 1; $i <= $this->num_groups; $i++) {
            $options_text = '';
            if (defined('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i) && constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i) != '') {
                $payment_options = preg_split('[\,\;]', constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i));
                foreach ($payment_options as $option) {

                    $cost = (MODULE_PAYMENT_UNZER_ADVANCED_AUTOFEE == "No" || $option == 'viabill' ? "0" : "1");
                    if($option=="creditcard"){

						$msg .= "<div class='creditcard_pm_title'>";

                        /** Configuring the text to be shown for the payment group. If there is an input in the text field for that payment option, that value will be shown to the user, otherwise, the default value will be used.*/
                        if(defined('MODULE_PAYMENT_UNZER_ADVANCED_GROUP'.$i.'_TEXT') && constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i . '_TEXT') != ''){
                            $msg .= constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i . '_TEXT')."</div>";
                        }else {
                            $msg .= $this->get_payment_options_name($option)."</div>";
                        }

                        $msg .= "<br>";

						$optscount++;
                        /** Read the logos defined on admin panel **/
                        $cards = explode(";",MODULE_PAYMENT_UNZER_CARD_LOGOS);
                        foreach ($cards as $optionc) {
                            $iconc = "";
                            if(file_exists(DIR_WS_ICONS.$optionc.".png")){
                              $iconc = DIR_WS_ICONS.$optionc.".png";
                            }elseif(file_exists(DIR_WS_ICONS.$optionc.".jpg")){
                              $iconc = DIR_WS_ICONS.$optionc.".jpg";
                            }elseif(file_exists(DIR_WS_ICONS.$optionc.".gif")){
                              $iconc = DIR_WS_ICONS.$optionc.".gif";
                            }

                            /** Define payment icons width */
                            $w = 35;
                            $h = 22;
                            $space = 5;

                            $msg .= tep_image($iconc,$optionc,$w,$h,'style="position:relative;border:0px;float:left;margin:'.$space.'px;" ');
                        }




                        $msg .= '</td></tr></table>';
              			$options_text=$msg;

                        if($qty_groups==1){
                            $selection = array(
                                'id' => $this->code,
                                'module' => '<table width="100%" border="0">
                                                <tr class="moduleRow table-selection" onclick="selectUnzerRowEffect(this, ' . ($optscount-1) . ',\''.$option.'\');event.stopImmediatePropagation();">
                                                    <td class="main" style="height:22px;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;">' .$options_text.($cost !=0 ? '</td><td class="main" style="height:22px;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;"> (+ '.MODULE_PAYMENT_UNZER_ADVANCED_FEELOCKINFO.')' :'').'
                                                        </td>
                                                </tr>'.'
                                            </table>'.tep_draw_hidden_field('cardlock', $option));


                        }else{
                            $selection['fields'][] = array(
                                'title' => '<table width="100%" border="0">
                                                <tr class="moduleRow table-selection" style="background-color: transparent !important;border-top: 0 !important;" onclick="selectUnzerRowEffect(this, ' . ($optscount-1) . ',\''.$option.'\');event.stopImmediatePropagation();">
                                                    <td class="main" style="height:22px;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;">' . $options_text.($cost !=0 ? '</td><td style="height:22px;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;">(+ '.MODULE_PAYMENT_UNZER_ADVANCED_FEELOCKINFO.')' :'').'
                                                        </td>
                                                </tr>'.'
                                            </table>',
                                'field' => tep_draw_radio_field(
                                    'unzer_card',
                                    '',
                                    ($option==$cardlock ? true : false),
                                    ' onClick="setUnzer(); document.checkout_payment.cardlock.value = \''.$option.'\';event.stopImmediatePropagation();" '
                                )
                            );
                        }/** end qty=1 */
                    }

                    if($option != "creditcard"){
                        /** upload images to images/icons corresponding to your chosen cardlock groups in your payment module settings */
                        $selectedopts = explode(",", $option);
                        $icon = "";
                        foreach($selectedopts as $option){
                            $optscount++;

                            $icon = "";
                            if(file_exists(DIR_WS_ICONS.$option.".png")){
                              $icon = DIR_WS_ICONS.$option.".png";
                            }elseif(file_exists(DIR_WS_ICONS.$option.".jpg")){
                              $icon = DIR_WS_ICONS.$option.".jpg";
                            }elseif(file_exists(DIR_WS_ICONS.$option.".gif")){
                              $icon = DIR_WS_ICONS.$option.".gif";
                            }elseif(file_exists(DIR_WS_ICONS . $option . "_payment.png")){
                              $icon = DIR_WS_ICONS . $option . "_payment.png";
                            }elseif(file_exists(DIR_WS_ICONS . $option . "_payment.jpg")){
                              $icon = DIR_WS_ICONS . $option . "_payment.jpg";
                            }elseif(file_exists(DIR_WS_ICONS . $option . "_payment.gif")){
                              $icon = DIR_WS_ICONS . $option . "_payment.gif";
                            }
                            $space = 5;

                            //define payment icon width
                            if(strstr($icon, "_payment")){
                                $w = 120;
                                $h = 27;
                                if(strstr($icon, "3d")){
                                    $w = 60;
                                }
                            }else{
                                $w = 35;
                                $h = 22;
                            }

                            /** Configuring the text to be shown for the payment option. */
                            $options_text = '<table width="100%">
                                                <tr style="background-color: transparent !important;border-top: 0 !important;">
                                                    <td style="background-color: transparent !important;border-top: 0 !important;">'.tep_image($icon,$this->get_payment_options_name($option),$w,$h,' style="position:relative;border:0px;float:left;margin:'.$space.'px;" ').'</td>
                                                    <td style="height: 27px;white-space:nowrap;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;" >';

                            /** If there is an input in the text field for that payment option, that value will be shown to the user, otherwise, the default value will be used. */
                            if(defined('MODULE_PAYMENT_UNZER_ADVANCED_GROUP'.$i.'_TEXT') && constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i . '_TEXT') != ''){
                                $options_text .= constant('MODULE_PAYMENT_UNZER_ADVANCED_GROUP' . $i . '_TEXT').'</td></tr></table>';
                            }else {
                                $options_text .= $this->get_payment_options_name($option).'</td></tr></table>';
                            }


                            if($qty_groups==1){
                                $selection = array(
                                    'id' => $this->code,
                                    'module' => '<table width="100%" border="0">
                                                    <tr class="moduleRow table-selection" onclick="selectUnzerRowEffect(this, ' . ($optscount-1) . ',\''.$option.'\');event.stopImmediatePropagation();">
                                                        <td class="main" style="height: 27px;white-space:nowrap;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;">' .$options_text.($cost !=0 ? '</td><td style="height:22px;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;"> (+ '.MODULE_PAYMENT_UNZER_ADVANCED_FEELOCKINFO.')' :'').'
                                                            </td>
                                                    </tr>'.'
                                                </table>'.tep_draw_hidden_field('cardlock', $option).tep_draw_hidden_field('unzer_card', (isset($fees[1])) ? $fees[1] : '0'));
                            }else{
                                $selection['fields'][] = array(
                                    'title' => '<table width="100%" border="0">
                                                    <tr class="moduleRow table-selection" style="background-color: transparent !important;border-top: 0 !important;" onclick="selectUnzerRowEffect(this, ' . ($optscount-1) . ',\''.$option.'\');event.stopImmediatePropagation();">
                                                        <td class="main" style="height: 27px;white-space:nowrap;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;">' . $options_text.($cost !=0 ? '</td><td style="height:22px;vertical-align:middle;background-color: transparent !important;border-top: 0 !important;"> (+ '.MODULE_PAYMENT_UNZER_ADVANCED_FEELOCKINFO.')' :'').'
                                                            </td>
                                                    </tr>'.'
                                                </table>',
                                    'field' => tep_draw_radio_field(
                                        'unzer_card',
                                        '',
                                        ($option==$cardlock ? true : false),
                                        ' onClick="setUnzer();document.checkout_payment.cardlock.value = \''.$option.'\';event.stopImmediatePropagation()" '
                                    )
                                );
                            }
                        }
                    }
                }
            }
        }

        $js_function = '<script language="javascript"><!--
                            function setUnzer() {
                                var radioLength = document.checkout_payment.payment.length;
                                for(var i = 0; i < radioLength; i++) {
                                    document.checkout_payment.payment[i].checked = false;
                                    if(document.checkout_payment.payment[i].value == "unzer_advanced") {
                                        document.checkout_payment.payment[i].checked = true;
                                    }
                                }
                            }

                            function selectUnzerRowEffect(object, buttonSelect, option) {
                                if (typeof selected !== "undefined" && selected !== null) {
                                  if (!selected) {
                                      if (document.getElementById) {
                                          selected = document.getElementById("defaultSelected");
                                      } else {
                                          selected = document.all["defaultSelected"];
                                      }
                                  }
                                  if (selected) selected.className = "moduleRow";
                                }

                                object.className = "moduleRowSelected";
                                selected = object;
                                document.checkout_payment.cardlock.value = option;
                                document.checkout_payment.unzer_card.checked = false;

                                if (document.checkout_payment.unzer_card[0]) {
                                    document.checkout_payment.unzer_card[buttonSelect].checked=true;
                                } else {
                                    document.checkout_payment.unzer_card.checked=true;
                                }
                                setUnzer();
                            }

                        //--></script>';

        $selection['module'] .= $js_function;
        return $selection;
    }

    /* Before order is confirmed hook*/
    public function pre_confirmation_check() {
        global $cartID, $cart, $cardlock;

        if (!tep_session_is_registered('cardlock')) {
            tep_session_register('cardlock');
        }

        if (empty($cart->cartID)) {
            $cartID = $cart->cartID = $cart->generate_cart_id();
        }

        if (!tep_session_is_registered('cartID')) {
            tep_session_register('cartID');
        }

        $this->get_order_fee();
    }

    /* Order confirmation page hook*/
    public function confirmation($addorder=false) {
        global $order, $cart_Unzer_ID;
        $order_id = substr($cart_Unzer_ID, strpos($cart_Unzer_ID, '-') + 1);

        /** Do not create preparing order id before payment confirmation is chosen by customer */
        $mode = false;
        if(MODULE_PAYMENT_UNZER_ADVANCED_MODE == "Before" || (isset($_POST['callunzer']) && $_POST['callunzer'] == "go")){
            $mode = true;
        }

        if($mode && !$order_id) {
            if(!isset($order->customer['company']))$order->customer['company']='';
            if(!isset($order->billing['company']))$order->billing['company']='';
            if(!isset($order->delivery['company']))$order->delivery['company']='';
            if(!isset($order->customer['suburb']))$order->customer['suburb']='';
            if(!isset($order->billing['suburb']))$order->billing['suburb']='';
            if(!isset($order->delivery['suburb']))$order->delivery['suburb']='';

            require 'includes/system/segments/checkout/build_order_totals.php';
            require 'includes/system/segments/checkout/insert_order.php';

            $_SESSION['cart_Unzer_ID'] = $_SESSION['cartID'] . '-' . $order->get_id();
        }

        $fee_info = (MODULE_PAYMENT_UNZER_ADVANCED_AUTOFEE =="Yes" && $_POST["cardlock"] !="viabill" ? MODULE_PAYMENT_UNZER_ADVANCED_FEEINFO . '<br />' : '');

        return array('title' => $fee_info . $this->email_footer);
    }

    /* Define payment button and data array to be sent */
    public function process_button() {
        global $_POST, $customer_id, $order, $currencies, $languages_id, $language, $cart_Unzer_ID, $messageStack;

        /** Collect all post fields and attach as hiddenfieds to button */
        if ( !class_exists('unzer_currencies') ) {
            include(DIR_FS_CATALOG . DIR_WS_CLASSES . 'unzer_currencies.php');
        }
        if (!($currencies instanceof unzer_currencies)) {
            $currencies = new unzer_currencies($currencies);
        }

        $process_button_string = '';
        $process_parameters = null;

        $unzer_merchant_id = MODULE_PAYMENT_UNZER_ADVANCED_MERCHANTID;
        $unzer_agreement_id = MODULE_PAYMENT_UNZER_ADVANCED_AGGREEMENTID;

        /** TODO: dynamic language switching instead of hardcoded mapping */
        $unzer_language = "da";
        switch ($language) {
            case "english": $unzer_language = "en";
                break;
            case "swedish": $unzer_language = "se";
                break;
            case "norwegian": $unzer_language = "no";
                break;
            case "german": $unzer_language = "de";
                break;
            case "french": $unzer_language = "fr";
                break;
        }
        $unzer_branding_id = "";

        $unzer_subscription = (MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION == "Normal" ? "" : "1");
        $unzer_cardtypelock = $_POST['cardlock'];
        $unzer_autofee = (MODULE_PAYMENT_UNZER_ADVANCED_AUTOFEE == "No" || $unzer_cardtypelock == 'viabill' ? "0" : "1");
        $unzer_description = "Merchant ".$unzer_merchant_id." ".(MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION == "Normal" ? "Authorize" : "Subscription");
        $order_id = substr($cart_Unzer_ID, strpos($cart_Unzer_ID, '-') + 1);
        $unzer_order_id = MODULE_PAYMENT_UNZER_ADVANCED_ORDERPREFIX.sprintf('%04d', $order_id);
        /** Calculate the total order amount for the order (the same way as in checkout_process.php) */
        $unzer_order_amount = 100 * $currencies->calculate($order->info['total'], true, $order->info['currency'], $order->info['currency_value'], '.', '');
        $unzer_currency_code = $order->info['currency'];
        $unzer_continueurl = tep_href_link(FILENAME_CHECKOUT_PROCESS, 'cart_Unzer_ID='.$cart_Unzer_ID, 'SSL');
        $unzer_cancelurl = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code, 'SSL');
        $unzer_callbackurl = tep_href_link('callback10.php','oid='.$order_id,'SSL');
        $unzer_autocapture = (MODULE_PAYMENT_UNZER_ADVANCED_AUTOCAPTURE == "No" ? "0" : "1");
        $unzer_version ="v10";

        /** Define process_parameters START */
        $process_parameters = [
            'agreement_id' => $unzer_agreement_id,
            'amount' => $unzer_order_amount,
            'autocapture' => $unzer_autocapture,
            'autofee' => $unzer_autofee,
            'callbackurl' => $unzer_callbackurl,
            'cancelurl' => $unzer_cancelurl,
            'continueurl' => $unzer_continueurl,
            'currency' => $unzer_currency_code,
            'description' => $unzer_description,
            'language' => $unzer_language,
            'merchant_id' => $unzer_merchant_id,
            'order_id' => $unzer_order_id,
            'payment_methods' => $unzer_cardtypelock,
            'subscription' => $unzer_subscription,
            'version' => 'v10',

            'invoice_address' => [
                'name' => ((isset($order->billing['firstname'])) ? ($order->billing['firstname']) : ('')) . ((isset($order->billing['lastname'])) ? (' ' . $order->billing['lastname']) : ('')),
                'att' => '',
                'company_name' => (isset($order->billing['company'])) ? ($order->billing['company']) : (''),
                'street' => (isset($order->billing['street_address'])) ? ($order->billing['street_address']) : (''),
                'house_number' => '',
                'house_extension' => '',
                'city' => (isset($order->billing['city'])) ? ($order->billing['city']) : (''),
                'zip_code' => (isset($order->billing['postcode'])) ? ($order->billing['postcode']) : (''),
                'region' => (isset($order->billing['state'])) ? ($order->billing['state']): (''),
                'country_code' => UnzerISO3166::alpha3($order->billing['country']['title']),
                'vat_no' => '',
                'phone_number' => '',
                'mobile_number' => (isset($order->customer['telephone'])) ? ($order->customer['telephone']) : (''),
                'email' => (isset($order->customer['email_address'])) ? ($order->customer['email_address']) : ('')
            ],

            'shipping_address' => [
                'name' => ((isset($order->delivery['firstname'])) ? ($order->delivery['firstname']) : ('')) . ((isset($order->billing['lastname'])) ? (' ' . $order->billing['lastname']) : ('')),
                'att' => '',
                'company_name' => (isset($order->delivery['company'])) ? ($order->delivery['company']) : (''),
                'street' => (isset($order->delivery['street_address'])) ? ($order->delivery['street_address']) : (''),
                'house_number' => '',
                'house_extension' => '',
                'city' => (isset($order->delivery['city'])) ? ($order->delivery['city']) : (''),
                'zip_code' => (isset($order->delivery['postcode'])) ? ($order->delivery['postcode']) : (''),
                'region' => (isset($order->delivery['state'])) ? ($order->delivery['state']) : (''),
                'country_code' => UnzerISO3166::alpha3($order->delivery['country']['title']),
                'vat_no' => '',
                'phone_number' => '',
                'mobile_number' => (isset($order->customer['telephone'])) ? ($order->customer['telephone']) : (''),
                'email' => (isset($order->customer['email_address'])) ? ($order->customer['email_address']) : ('')
            ],

            'basket' => [],

            'shopsystem' => [
                'name' => "OsCommerce Phoenix",
                'version' => "1.0.2"
            ]
        ];

        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $process_parameters['basket'][] = [
                'qty' =>  $order->products[$i]['qty'],
                'item_no' =>  $order->products[$i]['id'],
                'item_name' =>  $order->products[$i]['name'],
                'item_price' =>  ($order->products[$i]['final_price'] * $order->products[$i]['qty']),
                'vat_rate' =>  ''
            ];
        }

        if(isset($_POST['callunzer']) && ("go" == $_POST['callunzer'])) {
            $apiorder= new UnzerApi();
            $apiorder->setOptions(MODULE_PAYMENT_UNZER_ADVANCED_USERAPIKEY);

            /** Set status request mode */
            $mode = (("Normal" == MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION) ? ("") : ("1"));

            /** Set to create/update mode */
            $apiorder->mode = (("Normal" == MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION) ? ("payments/") : ("subscriptions/"));

            /** Check if order exists. */
            $qid = null;
            $exists = $this->get_unzer_order_status($order_id, $mode);
            if (null == $exists["qid"]) {
                /** Create new unzer order */
                $storder = $apiorder->createorder($unzer_order_id, $unzer_currency_code, $process_parameters);
                $qid = $storder["id"];
            } else {
                $qid = $exists["qid"];
            }

            $storder = $apiorder->link($qid, $process_parameters);

            if (substr($storder['url'], 0, 5) <> 'https') {
                $messageStack->add_session(MODULE_PAYMENT_UNZER_ADVANCED_ERROR_COMMUNICATION_FAILURE, 'error');
                tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code, 'SSL'));
            }

            $process_button_string .= "<script>window.location.replace('".$storder['url']."');</script>";
        }

        $process_button_string .=  "<input type='hidden' value='go' name='callunzer' />". "\n".
                                   "<input type='hidden' value='" . $_POST['cardlock'] . "' name='cardlock' />";

        return $process_button_string;
    }

    /* Before order is processed */
    public function before_process() {
        /** Called in FILENAME_CHECKOUT_PROCESS */
        /** check if order is approved by callback */
        global $order, $cart_Unzer_ID;

        $order_id = substr($cart_Unzer_ID, strpos($cart_Unzer_ID, '-') + 1);
        $order->set_id($order_id);

        $order_status_approved_id = (MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID > 0 ? (int) MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID : (int) DEFAULT_ORDERS_STATUS_ID);

        $mode = (MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION == "Normal" ? "" : "1");
        $checkorderid = $this->get_unzer_order_status($order_id, $mode);
        if($checkorderid["oid"] != $order_id){
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code, 'SSL'));
        }

        if ( !class_exists('unzer_order') ) {
            include(DIR_FS_CATALOG . DIR_WS_CLASSES . 'unzer_order.php');
        }

        file_put_contents('unzer-api.log', 'eroare_unzera_1', FILE_APPEND);
        file_put_contents('unzer-api.log', print_r($order,TRUE), FILE_APPEND);
        file_put_contents('unzer-api.log', 'eroare_unzera_1_oid', FILE_APPEND);
        file_put_contents('unzer-api.log', $order_id, FILE_APPEND);

        if (!($order instanceof unzer_order)) {
            $order = new unzer_order($order);
        }

        file_put_contents('unzer-api.log', 'eroare_unzera_2', FILE_APPEND);
        file_put_contents('unzer-api.log', print_r($order,TRUE), FILE_APPEND);

        /** For debugging with FireBug / FirePHP */
        global $firephp;
        if (isset($firephp)) {
            $firephp->log($order_id, 'order_id');
        }

        /** Update order status */
        tep_db_query("update orders set orders_status = '" . (int)$order_status_approved_id . "', last_modified = now() where orders_id = '" . (int)$order_id . "'");
        $customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
        $sql_data = [
          'orders_id' => $order_id,
          'orders_status_id' => (int)$order_status_approved_id,
          'date_added' => 'now()',
          'customer_notified' => $customer_notification,
          'comments' => $order->info['comments'],
        ];
        tep_db_perform('orders_status_history', $sql_data);

        $GLOBALS['hooks']->register_pipeline('after');

        /** Load the after_process function from the payment modules */
        $this->after_process();
    }

    /* After order is processed */
    public function after_process() {
        tep_session_unregister('cardlock');
        tep_session_unregister('order_id');
        tep_session_unregister('unzer_fee');
        tep_session_unregister('unzer_card');
        tep_session_unregister('cart_Unzer_ID');
        tep_session_unregister('qlink');

        $GLOBALS['hooks']->register_pipeline('reset');
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        require 'includes/application_bottom.php';
    }

    public function get_error() {
        global $cart_Unzer_ID, $order, $currencies;
        $order_id = substr($cart_Unzer_ID, strpos($cart_Unzer_ID, '-') + 1);;

        if ( !class_exists('unzer_currencies') ) {
            include(DIR_FS_CATALOG . DIR_WS_CLASSES . 'unzer_currencies.php');
        }
        if (!($currencies instanceof unzer_currencies)) {
            $currencies = new unzer_currencies($currencies);
        }

        $error_desc = MODULE_PAYMENT_UNZER_ADVANCED_ERROR_CANCELLED;
        $error = array('title' => MODULE_PAYMENT_UNZER_ADVANCED_TEXT_ERROR, 'error' => $error_desc);

        return $error;
    }

    public function output_error() {
        return false;
    }

    /* Define module admin fields and statuses */
    protected function get_parameters() {
        $cc_query = tep_db_query("describe orders cc_transactionid");
        if (tep_db_num_rows($cc_query) == 0) {
            tep_db_query("ALTER TABLE orders ADD cc_transactionid VARCHAR( 64 ) NULL default NULL");
        }

        $cc_query = tep_db_query("describe orders cc_cardhash");
        if (tep_db_num_rows($cc_query) == 0) {
            tep_db_query("ALTER TABLE orders ADD cc_cardhash VARCHAR( 64 ) NULL default NULL");
        }

        $cc_query = tep_db_query("describe orders cc_cardtype");
        if (tep_db_num_rows($cc_query) == 0) {
            tep_db_query("ALTER TABLE orders ADD cc_cardtype VARCHAR( 64 ) NULL default NULL");
        }

        tep_db_query("ALTER TABLE orders CHANGE cc_expires  cc_expires VARCHAR( 8 )  NULL DEFAULT NULL");

        $fields = array(
            'MODULE_PAYMENT_UNZER_ADVANCED_STATUS' => [
                'title' => 'Enable unzer_advanced',
                'desc' => 'Do you want to accept unzer payments?',
                'value' => 'False',
                'set_func' => "tep_cfg_select_option(['True', 'False'], ",
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_ZONE' => [
                'title' => 'Payment Zone',
                'value' => '0',
                'desc' => 'If a zone is selected, only enable this payment method for that zone.',
                'use_func' => 'tep_get_zone_class_title',
                'set_func' => 'tep_cfg_pull_down_zone_classes(',
            ],

            'MODULE_PAYMENT_MONEYORDER_SORT_ORDER' => [
                'title' => 'Sort order of display.',
                'value' => '0',
                'desc' => 'Sort order of display. Lowest is displayed first.',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_MERCHANTID' => [
                'title' => 'Unzer Merchant Id',
                'desc' => 'Enter Merchant id',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_AGGREEMENTID' => [
                'title' => 'Unzer Window user Agreement Id',
                'desc' => 'Enter Window user Agreement id',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_USERAPIKEY' => [
                'title' => 'API USER KEY',
                'desc' => 'Used for payments, and for handling transactions from your backend order page.',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_ORDERPREFIX' => [
                'title' => 'Order number prefix',
                'value' => '000',
                'desc' => 'Enter prefix (Ordernumbers Must contain at least 3 characters)<br>Please Note: if upgrading from previous versions of Unzer 10, use format \"Window Agreement ID_\" ex. 1234_ if \"old\" orders statuses  are to be displayed in your order admin.<br>',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID' => [
                'title' => 'Set Preparing Order Status',
                'value' => self::ensure_order_status('MODULE_PAYMENT_UNZER_ADVANCED_ORDER_PREPARE_STATUS_ID', 'Unzer [preparing]'),
                'desc' => 'Set the status of prepared orders made with this payment module to this value',
                'set_func' => 'tep_cfg_pull_down_order_statuses(',
                'use_func' => 'tep_get_order_status_name',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID' => [
                'title' => 'Set Unzer Acknowledged Order Status',
                'value' => self::ensure_order_status('MODULE_PAYMENT_UNZER_ADVANCED_ORDER_APPROVED_STATUS_ID', 'Unzer [approved]'),
                'desc' => 'Set the status of orders made with this payment module to this value',
                'set_func' => 'tep_cfg_pull_down_order_statuses(',
                'use_func' => 'tep_get_order_status_name',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_REJECTED_ORDER_STATUS_ID' => [
                'title' => 'Set Unzer Rejected Order Status',
                'value' => self::ensure_order_status('MODULE_PAYMENT_UNZER_ADVANCED_ORDER_REJECTED_STATUS_ID', 'Unzer [rejected]'),
                'desc' => 'Set the status of rejected orders made with this payment module to this value',
                'set_func' => 'tep_cfg_pull_down_order_statuses(',
                'use_func' => 'tep_get_order_status_name',
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION' => [
                'title' => 'Subscription payment',
                'desc' => 'Set Subscription payment as default (normal is single payment).',
                'value' => 'Normal',
                'set_func' => "tep_cfg_select_option(['Normal', 'Subscription'], ",
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_AUTOFEE' => [
                'title' => 'Autofee',
                'desc' => 'Does customer pay the cardfee?<br>Set fees in <a href=\"https://insights.unzerdirect.com/\" target=\"_blank\"><u>Unzer manager</u></a>',
                'value' => 'No',
                'set_func' => "tep_cfg_select_option(['Yes', 'No'], ",
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_AUTOCAPTURE' => [
                'title' => 'Autocapture',
                'desc' => 'Use autocapture?',
                'value' => 'No',
                'set_func' => "tep_cfg_select_option(['Yes', 'No'], ",
            ],

            'MODULE_PAYMENT_UNZER_ADVANCED_MODE' => [
                'title' => 'Preparing orders mode',
                'desc' => 'Choose mode:<br><b>Normal:</b> Create when payment window is opened.<br><b>Before:</b> Create when confirmation page is opened',
                'value' => 'Normal',
                'set_func' => "tep_cfg_select_option(['Normal', 'Before'], ",
            ],

            'MODULE_PAYMENT_UNZER_CARD_LOGOS' => [
                'title' => 'Credit Card Logos',
                'value' => implode(";",MODULE_AVAILABLE_CREDITCARDS),
                'desc' => 'Images related to Credit Card Payment Method. Drag & Drop to change the visibility/order',
                'set_func' => 'edit_logos(',
                'use_func' => 'show_logos',
            ]
        );

        /* Set statuses public_flag to 1 in order to be shown on front store */
        tep_db_query("update orders_status set public_flag = 1 and downloads_flag = 0 where orders_status_id = '" . ((defined('MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID > 0)) ? ((int)MODULE_PAYMENT_UNZER_ADVANCED_ORDER_STATUS_ID) : (0)) . "'");
        tep_db_query("update orders_status set public_flag = 1 and downloads_flag = 0 where orders_status_id = '" . ((defined('MODULE_PAYMENT_UNZER_ADVANCED_REJECTED_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_UNZER_ADVANCED_REJECTED_ORDER_STATUS_ID > 0)) ? ((int)MODULE_PAYMENT_UNZER_ADVANCED_REJECTED_ORDER_STATUS_ID) : (0)) . "'");
        tep_db_query("update orders_status set public_flag = 1 and downloads_flag = 0 where orders_status_id = '" . ((defined('MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID > 0)) ? ((int)MODULE_PAYMENT_UNZER_ADVANCED_PREPARE_ORDER_STATUS_ID) : (0)) . "'");

        for ($i = 1; $i <= $this->num_groups; $i++) {
            if ($i==1) {
                $defaultlock='viabill';
            } else if ($i==2) {
                $defaultlock='creditcard';
            } else {
                $defaultlock='';
            }

            $unzer_group = (defined('MODULE_PAYMENT_UNZER_GROUP' . $i)) ? constant('MODULE_PAYMENT_UNZER_GROUP' . $i) : $defaultlock;

            $group_field = array('MODULE_PAYMENT_UNZER_ADVANCED_GROUP'.$i => [
                'title' => 'Group '.$i.' Payment Options',
                'value' => $unzer_group,
                'desc' => 'Comma seperated Unzer payment options that are included in Group '.$i.', maximum 255 chars (<a href=\'http://unzerdirect.com/documentation/appendixes/payment-methods\' target=\'_blank\'><u>available options</u></a>)<br>Example: creditcard OR viabill OR dankort<br>',
            ]);

            //Added a text field key for each payment group
            $text_field = array('MODULE_PAYMENT_UNZER_ADVANCED_GROUP'.$i.'_TEXT' => [
                'title' => 'Group '.$i.' Payment Text',
                'value' => '',
                'desc' => 'Define text to be displayed for Group ' . $i . ' Payment Option. If this is not defined, the default text will be shown.<br>',
            ]);

            $fields = array_merge($fields, $group_field);

            $fields = array_merge($fields, $text_field);
        }

        return $fields;
    }

    /** Internal help functions */
    /** $order_total parameter must be total amount for current order including tax */
    /** Format of $fee parameter: "[fixed fee]:[percentage fee]" */
    protected function calculate_order_fee($order_total, $fee) {
        list($fixed_fee, $percent_fee) = explode(':', $fee);

        return ((float) $fixed_fee + (float) $order_total * ($percent_fee / 100));
    }

    protected function get_order_fee() {
        global $_POST, $order, $currencies, $unzer_fee;
        $unzer_fee = 0.0;
        if (isset($_POST['unzer_card']) && strpos($_POST['unzer_card'], ":")) {
            $unzer_fee = $this->calculate_order_fee($order->info['total'], $_POST['unzer_card']);
        }
    }

    protected function get_payment_options_name($payment_option) {
        switch ($payment_option) {
            case 'creditcard': return MODULE_PAYMENT_UNZER_ADVANCED_CREDITCARD_TEXT;

            case '3d-dankort': return MODULE_PAYMENT_UNZER_ADVANCED_DANKORT_3D_TEXT;
            case '3d-jcb': return MODULE_PAYMENT_UNZER_ADVANCED_JCB_3D_TEXT;
            case '3d-visa': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_3D_TEXT;
            case '3d-visa-dk': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_DK_3D_TEXT;
            case '3d-visa-electron': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_ELECTRON_3D_TEXT;
            case '3d-visa-electron-dk': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_ELECTRON_DK_3D_TEXT;
            case '3d-visa-debet': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_DEBET_3D_TEXT;
            case '3d-visa-debet-dk': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_DEBET_DK_3D_TEXT;
            case '3d-maestro': return MODULE_PAYMENT_UNZER_ADVANCED_MAESTRO_3D_TEXT;
            case '3d-maestro-dk': return MODULE_PAYMENT_UNZER_ADVANCED_MAESTRO_DK_3D_TEXT;
            case '3d-mastercard': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_3D_TEXT;
            case '3d-mastercard-dk': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_DK_3D_TEXT;
            case '3d-mastercard-debet': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_DEBET_3D_TEXT;
            case '3d-mastercard-debet-dk': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_DEBET_DK_3D_TEXT;
            case '3d-creditcard': return MODULE_PAYMENT_UNZER_ADVANCED_CREDITCARD_3D_TEXT;
            case 'mastercard': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_TEXT;
            case 'mastercard-dk': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_DK_TEXT;
            case 'mastercard-debet': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_DEBET_TEXT;
            case 'mastercard-debet-dk': return MODULE_PAYMENT_UNZER_ADVANCED_MASTERCARD_DEBET_DK_TEXT;
            case 'american-express': return MODULE_PAYMENT_UNZER_ADVANCED_AMERICAN_EXPRESS_TEXT;
            case 'american-express-dk': return MODULE_PAYMENT_UNZER_ADVANCED_AMERICAN_EXPRESS_DK_TEXT;
            case 'dankort': return MODULE_PAYMENT_UNZER_ADVANCED_DANKORT_TEXT;
            case 'diners': return MODULE_PAYMENT_UNZER_ADVANCED_DINERS_TEXT;
            case 'diners-dk': return MODULE_PAYMENT_UNZER_ADVANCED_DINERS_DK_TEXT;
            case 'jcb': return MODULE_PAYMENT_UNZER_ADVANCED_JCB_TEXT;
            case 'visa': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_TEXT;
            case 'visa-dk': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_DK_TEXT;
            case 'visa-electron': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_ELECTRON_TEXT;
            case 'visa-electron-dk': return MODULE_PAYMENT_UNZER_ADVANCED_VISA_ELECTRON_DK_TEXT;
            case 'viabill': return MODULE_PAYMENT_UNZER_ADVANCED_VIABILL_TEXT;
            case 'fbg1886': return MODULE_PAYMENT_UNZER_ADVANCED_FBG1886_TEXT;
            case 'paypal': return MODULE_PAYMENT_UNZER_ADVANCED_PAYPAL_TEXT;
            case 'sofort': return MODULE_PAYMENT_UNZER_ADVANCED_SOFORT_TEXT;
            case 'mobilepay': return MODULE_PAYMENT_UNZER_ADVANCED_MOBILEPAY_TEXT;
            case 'bitcoin': return MODULE_PAYMENT_UNZER_ADVANCED_BITCOIN_TEXT;
            case 'swish': return MODULE_PAYMENT_UNZER_ADVANCED_SWISH_TEXT;
            case 'trustly': return MODULE_PAYMENT_UNZER_ADVANCED_TRUSTLY_TEXT;
            case 'klarna': return MODULE_PAYMENT_UNZER_ADVANCED_KLARNA_TEXT;

            case 'maestro': return MODULE_PAYMENT_UNZER_ADVANCED_MAESTRO_TEXT;
            case 'ideal': return MODULE_PAYMENT_UNZER_ADVANCED_IDEAL_TEXT;
            case 'paysafecard': return MODULE_PAYMENT_UNZER_ADVANCED_PAYSAFECARD_TEXT;
            case 'resurs': return MODULE_PAYMENT_UNZER_ADVANCED_RESURS_TEXT;
            case 'vipps': return MODULE_PAYMENT_UNZER_ADVANCED_VIPPS_TEXT;

            // case 'danske-dk': return MODULE_PAYMENT_UNZER_ADVANCED_DANSKE_DK_TEXT;
            // case 'edankort': return MODULE_PAYMENT_UNZER_ADVANCED_EDANKORT_TEXT;
            // case 'nordea-dk': return MODULE_PAYMENT_UNZER_ADVANCED_NORDEA_DK_TEXT;
            // case 'viabill':  return MODULE_PAYMENT_UNZER_ADVANCED_viabill_DESCRIPTION;
            // case 'paii': return MODULE_PAYMENT_UNZER_ADVANCED_PAII_TEXT;
        }
        return '';
    }


    protected function sign($params, $api_key) {
        ksort($params);
        $base = implode(" ", $params);

        return hash_hmac("sha256", $base, $api_key);
    }


    private function get_unzer_order_status($order_id,$mode="") {
        $api= new UnzerApi();

        $api->setOptions(MODULE_PAYMENT_UNZER_ADVANCED_USERAPIKEY);

        try {
            $api->mode = ($mode=="" ? "payments?order_id=" : "subscriptions?order_id=");

            // Commit the status request, checking valid transaction id
            $st = $api->status(MODULE_PAYMENT_UNZER_ADVANCED_ORDERPREFIX.sprintf('%04d', $order_id));
            $eval = array();
            if(isset($st[0]) && $st[0]["id"]){
                $eval["oid"] = str_replace(MODULE_PAYMENT_UNZER_ADVANCED_ORDERPREFIX,"", $st[0]["order_id"]);
                $eval["qid"] = $st[0]["id"];
            }else{
                $eval["oid"] = null;
                $eval["qid"] = null;
            }

        } catch (Exception $e) {
            $eval = 'Unzer Status: ';
            // An error occured with the status request
            $eval .= 'Problem: ' . $this->json_message_front($e->getMessage()) ;
            //  tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code, 'SSL'));
        }

        return $eval;
    }


    private function json_message_front($input){

        $dec = json_decode($input,true);

        $message= $dec["message"];

        return $message;
    }
}

/** Display logos in the admin panel in view state */
function show_logos($text) {
    $w = 55;
    $h = 'auto';
    $output = '';

    if ( !empty($text) ) {
        $output = '<ul style="list-style-type: none; margin: 0; padding: 5px; margin-bottom: 10px;">';

        $options = explode(';', $text);
        foreach ($options as $optionc) {
            $iconc = "";
            if(file_exists(DIR_FS_CATALOG . DIR_WS_ICONS . $optionc . ".png")){
                $iconc = DIR_WS_CATALOG_IMAGES . 'icons/'.$optionc.".png";
            }elseif(file_exists(DIR_FS_CATALOG . DIR_WS_IMAGES . 'icons/'.$optionc.".jpg")){
                $iconc = DIR_WS_CATALOG_IMAGES . 'icons/'.$optionc.".jpg";
            }elseif(file_exists(DIR_FS_CATALOG . DIR_WS_IMAGES . 'icons/'.$optionc.".gif")){
                $iconc = DIR_WS_CATALOG_IMAGES . 'icons/'.$optionc.".gif";
            }

            if(strlen($iconc))
                $output .= '<li style="padding: 2px;">' . tep_image($iconc, $optionc , $w, $h) . '</li>';
          }
          $output .= '</ul>';
    }
    return $output;
}

/** Display logos in the admin panel in edit state */
function edit_logos($values, $key) {
    $w = 55;
    $h = 'auto';
    /** Scan images directory for logos */
    $files_array = array();
    if ( $dir = @dir(DIR_FS_CATALOG . DIR_WS_ICONS) ) {
        while ( $file = $dir->read() ) {
            /** Check if image is valid */
            if ( !is_dir(DIR_FS_CATALOG . DIR_WS_ICONS . $file ) && in_array(explode('.',$file)[0],MODULE_AVAILABLE_CREDITCARDS)) {
                if (in_array(substr($file, strrpos($file, '.')+1), array('gif', 'jpg', 'png')) ) {
                    $files_array[] = $file;
                }
            }
        }
        sort($files_array);
        $dir->close();
    }

    /** Display logos to be shown */
    $values_array = !empty($values) ? explode(';', $values) : array();
    $output = '<h3>' . MODULE_PAYMENT_UNZER_CARD_LOGOS_SHOWN_CARDS . '</h3>' .
              '<ul id="ca_logos" style="list-style-type: none; margin: 0; padding: 5px; margin-bottom: 10px;">';

    foreach ($values_array as $optionc) {
        $iconc = "";
        if(file_exists(DIR_FS_CATALOG . DIR_WS_ICONS . $optionc.".png")){
            $iconc = DIR_WS_CATALOG_IMAGES . 'icons/'.$optionc.".png";
        }elseif(file_exists(DIR_FS_CATALOG . DIR_WS_ICONS . $optionc.".jpg")){
            $iconc = DIR_WS_CATALOG_IMAGES . 'icons/'.$optionc.".jpg";
        }elseif(file_exists(DIR_FS_CATALOG . DIR_WS_ICONS . $optionc.".gif")){
            $iconc = DIR_WS_CATALOG_IMAGES . 'icons/'.$optionc.".gif";
        }

        if(strlen($iconc))
            $output .= '<li style="padding: 2px;">' . tep_image($iconc, $optionc, $w, $h) . tep_draw_hidden_field('bm_card_acceptance_logos[]', $optionc) . '</li>';
    }

    $output .= '</ul>';

    /** Display available logos */
    $output .= '<h3>' . MODULE_PAYMENT_UNZER_CARD_LOGOS_NEW_CARDS . '</h3><ul id="new_ca_logos" style="list-style-type: none; margin: 0; padding: 5px; margin-bottom: 10px;">';
    foreach ($files_array as $file) {
        /** Check if logo is not already displayed in "Available list" */
        if ( !in_array(explode(".",$file)[0], $values_array) ) {
            $output .= '<li style="padding: 2px;">' . tep_image(DIR_WS_CATALOG_IMAGES . 'icons/' . $file, explode(".",$file)[0], $w, $h) . tep_draw_hidden_field('bm_card_acceptance_logos[]', explode(".",$file)[0]) . '</li>';
        }
    }

    $output .= '</ul>';

    $output .= tep_draw_hidden_field('configuration[' . $key . ']', '', 'id="ca_logo_cards"');

    $drag_here_li = '<li id="caLogoEmpty" style="background-color: #fcf8e3; border: 1px #faedd0 solid; color: #a67d57; padding: 5px;">' . addslashes(MODULE_PAYMENT_UNZER_CARD_LOGOS_DRAG_HERE) . '</li>';

    /** Drag and Drop logic */
    $output .= <<<EOD
              <script>
                  $(function() {
                      var drag_here_li = '{$drag_here_li}';
                      if ( $('#ca_logos li').length < 1 ) {
                          $('#ca_logos').append(drag_here_li);
                      }

                      $('#ca_logos').sortable({
                          connectWith: '#new_ca_logos',
                          items: 'li:not("#caLogoEmpty")',
                          stop: function (event, ui) {
                              if ( $('#ca_logos li').length < 1 ) {
                                  $('#ca_logos').append(drag_here_li);
                              } else if ( $('#caLogoEmpty').length > 0 ) {
                                  $('#caLogoEmpty').remove();
                              }
                          }
                      });

                      $('#new_ca_logos').sortable({
                          connectWith: '#ca_logos',
                          stop: function (event, ui) {
                              if ( $('#ca_logos li').length < 1 ) {
                                  $('#ca_logos').append(drag_here_li);
                              } else if ( $('#caLogoEmpty').length > 0 ) {
                                  $('#caLogoEmpty').remove();
                              }
                          }
                      });

                      $('#ca_logos, #new_ca_logos').disableSelection();

                      $('form[name="modules"]').submit(function(event) {
                          var ca_selected_cards = '';

                          if ( $('#ca_logos li').length > 0 ) {
                              $('#ca_logos li input[name="bm_card_acceptance_logos[]"]').each(function() {
                                  ca_selected_cards += $(this).attr('value') + ';';
                              });
                          }

                        if (ca_selected_cards.length > 0) {
                            ca_selected_cards = ca_selected_cards.substring(0, ca_selected_cards.length - 1);
                        }

                        $('#ca_logo_cards').val(ca_selected_cards);
                      });
                  });
              </script>
EOD;
    return $output;
}
?>
