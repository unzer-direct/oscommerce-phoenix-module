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

if (!defined('FILENAME_ORDERS')) define('FILENAME_ORDERS','orders.php');
if (!defined('DIR_FS_CATALOG_LANGUAGES')) define('DIR_FS_CATALOG_LANGUAGES', DIR_FS_CATALOG . 'includes/languages/');

class hook_admin_orders_unzer_payment_action {

    function load_language() {
        global $language;
        include_once(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/hooks/admin/' . basename(__FILE__));
    }

    function execute() {
          global $languages_id;

          if ( isset($_GET['tabaction']) ) {
            $this->load_language();

            $oID = $_GET['oID'];
            $amount = $_POST['amount_big'] . $_POST['amount_small'];

            switch ($_GET['tabaction']) {
                case 'unzer_reverse':
                    $result = get_unzer_reverse($oID);
                    tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('tabaction')) . 'action=edit'));
                    break;

                case 'unzer_capture':
                    if (!isset($_POST['amount_big']) || $_POST['amount_big'] == '' || $_POST['amount_big'] == 0) {
                        tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('tabaction')) . 'action=edit'));
                    }
                    $result = get_unzer_capture($oID, $amount);
                    tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('tabaction')) . 'action=edit'));
                    break;

                case 'unzer_credit':
                    $result = get_unzer_credit($oID, $amount);
                    tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('tabaction')) . 'action=edit'));
                    break;

            }
        }
    }
}
