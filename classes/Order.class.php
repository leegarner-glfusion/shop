<?php
/**
 * Order class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Order class.
 * @package shop
 */
class Order
{
    /** Session variable name for storing cart info.
     * @var string */
    protected static $session_var = 'glShopCart';

    /** Flag to indicate that administrative actions are being done.
     * @var boolean */
    private $isAdmin = false;

    /** Internal properties set via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Flag to indicate that this order has been finalized.
     * @var boolean */
    private $is_final = false;

    /** Flag to indicate that this is a new record.
     * @var boolean */
    protected $isNew = true;

    /** Miscellaneious information values used by the Cart class.
     * @var array */
    protected $m_info = array();

    /** Flag to indicate that "no shipping" should be set.
     * @deprecated ?
     * @var boolean */
    var $no_shipping = 1;

    /** Address field names.
     * @var array */
    protected $_addr_fields = array(
        'name', 'company', 'address1', 'address2',
        'city', 'state', 'country', 'zip',
    );

    /** OrderItem objects.
     * @var array */
    protected $items = array();

    /** Order item total.
     * @var float */
    protected $subtotal = 0;

    /** Order final total, incl. shipping, handling, etc.
     * @var float */
    protected $total = 0;

    /** Number of taxable line items on the order.
     * @var integer */
    protected $tax_items = 0;

    /** Currency object, used for formatting amounts.
     * @var object */
    protected $Currency;

    /** Statuses that indicate an order is still in a "cart" phase.
     * @var array */
    protected static $nonfinal_statuses = array('cart', 'pending');

    /** Order Date.
     * This field is defined here since it contains an object and
     * needs to be accessed directly.
     * @var object */
    protected $order_date;

    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $id     Optional order ID to read
     */
    public function __construct($id='')
    {
        global $_USER, $_SHOP_CONF;

        $this->isNew = true;
        $this->uid = (int)$_USER['uid'];
        $this->instructions = '';
        $this->tax_rate = SHOP_getTaxRate();
        $this->currency = $_SHOP_CONF['currency'];
        if (!empty($id)) {
            $this->order_id = $id;
            if (!$this->Load($id)) {
                $this->isNew = true;
                $this->items = array();
            } else {
                $this->isNew = false;
            }
        }
        if ($this->isNew) {
            $this->order_id = self::_createID();
            $this->order_date = SHOP_now();
            $this->token = self::_createToken();
            $this->shipping = 0;
            $this->handling = 0;
            $this->by_gc = 0;
        }
    }


    /**
     * Get an object instance for an order.
     *
     * @param   string  $id     Order ID
     * @return  object          Order object
     */
    public static function getInstance($id)
    {
        static $orders = array();
        if (!array_key_exists($id, $orders)) {
            $orders[$id] = new self($id);
        }
        return $orders[$id];
    }


    /**
     * Set a property value.
     *
     * @param   string  $name   Name of property to set
     * @param   mixed   $value  Value to set
     */
    function __set($name, $value)
    {
        switch ($name) {
        case 'uid':
        case 'billto_id':
        case 'shipto_id':
            $this->properties[$name] = (int)$value;
            break;

        case 'tax':
        case 'tax_rate':
        case 'shipping':
        case 'handling':
        case 'by_gc':
        case 'ship_units':
            $this->properties[$name] = (float)$value;
            break;

        case 'order_seq':
        default:
            $this->properties[$name] = $value;
            break;
        }
    }


