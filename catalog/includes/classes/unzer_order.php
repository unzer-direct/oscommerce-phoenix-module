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

class unzer_order extends order
{
    /* CAUTION - unlike parent constructor, this takes a populated order object */
    public function __construct($order)
    {
        $order_id = $order->id;

        /* Check if valid id */
        if (!(int)$order_id > 0) {
            return false;
        }

        /* Init inheritance */
        parent::__construct($order_id);

        /* Copy prepopulated data */
        foreach (get_object_vars($order) as $key => $value) {
            $this->$key = $value;
        }

        $this->id_query($order_id);
    }

    /* Decorate the object */
    public function id_query($order_id)
    {
        $order_id = tep_db_prepare_input($order_id);
        $order_query = tep_db_query("select cc_transactionid, cc_cardhash, cc_cardtype from orders where orders_id = '" . (int)$order_id . "'");
        $order = tep_db_fetch_array($order_query);
        $this->info['cc_transactionid'] = $order['cc_transactionid'];
        $this->info['cc_cardhash'] = $order['cc_cardhash'];
        $this->info['cc_cardtype'] = $order['cc_cardtype'];
    }
}
