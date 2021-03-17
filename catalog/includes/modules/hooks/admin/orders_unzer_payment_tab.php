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

if (!defined('DIR_FS_CATALOG_LANGUAGES')) define('DIR_FS_CATALOG_LANGUAGES', DIR_FS_CATALOG . 'includes/languages/');

class hook_admin_orders_unzer_payment_tab {

    function load_language() {
        global $language;
        include_once(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/hooks/admin/' . basename(__FILE__));
    }

    function execute() {
        global $oID, $languages_id, $order, $api, $base_url;
        $this->load_language();

        $output = '';

        if ( !class_exists('unzer_order') ) {
            include(DIR_WS_CLASSES . 'unzer_order.php');
        }
        if (!($order instanceof unzer_order)) {
            $order = new unzer_order($order);
        }
        // echo ('<pre>'.print_r($order,true).'</pre>');
        if (array_key_exists('cc_transactionid',$order->info) && !is_null($order->info['cc_transactionid']) && $order->info['cc_transactionid'] <> 'NULL') {
            // code moved from orders_gui_admin.php

            // Only show unzer transaction when we have an transacctionid from payment gateway
            // Also check if we can access Unzer API (only when Curl extentions are loaded)
            $subcription = false;

            if (strstr($order->info['cc_cardhash'],"Subscription")) {
                $subcription = true;
            }

            if ($api->init()) {

                try {
                    $api->mode = (MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION == "Normal" ? "payments?order_id=" : "subscriptions?order_id=");
                    $statusinfo = $api->status(MODULE_PAYMENT_UNZER_ADVANCED_ORDERPREFIX.sprintf('%04d', $_GET["oID"]));
                    $ostatus['amount'] = $statusinfo[0]["operations"][0]["amount"];
                    $ostatus['balance'] = $statusinfo[0]["balance"];
                    $ostatus['currency'] = $statusinfo[0]["currency"];
                    //get the latest operation
                    $operations = array_reverse($statusinfo[0]["operations"]);
                    $amount = $operations[0]["amount"];
                    $ostatus['unzerstat'] = $operations[0]["unzer_status_code"];
                    $ostatus['type'] = $operations[0]["type"];
                    $resttocap = $ostatus['amount'] - $ostatus['balance'];
                    $resttorefund = $statusinfo[0]["balance"];
                    $allowcapture = ($operations[0]["pending"] ? false : true);
                    $allowcancel = true;
                    $testmode = $statusinfo[0]["test_mode"];
                    $type = $statusinfo[0]["type"];
                    $id = $statusinfo[0]["id"];

                    //reset mode
                    $api->mode = (MODULE_PAYMENT_UNZER_ADVANCED_SUBSCRIPTION == "Normal" ? "payments/" : "subscriptions/");
                    $process_parameters["amount"] = $statusinfo[0]["link"]["amount"];
                    $process_parameters["callbackurl"] = HTTP_SERVER.DIR_WS_CATALOG."callback10.php?oid=".$oID;
                    $process_parameters["continueurl"] = HTTP_SERVER.DIR_WS_CATALOG."checkout_process.php?paymentlink=".$oID;
                    $process_parameters["cancelurl"] =   HTTP_SERVER.DIR_WS_CATALOG;
                    $process_parameters["reference_title"] = "admin link";
                    $process_parameters["language"] = $statusinfo[0]["link"]["language"];
                    $process_parameters["vat_amount"] = $process_parameters["amount"]*0.25;
                    $process_parameters["customer_email"] = $order->customer["email_address"];
                    $process_parameters["currency"] = $ostatus['currency'];

                    $storder = $api->link($id, $process_parameters);
                    $plink = $storder["url"];
                    //allow split payments and split refunds
                    if(($ostatus['type'] == "capture" ) ){
                        $allowcancel = false;
                    }
                    if(($ostatus['type'] == "refund" ) ){
                        $resttocap = 0;
                    }
                    $ostatus['time'] = $operations[0]["created_at"];
                    $ostatus['unzerstatmsg'] = $operations[0]["unzer_status_msg"];
                } catch (Exception $e) {
                    $error = $e->getCode(); // The code is the http status code
                    $error .= $e->getMessage(); // The message is json
                }

                $heading = ($ostatus['unzerstat'] == 20000 && $api->mode == "subscriptions/" && !isset($error) ? ' <h3>'.SUBSCRIPTION_ADMIN.'</h3>'."\n " : '');
                $form = '';

                if ($ostatus['unzerstat'] == 20000 && $api->mode == "payments/") {
                    $statustext = array();
                    $statustext["capture"] = INFO_UNZER_CAPTURED;
                    $statustext["cancel"] = INFO_UNZER_REVERSED;
                    $statustext["refund"] = INFO_UNZER_CREDITED;
                    $formatamount= explode(',',number_format($amount/100,2,',',''));
                    $amount_big = $formatamount[0];
                    $amount_small = $formatamount[1];

                    $unzer_status = '';
                    switch ($ostatus['type']) {
                        case 'authorize': // Authorized
                        case 'renew': //-not implemented in this version
                            $unzer_status = UNZER_AUTHORISED;

                            $form .= tep_draw_form('transaction_form', FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'tabaction=unzer_capture');
                            $form .= tep_draw_hidden_field('oID', $oID) . tep_draw_hidden_field('currency', $ostatus['currency']);

                            $form .= tep_draw_input_field('amount_big', $amount_big, 'size="11" style="text-align:right" ', false, 'text', false);
                            $form .= ' , ';
                            $form .= tep_draw_input_field('amount_small', $amount_small, 'size="3" ', false, 'text', false) . ' ' . $ostatus['currency'] . ' ';

                            if ($allowcapture) {
                                $form .= '<a href="javascript:if (unzer_check_capture(' . str_replace('.','',$amount_big) . ', ' . $amount_small . ')) document.transaction_form.submit();">' . tep_image(DIR_WS_IMAGES . 'icon_transaction_capture.gif', IMAGE_TRANSACTION_CAPTURE_INFO) . '</a>';
                            } else {
                                $form .= PENDING_STATUS;
                            }
                            $form .= '</form>';
                            $form .= tep_draw_form('transaction_decline_form', FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'tabaction=unzer_reverse', 'post');
                            if ($allowcancel) {
                                $form .= '<a href="javascript:if (unzer_check_confirm(\'' . CONFIRM_REVERSE . '\')) document.transaction_decline_form.submit();">' . tep_image(DIR_WS_IMAGES . 'icon_transaction_reverse.gif', IMAGE_TRANSACTION_REVERSE_INFO) . '</a>';
                            }
                            $form .= '</form>';
                            $sevendayspast = date('Y-m-d', time() - (7 * 24 * 60 * 60));
                            if ($sevendayspast == substr($ostatus['time'], 0, 10)) {
                                $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_yellow.gif', IMAGE_TRANSACTION_TIME_INFO_YELLOW);
                            } elseif (strcmp($sevendayspast, substr($ostatus['time'], 0, 10)) > 0) {
                                $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_red.gif', IMAGE_TRANSACTION_TIME_INFO_RED);
                            } else {
                                $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_green.gif', IMAGE_TRANSACTION_TIME_INFO_GREEN);
                            }
                            break;

                        case 'capture': // Captured or refunded
                            $unzer_status = UNZER_CAPTURED;
                        case 'refund':
                            if (!strlen($unzer_status)) $unzer_status = UNZER_REFUNDED;

                            if ($resttocap > 0 ) {
                                $form .= "<br><b>".IMAGE_TRANSACTION_CAPTURE_INFO."</b><br>\n";
                                $formatamount = explode(',',number_format($resttocap/100,2,',',''));
                                $amount_big = $formatamount[0];
                                $amount_small = $formatamount[1];

                                $form .= tep_draw_form('transaction_form', FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'tabaction=unzer_capture');
                                $form .= tep_draw_hidden_field('oID', $oID) . tep_draw_hidden_field('currency', $ostatus['currency']);
                                $form .= tep_draw_input_field('amount_big', $amount_big, 'size="11" style="text-align:right" ', false, 'text', false);
                                $form .= ' , ';
                                $form .= tep_draw_input_field('amount_small', $amount_small, 'size="3" ', false, 'text', false) . ' ' . $ostatus['currency'] . ' ';
                                $form .= '<a href="javascript:if (unzer_check_capture(' . str_replace('.','',$amount_big) . ', ' . $amount_small . ')) document.transaction_form.submit();">' . tep_image(DIR_WS_IMAGES . 'icon_transaction_capture.gif', IMAGE_TRANSACTION_CAPTURE_INFO) . '</a>';
                                $form .= '</form>'."\n";
                                $form .= tep_draw_form('transaction_decline_form', FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'action=unzer_reverse', 'post');
                                if ($allowcancel) {
                                    $form .= '<a href="javascript:if (unzer_check_confirm(\'' . CONFIRM_REVERSE . '\')) document.transaction_decline_form.submit();">' . tep_image(DIR_WS_IMAGES . 'icon_transaction_reverse.gif', IMAGE_TRANSACTION_REVERSE_INFO) . '</a>';
                                } else {
                                    $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_reverse_grey.gif', IMAGE_TRANSACTION_REVERSE_INFO);
                                }
                                $form .= '</form>';
                                $sevendayspast = date('Y-m-d', time() - (7 * 24 * 60 * 60));
                                if ($sevendayspast == substr($ostatus['time'], 0, 10)) {
                                    $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_yellow.gif', IMAGE_TRANSACTION_TIME_INFO_YELLOW);
                                } elseif (strcmp($sevendayspast, substr($ostatus['time'], 0, 10)) > 0) {
                                    $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_red.gif', IMAGE_TRANSACTION_TIME_INFO_RED);
                                } else {
                                    $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_green.gif', IMAGE_TRANSACTION_TIME_INFO_GREEN);
                                }
                                $form .= "<br><br>\n";
                            }

                            $formatamount = explode(',',number_format($resttorefund/100,2,',',''));
                            $amount_big = $formatamount[0];
                            $amount_small = $formatamount[1];
                            if ($resttorefund > 0) {
                                $form .= "<b>".IMAGE_TRANSACTION_CREDIT_INFO."</b><br>\n";
                                $form .= tep_draw_form('transaction_refundform', FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'tabaction=unzer_credit');
                                $form .= tep_draw_hidden_field('oID', $oID) . tep_draw_hidden_field('currency', $ostatus['currency']);
                                $form .= tep_draw_input_field('amount_big', str_replace('.','',$amount_big), 'size="11" style="text-align:right" ', false, 'text', false);
                                $form .= ' , ';
                                $form .= tep_draw_input_field('amount_small', $amount_small, 'size="3" ', false, 'text', false) . ' ' . $ostatus['currency'] . ' ';
                                $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_capture_gr    ey.gif', IMAGE_TRANSACTION_CAPTURE_INFO);
                                $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_reverse_grey.gif', IMAGE_TRANSACTION_REVERSE_INFO);
                                $form .= '<a href="javascript:if (unzer_check_confirm(\'' . CONFIRM_CREDIT . '\')) document.transaction_refundform.submit();">' . tep_image(DIR_WS_IMAGES . 'icon_transaction_credit.gif', IMAGE_TRANSACTION_CREDIT_INFO) . '</a>';
                                $form .= '</form>'."\n";
                                $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_grey.gif', '');
                            } else {
                                $form .= tep_draw_input_field('amount_big', str_replace('.','',$amount_big), 'size="11" style="text-align:right" disabled', false, 'text', false);
                                $form .= ' , ';
                                $form .= tep_draw_input_field('amount_small', $amount_small, 'size="3" disabled', false, 'text', false) . ' ' . $ostatus['currency'] . ' ';
                                $form .= ' (' . $statustext[$ostatus['type']].')';
                            }
                            break;
                        case 'cancel': // Reversed
                            $unzer_status = UNZER_REVERSED;
                            $form .= tep_draw_input_field('amount_big', str_replace('.','',$amount_big), 'size="11" style="text-align:right" disabled', false, 'text', false);
                            $form .= ' , ';
                            $form .= tep_draw_input_field('amount_small', $amount_small, 'size="3" disabled', false, 'text', false) . ' ' . $ostatus['currency'] . ' ';
                            $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_capture_grey.gif', IMAGE_TRANSACTION_CAPTURE_INFO);
                            $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_reverse_grey.gif', IMAGE_TRANSACTION_REVERSE_INFO);
                            $form .= tep_image(DIR_WS_IMAGES . 'icon_transaction_time_grey.gif', '');
                            $form .= ' (' . $statustext[$ostatus['type']] .')';
                            break;
                        default:
                            $unzer_status = sprintf(UNZER_ERROR,$ostatus['unzerstatmsg']);
                            $form .= '<font color="red">' . $ostatus['unzerstatmsg'] . '</font>';
                            break;
                    }
                } else {
                    $unzer_status = sprintf(UNZER_ERROR,$ostatus['unzerstatmsg']);
                    $form .= '<font color="red">' . $ostatus['unzerstatmsg'] . '</font>';
                }

                $errormsg = (isset($error) ? '<font color="red">' . $error . '</font><br>'."\n" : '');

                $table = '<table>'."\n";
                $table .= '<tr><td>'.ENTRY_UNZER_TRANSACTION_ID.'</td><td>'.$id.($testmode== true ? '<font color="red"> TEST MODE</font>' : '')."</td></tr>\n";
                $table .= '<tr><td>'.ENTRY_UNZER_TRANSACTION_TYPE.'</td><td>'.$statusinfo[0]["type"]." (".$statusinfo[0]["metadata"]["brand"].")"."</td></tr>\n";
                $table .= '<tr><td>'.ENTRY_UNZER_PAYMENT_LINK.'</td><td>'."<a target='_blank' href='".$plink."' >".$plink."</a>"."</td></tr>\n";
                $table .= '<tr><td>'.ENTRY_UNZER_STATUS.'</td></tr><tr><td colspan="2">'.$api->log_operations($operations, $ostatus['currency'])."</td></tr>\n";
                $table .= '</table>'."\n";

                $tab_title = addslashes(sprintf(TAB_UNZER_TRANSACTION,$unzer_status));
                $tab_link = substr(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params()), strlen($base_url)) . '#section_unzer_payment';

                $capture_confirm = CONFIRM_CAPTURE;

                $output = <<<EOD
<script><!--
$(function() {
  $('#orderTabs ul').first().append('<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#section_unzer_payment" role="tab">{$tab_title}</a></li>');

});
function unzer_check_confirm(confirm_text) {
    return confirm(confirm_text);
}

function unzer_check_capture(amount_big, amount_small, confirm_text) {
    if (Number(document.transaction_form.amount_big.value) == Number(amount_big) && Number(document.transaction_form.amount_small.value) == Number(amount_small)) {
        return true;
    } else {
        return confirm("$capture_confirm");
    }
}
//--></script>

<div class="tab-pane fade" id="section_unzer_payment" role="tabpanel">
 $heading $form
 $errormsg $table
</div>
EOD;

            }
        }
/*
		global $OSCOM_Hooks;
		echo $OSCOM_Hooks->call('orders', 'orderTab');
*/
        return $output;
    }
}