    /**
     * Return the value of a property, or NULL if the property is not set.
     *
     * @param   string  $name   Name of property to retrieve
     * @return  mixed           Value of property
     */
    function __get($name)
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else {
            return NULL;
        }
    }


    /**
     * Load the order information from the database.
     *
     * @param   string  $id     Order ID
     * @return  boolean     True on success, False if order not found
     */
    public function Load($id = '')
    {
        global $_TABLES;

        if ($id != '') {
            $this->order_id = $id;
        }

        $A = Cache::get('order_' . $this->order_id);
        if ($A === NULL) {
            $sql = "SELECT * FROM {$_TABLES['shop.orders']}
                    WHERE order_id='{$this->order_id}'";
            //echo $sql;die;
            $res = DB_query($sql);
            if (!$res) return false;    // requested order not found
            $A = DB_fetchArray($res, false);
            if (empty($A)) return false;
            Cache::set('order_' . $this->order_id, $A, 'orders');
        }
        if ($this->setVars($A)) $this->isNew = false;

        // Now load the items
        $items = Cache::get('items_order_' . $this->order_id);
        if ($items === NULL) {
            $items = array();
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}
                    WHERE order_id = '{$this->order_id}'";
            $res = DB_query($sql);
            if ($res) {
                while ($A = DB_fetchArray($res, false)) {
                    $items[$A['id']] = $A;
                }
            }
            Cache::set('items_order_' . $this->order_id, $items, array('items','orders'));
        }
        // Now load the arrays into objects
        foreach ($items as $item) {
            $this->items[$item['id']] = new OrderItem($item);
        }
        return true;
    }


    /**
     * Add a single item to this order.
     * Extracts item information from the provided $data variable, and
     * reads the item information from the database as well.  The entire
     * item record is added to the $items array as 'data'
     *
     * @param   array   $args   Array of item data
     */
    public function addItem($args)
    {
        if (!is_array($args)) return;
        $args['order_id'] = $this->order_id;    // make sure it's set
        $args['token'] = self::_createToken();  // create a unique token
        $item = new OrderItem($args);
        $this->items[] = $item;
        $this->Save();
    }


    /**
     * Set the billing address.
     *
     * @param   array   $A      Array of info, such as from $_POST
     */
    public function setBilling($A)
    {
        $addr_id = SHOP_getVar($A, 'useaddress', 'integer', 0);
        if ($addr_id > 0) {
            // If set, the user has selected an existing address. Read
            // that value and use it's values.
            Cart::setSession('billing', $addr_id);
        }

        if (!empty($A)) {
            $this->billto_id        = $addr_id;
            $this->billto_name      = SHOP_getVar($A, 'name');
            $this->billto_company   = SHOP_getVar($A, 'company');
            $this->billto_address1  = SHOP_getVar($A, 'address1');
            $this->billto_address2  = SHOP_getVar($A, 'address2');
            $this->billto_city      = SHOP_getVar($A, 'city');
            $this->billto_state     = SHOP_getVar($A, 'state');
            $this->billto_country   = SHOP_getVar($A, 'country');
            $this->billto_zip       = SHOP_getVar($A, 'zip');
        }
        $this->Save();
    }


    /**
     * Set the shipping address.
     *
     * @param   array|NULL  $A      Array of info, or NULL to clear
     */
    public function setShipping($A)
    {
        if ($A === NULL) {
            // Clear out the shipping address
            $this->shipto_id        = 0;
            $this->shipto_name      = '';
            $this->shipto_company   = '';
            $this->shipto_address1  = '';
            $this->shipto_address2  = '';
            $this->shipto_city      = '';
            $this->shipto_state     = '';
            $this->shipto_country   = '';
            $this->shipto_zip       = '';
        } elseif (is_array($A)) {
             $addr_id = SHOP_getVar($A, 'useaddress', 'integer', 0);
            if ($addr_id > 0) {
                // If set, read and use an existing address
                Cart::setSession('shipping', $addr_id);
            }

            if (!empty($A)) {
                $this->shipto_id        = $addr_id;
                $this->shipto_name      = SHOP_getVar($A, 'name');
                $this->shipto_company   = SHOP_getVar($A, 'company');
                $this->shipto_address1  = SHOP_getVar($A, 'address1');
                $this->shipto_address2  = SHOP_getVar($A, 'address2');
                $this->shipto_city      = SHOP_getVar($A, 'city');
                $this->shipto_state     = SHOP_getVar($A, 'state');
                $this->shipto_country   = SHOP_getVar($A, 'country');
                $this->shipto_zip       = SHOP_getVar($A, 'zip');
            }
        }
        $this->Save();
    }


    /**
     * Set all class variables, from a form or a database item
     *
     * @param   array   $A      Array of items
     */
    function setVars($A)
    {
        global $_USER, $_CONF, $_SHOP_CONF;

        if (!is_array($A)) return false;
        $tzid = COM_isAnonUser() ? $_CONF['timezone'] : $_USER['tzid'];

        $this->uid      = SHOP_getVar($A, 'uid', 'int');
        $this->status   = SHOP_getVar($A, 'status');
        $this->pmt_method = SHOP_getVar($A, 'pmt_method');
        $this->pmt_txn_id = SHOP_getVar($A, 'pmt_txn_id');
        $this->currency = SHOP_getVar($A, 'currency', 'string', $_SHOP_CONF['currency']);
        $dt = SHOP_getVar($A, 'order_date', 'integer');
        if ($dt > 0) {
            $this->order_date = new \Date($dt, $tzid);
        } else {
            $this->order_date = SHOP_now();
        }
        $this->order_id = SHOP_getVar($A, 'order_id');
        $this->shipping = SHOP_getVar($A, 'shipping', 'float');
        $this->handling = SHOP_getVar($A, 'handling', 'float');
        $this->tax = SHOP_getVar($A, 'tax', 'float');
        $this->instructions = SHOP_getVar($A, 'instructions');
        $this->by_gc = SHOP_getVar($A, 'by_gc', 'float');
        $this->token = SHOP_getVar($A, 'token', 'string');
        $this->buyer_email = SHOP_getVar($A, 'buyer_email');
        $this->billto_id = SHOP_getVar($A, 'billto_id', 'integer');
        $this->shipto_id = SHOP_getVar($A, 'shipto_id', 'integer');
        $this->order_seq = SHOP_getVar($A, 'order_seq', 'integer');
        if ($this->status != 'cart') {
            $this->tax_rate = SHOP_getVar($A, 'tax_rate');
        }
        $this->m_info = @unserialize(SHOP_getVar($A, 'info'));
        if ($this->m_info === false) $this->m_info = array();
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $this->$fld = $A[$fld];
            }
        }
        if (isset($A['uid'])) $this->uid = $A['uid'];

        if (isset($A['order_id']) && !empty($A['order_id'])) {
            $this->order_id = $A['order_id'];
            $this->isNew = false;
            Cart::setSession('order_id', $A['order_id']);
        } else {
            $this->order_id = '';
            $this->isNew = true;
            Cart::clearSession('order_id');
        }
    }


    /**
     * API function to delete an entire order record.
     * Only orders that have a status of "cart" or "pending" can be deleted.
     * Finalized (paid, shipped, etc.) orders cannot  be removed.
     * Trying to delete a nonexistant order returns true.
     *
     * @param   string  $order_id       Order ID, taken from $_SESSION if empty
     * @return  boolean     True on success, False on error.
     */
    public static function Delete($order_id = '')
    {
        global $_TABLES;

        if ($order_id == '') {
            $order_id = Cart::getSession('order_id');
        }
        if (!$order_id) return true;

        $order_id = DB_escapeString($order_id);

        // Just get an instance of this order since there are a couple of values to check.
        $Ord = self::getInstance($order_id);
        if ($Ord->isNew) return true;

        // Only orders with no sequence number can be deleted.
        // Only orders with certain status values can be deleted.
        if ($Ord->order_seq !== NULL || $Ord->isFinal()) {
            return false;
        }

        // Checks passed, delete the order and items
        $sql = "START TRANSACTION;
            DELETE FROM {$_TABLES['shop.orderitems']} WHERE order_id = '$order_id';
            DELETE FROM {$_TABLES['shop.orders']} WHERE order_id = '$order_id';
            COMMIT;";
        DB_query($sql);
        Cache::deleteOrder($order_id);
        return DB_error() ? false : true;
    }


    /**
     * Save the current order to the database
     *
     * @return  string      Order ID
     */
    public function Save()
    {
        global $_TABLES, $_SHOP_CONF;

        if (!SHOP_isMinVersion()) return '';

        // Save all the order items
        foreach ($this->items as $item) {
            $item->Save();
        }

        if ($this->isNew) {
            // Shouldn't have an empty order ID, but double-check
            if ($this->order_id == '') $this->order_id = self::_createID();
            if ($this->billto_name == '') {
                $this->billto_name = COM_getDisplayName($this->uid);
            }
            Cart::setSession('order_id', $this->order_id);
            // Set field values that can only be set once and not updated
            $sql1 = "INSERT INTO {$_TABLES['shop.orders']} SET
                    order_id='{$this->order_id}',
                    token = '" . DB_escapeString($this->token) . "',
                    uid = '" . (int)$this->uid . "', ";
            $sql2 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.orders']} SET ";
            $sql2 = " WHERE order_id = '{$this->order_id}'";
        }
        $this->calcTotalCharges();

        $fields = array(
                "order_date = '{$this->order_date->toUnix()}'",
                "status = '{$this->status}'",
                "pmt_txn_id = '" . DB_escapeString($this->pmt_txn_id) . "'",
                "pmt_method = '" . DB_escapeString($this->pmt_method) . "'",
                "by_gc = '{$this->by_gc}'",
                "phone = '" . DB_escapeString($this->phone) . "'",
                "tax = '{$this->tax}'",
                "shipping = '{$this->shipping}'",
                "handling = '{$this->handling}'",
                "instructions = '" . DB_escapeString($this->instructions) . "'",
                "buyer_email = '" . DB_escapeString($this->buyer_email) . "'",
                "info = '" . DB_escapeString(@serialize($this->m_info)) . "'",
                "tax_rate = '{$this->tax_rate}'",
                "currency = '{$this->currency}'",
        );
        foreach (array('billto', 'shipto') as $type) {
            $fld = $type . '_id';
            $fields[] = "$fld = " . (int)$this->$fld;
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $fields[] = $fld . "='" . DB_escapeString($this->$fld) . "'";
            }
        }
        $sql = $sql1 . implode(', ', $fields) . $sql2;
        //echo $sql;die;
        //COM_errorLog("Save: " . $sql);
        DB_query($sql);
        Cache::deleteOrder($this->order_id);
        $this->isNew = false;
        return $this->order_id;
    }


    /**
     * View or print the current order.
     * Access is controlled by the caller invoking canView() since a token
     * may be required.
     *
     * @param  string  $view       View to display (cart, final order, etc.)
     * @param  integer $step       Current step, for updating next_step in the form
     * @return string      HTML for order view
     */
    public function View($view = 'order', $step = 0)
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

        $this->is_final = false;
        $is_invoice = true;    // normal view/printing view
        $icon_tooltips = array();

        switch ($view) {
        case 'order':
        case 'adminview';
            $this->is_final = true;
        case 'checkout':
            $tplname = 'order';
            break;
        case 'viewcart':
            $tplname = 'viewcart';
            break;
        case 'packinglist':
            // Print a packing list. Same as print view but no prices or fees shown.
            $is_invoice = false;
        case 'print':
        case 'printorder':
            $this->is_final = true;
            $tplname = 'order.print';
            break;
        }
        $step = (int)$step;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('order', $tplname . '.thtml');
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $this->$fldname);
            }
        }

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (Workflow::getAll($this) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        $Currency = Currency::getInstance($this->currency);
        $this->no_shipping = 1;   // no shipping unless physical item ordered
        $this->subtotal = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            $item_total = $item->price * $item->quantity;
            $this->subtotal += $item_total;
            if ($item->taxable) {
                $this->tax_items++;       // count the taxable items for display
            }
            $T->set_var(array(
                'cart_item_id'  => $item->id,
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($item->product_id),
                'item_dscp'     => htmlspecialchars($item->description),
                'item_price'    => $Currency->FormatValue($item->price),
                'item_quantity' => (int)$item->quantity,
                'item_total'    => $Currency->FormatValue($item_total),
                'is_admin'      => $this->isAdmin ? 'true' : '',
                'is_file'       => $item->canDownload() ? true : false,
                'taxable'       => $this->tax_rate > 0 ? $P->taxable : 0,
                'tax_icon'      => $LANG_SHOP['tax'][0],
                'token'         => $item->token,
                'item_options'  => $P->getOptionDisplay($item),
                'item_link'     => $P->getLink(),
                'pi_url'        => SHOP_URL,
                'is_invoice'    => $is_invoice,
                'del_item_url'  => COM_buildUrl(SHOP_URL . "/cart.php?action=delete&id={$item->id}"),
            ) );
            if ($P->isPhysical()) {
                $this->no_shipping = 0;
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($this->tax_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['taxable'][0] . ' = ' . $LANG_SHOP['taxable'];
        }
        $this->total = $this->getTotal();     // also calls calcTax()

        $icon_tooltips = implode('<br />', $icon_tooltips);

        $by_gc = (float)$this->getInfo('apply_gc');
        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'total'         => $Currency->Format($this->total),
            'not_final'     => !$this->is_final,
            'order_date'    => $this->order_date->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->order_date->format($_SHOP_CONF['datetime_fmt'], false),
            'order_number' => $this->order_id,
            'shipping'      => $this->shipping > 0 ? $Currency->FormatValue($this->shipping) : 0,
            'handling'      => $this->handling > 0 ? $Currency->FormatValue($this->handling) : 0,
            'subtotal'      => $this->subtotal == $this->total ? '' : $Currency->Format($this->subtotal),
            'order_instr'   => htmlspecialchars($this->instructions),
            'shop_name'     => $_SHOP_CONF['shop_name'],
            'shop_addr'     => $_SHOP_CONF['shop_addr'],
            'shop_phone'    => $_SHOP_CONF['shop_phone'],
            'apply_gc'      => $by_gc > 0 ? $Currency->FormatValue($by_gc) : 0,
            'net_total'     => $Currency->Format($this->total - $by_gc),
            'cart_tax'      => $this->tax > 0 ? $Currency->FormatValue($this->tax) : 0,
            'tax_on_items'  => sprintf($LANG_SHOP['tax_on_x_items'], $this->tax_rate * 100, $this->tax_items),
            'status'        => $this->status,
            'token'         => $this->token,
            'allow_gc'      => $_SHOP_CONF['gc_enabled']  && !COM_isAnonUser() ? true : false,
            'next_step'     => $step + 1,
            'not_anon'      => !COM_isAnonUser(),
            'ship_method'   => $this->getInfo('shipper_name'),
            'ship_select'   => $this->is_final ? NULL : $this->selectShipper(),
            'total_prefix'  => $Currency->Pre(),
            'total_postfix' => $Currency->Post(),
            'total_num'     => $Currency->FormatValue($this->total),
            'cur_decimals'  => $Currency->Decimals(),
            'item_subtotal' => $Currency->FormatValue($this->subtotal),
            'return_url'    => SHOP_getUrl(),
            'is_invoice'    => $is_invoice,
            'icon_dscp'     => $icon_tooltips,
            'print_url'     => $this->buildUrl('print'),
        ) );
        if ($this->isAdmin) {
            $T->set_var(array(
                'is_admin'      => true,
                'purch_name'    => COM_getDisplayName($this->uid),
                'purch_uid'     => $this->uid,
                'stat_update'   => OrderStatus::Selection($this->order_id, 1, $this->status),
            ) );
        }

        // Instantiate a date objet to handle formatting of log timestamps
        $dt = new \Date('now', $_USER['tzid']);
        $log = $this->getLog();
        $T->set_block('order', 'LogMessages', 'Log');
        foreach ($log as $L) {
            $dt->setTimestamp($L['ts']);
            $T->set_var(array(
                'log_username'  => $L['username'],
                'log_msg'       => $L['message'],
                'log_ts'        => $dt->format($_SHOP_CONF['datetime_fmt'], true),
                'log_ts_tip'    => $dt->format($_SHOP_CONF['datetime_fmt'], false),
            ) );
            $T->parse('Log', 'LogMessages', true);
        }

        $payer_email = $this->buyer_email;
        if ($payer_email == '' && !COM_isAnonUser()) {
            $payer_email = $_USER['email'];
        }
        $T->set_var('payer_email', $payer_email);

        switch ($view) {
        case 'viewcart':
            $T->set_var('gateway_radios', $this->getCheckoutRadios());
            break;
        case 'checkout':
            $gw = Gateway::getInstance($this->getInfo('gateway'));
            if ($gw) {
                $T->set_var(array(
                    'gateway_vars'  => $this->checkoutButton($gw),
                    'checkout'      => 'true',
                    'pmt_method'    => $gw->Description(),
                ) );
            }
        default:
            break;
        }

        $status = $this->status;
        if ($this->pmt_method != '') {
            $gw = Gateway::getInstance($this->pmt_method);
            if ($gw !== NULL) {
                $pmt_method = $gw->Description();
            } else {
                $pmt_method = $this->pmt_method;
            }

            $T->set_var(array(
                'pmt_method' => $pmt_method,
                'pmt_txn_id' => $this->pmt_txn_id,
            ) );
        }

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Update the order's status flag to a new value.
     * If the new status isn't really new, the order is unchanged and "true"
     * is returned.  If this is called by some automated process, $log can
     * be set to "false" to avoid logging the change, such as during order
     * creation.
     *
     * @uses    Order::Log()
     * @param   string  $newstatus      New order status
     * @param   boolean $log            True to log the change, False to not
     * @return  boolean                 True on success or no change
     */
    public function updateStatus($newstatus, $log = true)
    {
        global $_TABLES, $LANG_SHOP;

        $oldstatus = $this->status;
        // If the status isn't really changed, don't bother updating anything
        // and just treat it as successful
        if ($oldstatus == $newstatus) return true;

        $this->status = $newstatus;
        $db_order_id = DB_escapeString($this->order_id);
        $log_user = $this->log_user;

        // Clear the order from cache
        Cache::delete('order_' . $this->order_id);

        // If promoting from a cart status to a real order, add the sequence number.
        if (!$this->isFinal($oldstatus) && $this->isFinal() && $this->order_seq < 1) {
            $sql = "START TRANSACTION;
                SELECT COALESCE(MAX(order_seq)+1,1) FROM {$_TABLES['shop.orders']} INTO @seqno FOR UPDATE;
                UPDATE {$_TABLES['shop.orders']}
                    SET status = '". DB_escapeString($newstatus) . "',
                    order_seq = @seqno
                WHERE order_id = '$db_order_id';
                COMMIT;";
            DB_query($sql);
            $this->order_seq = (int)DB_getItem($_TABLES['shop.orders'], 'order_seq', "order_id = '{$db_order_id}'");
        } else {
            // Update the status but leave the sequence alone
            $sql = "UPDATE {$_TABLES['shop.orders']} SET
                    status = '". DB_escapeString($newstatus) . "'
                WHERE order_id = '$db_order_id';";
            DB_query($sql);
        }
        //echo $sql;die;
        //COM_errorLog($sql);
        if (DB_error()) return false;
        $this->status = $newstatus;     // update in-memory object
        if ($log) {
            $this->Log(
                sprintf($LANG_SHOP['status_changed'], $oldstatus, $newstatus),
                $log_user
            );
        }
        $this->Notify($newstatus);
        return true;
    }


    /**
     * Log a message related to this order.
     * Typically used to log status changes.  If this is called for an
     * order object, the local "log_user" variable can be preset to the
     * log user name.  Otherwise, the current user's display name will be
     * associated with the log entry.
     *
     * @param   string  $msg        Log message
     * @param   string  $log_user   Optional log username
     */
    public function Log($msg, $log_user = '')
    {
        global $_TABLES, $_USER;

        // Don't log empty messages by mistake
        if (empty($msg)) return;

        // If the order ID is omitted, get information from the current
        // object.
        if (empty($log_user)) {
            $log_user = COM_getDisplayName($_USER['uid']) .
                ' (' . $_USER['uid'] . ')';
        }
        $order_id = DB_escapeString($this->order_id);
        $sql = "INSERT INTO {$_TABLES['shop.order_log']} SET
            username = '" . DB_escapeString($log_user) . "',
            order_id = '$order_id',
            message = '" . DB_escapeString($msg) . "',
            ts = UNIX_TIMESTAMP()";
        DB_query($sql);
        $cache_key = 'orderlog_' . $order_id;
        Cache::delete($cache_key);
        return;
    }


    /**
     * Get the last log entry.
     * Called from admin ajax to display the log after the status is updated.
     * Resets the "ts" field to the formatted timestamp.
     *
     * @return  array   Array of DB fields.
     */
    public function getLastLog()
    {
        global $_TABLES, $_SHOP_CONF, $_USER;

        $sql = "SELECT * FROM {$_TABLES['shop.order_log']}
                WHERE order_id = '" . DB_escapeString($this->order_id) . "'
                ORDER BY ts DESC
                LIMIT 1";
        //echo $sql;die;
        if (!DB_error()) {
            $L = DB_fetchArray(DB_query($sql), false);
            if (!empty($L)) {
                $dt = new \Date($L['ts'], $_USER['tzid']);
                $L['ts'] = $dt->format($_SHOP_CONF['datetime_fmt'], true);
            }
        }
        return $L;
    }


    /**
     * Send an email to the buyer.
     *
     * @param   string  $status     Order status (pending, paid, etc.)
     * @param   string  $gw_msg     Optional gateway message to include with email
     */
    public function Notify($status='', $gw_msg='')
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // Check if any notification is to be sent for this status update, to
        // save effort. If either the buyer or admin gets notified then
        // proceed to construct the messages.
        $notify_buyer = OrderStatus::getInstance($status)->notifyBuyer();
        $notify_admin = OrderStatus::getInstance($status)->notifyAdmin();
        if (!$notify_buyer && !$notify_admin) {
            SHOP_debug("Not sending any notification for status $status");
            return;
        }

        // setup templates
        $T = SHOP_getTemplate(array(
            'subject' => 'purchase_email_subject',
            'msg_admin' => 'purchase_email_admin',
            'msg_user' => 'purchase_email_user',
            'msg_body' => 'purchase_email_body',
        ) );

        // Add all the items to the message
        $total = (float)0;      // Track total purchase value
        $files = array();       // Array of filenames, for attachments
        $item_total = 0;
        $dl_links = '';         // Start with empty download links
        $email_extras = array();
        $Cur = Currency::getInstance();     // get currency for formatting

        foreach ($this->items as $id=>$item) {
            $P = $item->getProduct();

            // Add the file to the filename array, if any. Download
            // links are only included if the order status is 'paid'
            $file = $P->file;
            if (!empty($file) && $this->status == 'paid') {
                $files[] = $file;
                $dl_url = SHOP_URL . '/download.php?';
                // There should always be a token, but fall back to the
                // product ID if there isn't
                if ($item->token != '') {
                    $dl_url .= 'token=' . urlencode($item->token);
                    $dl_url .= '&i=' . $item->id;
                } else {
                    $dl_url .= 'id=' . $item->product_id;
                }
                $dl_links .= "<a href=\"$dl_url\">$dl_url</a><br />";
            }

            $ext = (float)$item->quantity * (float)$item->price;
            $item_total += $ext;
            $item_descr = $item->getShortDscp();
            $options_text = $P->getOptionDisplay($item);

            $T->set_block('msg_body', 'ItemList', 'List');
            $T->set_var(array(
                'qty'   => $item->quantity,
                'price' => $Cur->FormatValue($item->price),
                'ext'   => $Cur->FormatValue($ext),
                'name'  => $item_descr,
                'options_text' => $options_text,
            ) );
            $T->parse('List', 'ItemList', true);
            $x = $P->EmailExtra($item);
            if ($x != '') $email_extras[] = $x;
        }

        $total_amount = $item_total + $this->tax + $this->shipping + $this->handling;
        $user_name = COM_getDisplayName($this->uid);
        if ($this->billto_name == '') {
            $this->billto_name = $user_name;
        }

        $T->set_var(array(
            'payment_gross'     => $Cur->Format($total_amount),
            'payment_items'     => $Cur->Format($item_total),
            'tax'               => $Cur->FormatValue($this->tax),
            'tax_num'           => $this->tax,
            'shipping'          => $Cur->FormatValue($this->shipping),
            'shipping_num'      => $this->shipping,
            'handling'          => $Cur->FormatValue($this->handling),
            'handling_num'      => $this->handling,
            'payment_date'      => SHOP_now()->toMySQL(true),
            'payer_email'       => $this->buyer_email,
            'payer_name'        => $this->billto_name,
            'site_name'         => $_CONF['site_name'],
            'txn_id'            => $this->pmt_txn_id,
            'pi_url'            => SHOP_URL,
            'pi_admin_url'      => SHOP_ADMIN_URL,
            'dl_links'          => $dl_links,
            'buyer_uid'         => $this->uid,
            'user_name'         => $user_name,
            'gateway_name'      => $this->pmt_method,
            'pending'           => $this->status == 'pending' ? 'true' : '',
            'gw_msg'            => $gw_msg,
            'status'            => $this->status,
            'order_instr'       => $this->instructions,
            'order_id'          => $this->order_id,
            'token'             => $this->token,
            'email_extras'      => implode('<br />' . LB, $email_extras),
            'order_date'        => $this->order_date->format($_SHOP_CONF['datetime_fmt'], true),
            'order_url'         => $this->buildUrl('view'),
        ) );

        $this->_setAddressTemplate($T);

        // If any part of the order is paid by gift card, indicate that and
        // calculate the net amount paid by shop, etc.
        if ($this->by_gc > 0) {
            $T->set_var(array(
                'by_gc'     => $Cur->FormatValue($this->by_gc),
                'net_total' => $Cur->Format($total_amount - $this->by_gc),
            ) );
        }

        // Show the remaining gift card balance, if any.
        $gc_bal = Coupon::getUserBalance($this->uid);
        if ($gc_bal > 0) {
            $T->set_var(array(
                'gc_bal_fmt' => $Cur->Format($gc_bal),
                'gc_bal_num' => $gc_bal,
            ) );
        }

        // parse templates for subject/text
        $T->set_var('purchase_details',
                        $T->parse('detail', 'msg_body'));
        $user_text  = $T->parse('user_out', 'msg_user');
        $admin_text = $T->parse('admin_out', 'msg_admin');

        // Send a notification to the buyer, depending on the status
        if ($notify_buyer) {
            SHOP_debug("Sending email to " . $this->uid . ' at ' . $this->buyer_email);
            if ($this->buyer_email != '') {
                COM_emailNotification(array(
                    'to' => array($this->buyer_email),
                    'from' => $_CONF['site_mail'],
                    'htmlmessage' => $user_text,
                    'subject' => $LANG_SHOP['subj_email_user'],
                ) );
            }
        }

        // Send a notification to the administrator, depending on the status
        if ($notify_admin) {
            $email_addr = empty($_SHOP_CONF['admin_email_addr']) ?
                $_CONF['site_mail'] : $_SHOP_CONF['admin_email_addr'];
            SHOP_debug("Sending email to admin at $email_addr");
            COM_emailNotification(array(
                'to' => array($email_addr),
                'from' => $_CONF['noreply_mail'],
                'htmlmessage' => $admin_text,
                'subject' => $LANG_SHOP['subj_email_admin'],
            ) );
        }

    }   // Notify()


    /**
     * Get the miscellaneous charges on this order.
     * Just a shortcut to adding up the non-item charges.
     *
     * @return  float   Total "other" charges, e.g. tax, shipping, etc.
     */
    public function miscCharges()
    {
        return $this->shipping + $this->handling + $this->tax;
    }


    /**
     * Check the user's permission to view this order or cart.
     *
     * @return  boolean     True if allowed to view, False if denied.
     */
    public function canView($token='')
    {
        global $_USER;

        if ($this->isNew || $this->status == 'cart') {
            // Record not found in DB, or this is a cart (not an order)
            return false;
        } elseif ($this->uid > 1 && $_USER['uid'] == $this->uid ||
            plugin_ismoderator_shop()) {
            // Administrator, or logged-in buyer
            return true;
        } elseif ($token !== '' && $token == $this->token) {
            return true;
        } elseif (isset($_GET['token']) && $_GET['token'] == $this->token) {
            // Anonymous with the correct token
            return true;
        } else {
            // Unauthorized
            return false;
        }
    }


    /**
     * Get all the log entries for this order.
     *
     * @return  array   Array of log entries
     */
    public function getLog()
    {
        global $_TABLES, $_CONF;

        $order_id = DB_escapeString($this->order_id);
        $cache_key = 'orderlog_' . $order_id;
        $log = Cache::get($cache_key);
        if ($log === NULL) {
            $log = array();
            $sql = "SELECT * FROM {$_TABLES['shop.order_log']}
                    WHERE order_id = '$order_id'";
            $res = DB_query($sql);
            while ($L = DB_fetchArray($res, false)) {
                $log[] = $L;
            }
            Cache::set($cache_key, $log, 'order_log');
        }
        return $log;
    }


    /**
     * Calculate the tax on this order.
     * Sets the tax and tax_items properties and returns the tax amount.
     *
     * @return  float   Sales Tax amount
     */
    public function calcTax()
    {
        if ($this->tax_rate == 0) {
            $this->tax_items = 0;
            $this->tax = 0;
        } else {
            $tax = 0;
            $this->tax_items = 0;
            foreach ($this->items as $item) {
                if ($item->getProduct()->taxable) {
                    $tax += Currency::getInstance()->RoundVal($this->tax_rate * $item->quantity * $item->price);
                    $this->tax_items += 1;
                }
            }
            //$this->tax = Currency::getInstance()->RoundVal($this->tax_rate * $tax_amt);
            $this->tax = $tax;
        }
        return $this->tax;
    }


    /**
     * Calculate the total shipping fee for this order.
     * Sets $this->shipping, no return value.
     */
    public function calcShipping()
    {
        $units = 0;
        $fixed = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            if ($P->isPhysical()) {
                $fixed += $P->getShipping($item->quantity);
                $units += $P->shipping_units * $item->quantity;
            }
        }

        $shipper_id = $this->getInfo('shipper_id');
        if ($shipper_id !== NULL) {
            $shippers = Shipper::getShippers($units);
            if (isset($shippers[$shipper_id])) {
                $this->shipping = $shippers[$shipper_id]->best_rate + $fixed;
            } else {
                $shipper = Shipper::getBestRate($units);
                $this->ship_method = $shipper->name;
                $this->shipping = $shipper->best_rate + $fixed;
            }
        } else {
            // Now get the order shipping, if any, based on product units.
            $shipper = Shipper::getBestRate($units);
            $this->ship_method = $shipper->name;
            $this->shipping = $shipper->best_rate + $fixed;
        }
    }


    /**
     * Calculate total additional charges: tax, shipping and handling..
     * Simply totals the amounts for each item.
     *
     * @return  float   Total additional charges
     */
    public function calcTotalCharges()
    {
        global $_SHOP_CONF;

        $this->handling = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            $this->handling += $P->getHandling($item->quantity);
        }

        $this->calcTax();   // Tax calculation is slightly more complex
        $this->calcShipping();
        return $this->tax + $this->shipping + $this->handling;
    }


    /**
     * Create a random token string for this order to allow anonymous users
     * to view the order from an email link.
     *
     * @return  string      Token string
     */
    private static function _createToken()
    {
        $len = 13;
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($len / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($len / 2));
        } else {
            $options = array(
                'length'    => $len * 2,
                'letters'   => 3,       // mixed case
                'numbers'   => true,    // include numbers
                'symbols'   => true,    // include symbols
                'mask'      => '',
            );
            $bytes = Coupon::generate($options);
        }
        return substr(bin2hex($bytes), 0, $len);
    }


    /**
     * Get the order total, including tax, shipping and handling.
     *
     * @return  float   Total order amount
     */
    public function getTotal()
    {
        $total = 0;
        foreach ($this->items as $id => $item) {
            $total += ($item->price * $item->quantity);
        }
        if ($this->is_final) {
            // Already have the amounts calculated, don't do it again
            // every time the order is viewed since rates may change.
            $total += $this->shipping + $this->tax + $this->handling;
        } else {
            $total += $this->calcTotalCharges();
        }
        return Currency::getInstance()->RoundVal($total);
    }


    /**
     * Set the isAdmin field to indicate whether admin access is being requested.
     *
     * @param   boolean $isAdmin    True to get admin view, False for user view
     */
    public function setAdmin($isAdmin = false)
    {
        $this->isAdmin = $isAdmin == false ? false : true;
    }


    /**
     * Create the order ID.
     * Since it's transmitted in cleartext, it'd be a good idea to
     * use something more "encrypted" than just the session ID.
     * On the other hand, it can't be too random since it needs to be
     * repeatable.
     *
     * @return  string  Order ID
     */
    protected static function _createID()
    {
        global $_TABLES;
        if (function_exists('CUSTOM_shop_orderID')) {
            $func = 'CUSTOM_shop_orderID';
        } else {
            $func = 'COM_makeSid';
        }
        do {
            $id = COM_sanitizeID($func());
        } while (DB_getItem($_TABLES['shop.orders'], 'order_id', "order_id = '$id'") !== NULL);
        return $id;
    }


    /**
     * Check if an item already exists in the cart.
     * This can be used to determine whether to add the item or not.
     * Check for "false" return value as the return may be zero for the
     * first item in the cart.
     *
     * @param   string  $item_id    Item ID to check, e.g. "1|2,3,4"
     * @param   array   $extras     Option custom values, e.g. text fields
     * @return  integer|boolean Item cart record ID if item exists in cart, False if not
     */
    public function Contains($item_id, $extras=array())
    {
        $id_parts = SHOP_explode_opts($item_id, true);
        if (!isset($id_parts[1])) $id_parts[1] = '';
        $args = array(
            'product_id'    => $id_parts[0],
            'options'       => $id_parts[1],
            'extras'        => $extras,
        );
        $Item2 = new OrderItem($args);

        foreach ($this->items as $id=>$Item1) {
            if ($Item1->Matches($Item2)) {
                return $id;
            }
        }
        // No matching item_id found
        return false;
    }


    /**
     * Get the requested address array.
     *
     * @param   string  $type   Type of address, billing or shipping
     * @return  array           Array of name=>value address elements
     */
    public function getAddress($type)
    {
        if ($type != 'billto') $type = 'shipto';
        $fields = array();
        foreach ($this->_addr_fields as $name) {
            $fields[$name] = $type . '_' . $name;
        }
        return $fields;
    }


    /**
     * Get the cart info from the private m_info array.
     * If no key is specified, the entire m_info array is returned.
     * If a key is specified but not found, the NULL is returned.
     *
     * @param   string  $key    Specific item to return
     * @return  mixed       Value of item, or entire info array
     */
    public function getInfo($key = '')
    {
        if ($key != '') {
            if (isset($this->m_info[$key])) {
                return $this->m_info[$key];
            } else {
                return NULL;
            }
        } else {
            return $this->m_info;
        }
    }


    /**
     * Get all the items in this order
     *
     * @return  array   Array of OrderItem objects
     */
    public function getItems()
    {
        return $this->items;
    }


    /**
     * Set an info item into the private info array.
     *
     * @param   string  $key    Name of var to set
     * @param   mixed   $value  Value to set
     */
    public function setInfo($key, $value)
    {
        $this->m_info[$key] = $value;
    }


    /**
     * Get the gift card amount applied to this cart.
     *
     * @return  float   Gift card amount
     */
    public function getGC()
    {
        return (float)$this->getInfo('apply_gc');
    }


    /**
     * Apply a gift card amount to this cart.
     *
     * @param   float   $amt    Amount of credit to apply
     */
    public function setGC($amt)
    {
        $amt = (float)$amt;
        if ($amt == -1) {
            $gc_bal = Coupon::getUserBalance();
            $amt = min($gc_bal, Coupon::canPayByGC($this));
        }
        $this->setInfo('apply_gc', $amt);
    }


    /**
     * Set the chosen payment gateway into the cart information.
     * Used so the gateway will be pre-selected if the buyer returns to the
     * cart update page.
     *
     * @param   string  $gw_name    Gateway name
     */
    public function setGateway($gw_name)
    {
        $this->setInfo('gateway', $gw_name);
    }


    /**
     * Check if this order has any physical items.
     * Used to adapt workflows based on product types.
     *
     * @return  boolean     True if at least one physical product is present
     */
    public function hasPhysical()
    {
        foreach ($this->items as $id=>$item) {
            if ($item->getProduct()->isPhysical()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Check if this order is paid.
     * The status may be one of several values like "shipped", "closed", etc.
     * but should not be "cart" or "pending".
     *
     * @return  boolean     True if not a cart or pending order, false otherwise
     */
    public function isPaid()
    {
        switch ($this->status) {
        case 'cart':
        case 'pending':
            return false;
        default:
            return true;
        }
    }


    /**
     * Get shipping information for the items to use when selecting a shipper.
     *
     * @return  array   Array('units'=>unit_count, 'amount'=> fixed per-item amount)
     */
    public function getItemShipping()
    {
        $shipping_amt = 0;
        $shipping_units = 0;
        foreach ($this->items as $item) {
            $shipping_amt += $item->getShippingAmt();
            $shipping_units += $item->getShippingUnits();
        }
        return array(
            'units' => $shipping_units,
            'amount' => $shipping_amt,
        );
    }


    /**
     * Set an order record field to a given value.
     *
     * @deprecated
     * @param   string  $field  Field name.
     * @param   mixed   $value  Field value.
     * @return  boolean     True on success, False on DB error.
     */
    public function setField($field, $value)
    {
        global $_TABLES;

        $value = DB_escapeString($value);
        $order_id = DB_escapeString($this->order_id);
        $sql = "UPDATE {$_TABLES['shop.orders']}
            SET $field = '$value'
            WHERE order_id = '$order_id'";
        $res = DB_query($sql);
        if (DB_error()) {
            COM_errorLog("Order::setField() error executing SQL: $sql");
            return false;
        } else {
            return true;
        }
    }


    /**
     * Set shipper information in the info array, including the best rate.
     *
     * @param   integer $shipper_id     Shipper record ID
     */
    public function setShipper($shipper_id)
    {
        $ship_info = $this->getItemShipping();
        $shippers = \Shop\Shipper::getShippers($ship_info['units']);
        $shipper = SHOP_getVar($shippers, $shipper_id, 'object', NULL);
        if ($shipper !== NULL) {
            $this->setInfo('shipper_name', $shipper->name);
            $this->setInfo('shipper_id', $shipper->id);
            $this->shipping = $shipper->best_rate;
        }
    }


    /**
     * Select the shipping method for this order.
     * Displays a list of shippers with the rates for each
     * @todo    1. Sort by rate
     *          2. Save shipper selection with the order
     *
     *  @param  integer $step   Current step in workflow
     *  @return string      HTML for shipper selection form
     */
    public function selectShipper()
    {
        $T = SHOP_getTemplate('shipping_method', 'form');
        // Get the total units and fixed per-item shipping charges.
        $shipping = $this->getItemShipping();
        // Get all the shippers and rates for the selection
        $shippers = Shipper::getShippers($shipping['units']);
        if (empty($shippers)) return '';

        // Get the best or previously-selected shipper for the default choice
        $shipper_id = $this->getInfo('shipper_id');
        if ($shipper_id !== NULL && isset($shippers[$shipper_id])) {
            $best = $shippers[$shipper_id];
        } else {
            $best = Shipper::getBestRate($shipping['units']);
        }
        $T->set_block('form', 'shipMethodSelect', 'row');

        $ship_rates = array();
        foreach ($shippers as $shipper) {
            $sel = $shipper->id == $best->id ? 'selected="selected"' : '';
            $s_amt = $shipper->best_rate + $shipping['amount'];
            $rate = array(
                'amount'    => (string)Currency::getInstance()->FormatValue($s_amt),
                'total'     => (string)Currency::getInstance()->FormatValue($this->subtotal + $s_amt),
            );
            $ship_rates[$shipper->id] = $rate;
            $T->set_var(array(
                'method_sel'    => $sel,
                'method_name'   => $shipper->name,
                'method_rate'   => Currency::getInstance()->Format($s_amt),
                'method_id'     => $shipper->id,
                'order_id'      => $this->order_id,
                'multi'         => count($shippers) > 1 ? true : false,
            ) );
            $T->parse('row', 'shipMethodSelect', true);
        }
        $T->set_var('shipper_json', json_encode($ship_rates));
        $T->parse('output', 'form');
        return  $T->finish($T->get_var('output'));
    }


    /**
     * Set all the billing and shipping address vars into the template.
     *
     * @param   object  $T      Template object
     */
    private function _setAddressTemplate(&$T)
    {
        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (Workflow::getAll($this) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $this->$fldname);
            }
        }
    }


    /**
     * Determine if an order is final, that is, cannot be updated or deleted.
     *
     * @param   string  $status     Status to check, if not the current status
     * @return  boolean     True if order is final, False if still a cart or pending
     */
    public function isFinal($status = NULL)
    {
        if ($status === NULL) {     // checking current status
            $status = $this->status;
        }
        return !in_array($status, self::$nonfinal_statuses);
    }


    /**
     * Convert from one currency to another.
     *
     * @param   string  $new    New currency, configured currency by default
     * @param   string  $old    Original currency, $this->currency by default
     * @return  boolean     True on success, False on error
     */
    public function convertCurrency($new ='', $old='')
    {
        global $_SHOP_CONF;

        if ($new == '') $new = $_SHOP_CONF['currency'];
        if ($old == '') $old = $this->currency;
        // If already set, return OK. Nothing to do.
        if ($new == $old) return true;

        // Update each item's pricing
        foreach ($this->items as $Item) {
            $Item->convertCurrency($old, $new);
        }

        // Update the currency amounts stored with the order
        foreach (array('tax', 'shipping', 'handling') as $fld) {
            $this->$fld = Currency::Convert($this->$fld, $new, $old);
        }

        // Set the order's currency code to the new value and save.
        $this->currency = $new;
        $this->Save();
        return true;
    }


    /**
     * Provide a central location to get the URL to print or view a single order.
     *
     * @param   string  $view   View type (order or print)
     * @return  string      URL to the view/print page
     */
    public function buildUrl($view)
    {
        return COM_buildUrl(SHOP_URL . "/order.php?mode=$view&id={$this->order_id}&token={$this->token}");
    }

}

?>
