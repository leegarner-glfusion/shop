<?php
/**
 * Dummy IPN processor for orders with zero balances.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

// Get the complete IPN message prior to any processing
SHOP_log("Recieved IPN:", SHOP_LOG_DEBUG);
SHOP_log(var_export($_POST, true), SHOP_LOG_DEBUG);

// Process IPN request
$ipn = \Shop\IPN::getInstance('internal', $_POST);
if ($ipn) {
    $ipn->Process();
}

if (!isset($_GET['debug'])) {
    COM_refresh(SHOP_URL . '/index.php?thanks');
} else {
    echo 'Debug Finished';
}

?>
