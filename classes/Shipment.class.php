<?php
/**
 * Class to manage order shipments
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

namespace Shop;

/**
 * Class for order line items.
 * @package shop
 */
class Shipment
{
    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Order object related to this shipment.
     * var object */
    private $Order = NULL;

    /** ShipmentItem objects included in this shipment.
     * @var array */
    public $Items = NULL;

    /** Packages (tracking info) included in this shipment.
     * @var array */
    public $Packages = NULL;

    /** Fields for a Shipment record.
     * @var array */
    private static $fields = array(
        'shipment_id', 'order_id', 'ts',
        'tracking_num', 'comment',
    );


    /**
     * Constructor.
     * Initializes the order item.
     *
     * @param   integer|array   $shipment_id  Record ID or array
     */
    public function __construct($shipment_id = 0)
    {
        if (is_numeric($shipment_id) && $shipment_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($shipment_id);
            if (!$status) {
                $this->shipment_id = 0;
            }
        } elseif (is_array($shipment_id)) {
            // Got a shipment record, just set the variables
            $this->setVars($shipment_id);
        }
    }


    /**
     * Create a new shipment.
     * Instantiates a Shipment object and sets the order ID.
     *
     * @param   string  $order_id   ID of related order
     * @return  object      Shipment object with order ID set
     */
    public static function Create($order_id)
    {
        $Obj = new self();
        $Obj->order_id = $order_id;
        $Order = $Obj->getOrder();
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read($rec_id)
    {
        global $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT * FROM {$_TABLES['shop.shipments']}
                WHERE shipment_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
            $this->getItems();
            $this->getPackages();
            return true;
        } else {
            $this->shipment_id = 0;
            return false;
        }
    }


    /**
     * Set the object variables from an array.
     *
     * @param   array   $A      Array of values
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars($A)
    {
        if (!is_array($A)) return false;

        foreach (self::$fields as $field) {
            if (isset($A[$field])) {
                $this->$field = $A[$field];
            }
        }
        return true;
    }


    /**
     * Setter function.
     *
     * @param   string  $key    Name of property to set
     * @param   mixed   $value  Value to set for property
     */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'shipment_id':
        case 'shipper_id':
        case 'ts':
            $this->properties[$key] = (int)$value;
            break;
        default:
            $this->properties[$key] = trim($value);
            break;
        }
    }


    /**
     * Getter function.
     *
     * @param   string  $key    Property to retrieve
     * @return  mixed           Value of property, NULL if undefined
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
     * Get a date object representing this shipment's timestamp.
     *
     * @return  object      Date object
     */
    public function getDate()
    {
        global $_CONF;

        static $Dt = NULL;
        if ($Dt === NULL) {
            $Dt = new \Date($this->ts, $_CONF['timezone']);
        }
        return $Dt;
    }


    /**
     * Get all the shipments related to a specific order.
     *
     * @param   string  $order_id   ID of order
     * @return  array       Array of Shipment objects
     */
    public static function getByOrder($order_id)
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['shop.shipments']}
            WHERE order_id = '" . DB_escapeString($order_id) . "'
            ORDER BY ts ASC";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
     * Get the order object related to this shipment.
     *
     * @return  object      Order object
     */
    public function getOrder()
    {
        if ($this->Order === NULL) {
            $this->Order = Order::getInstance($this->order_id);
        }
        return $this->Order;
    }


    /**
     * Get the Items shipped in this shipment.
     */
    public function getItems()
    {
        if ($this->Items === NULL) {
            $this->Items = ShipmentItem::getByShipment($this->shipment_id);
        }
        return $this->Items;
    }


    /**
     * Get the packages included in this shipment.
     */
    public function getPackages()
    {
        if ($this->Packages === NULL) {
            $this->Packages = ShipmentPackage::getByShipment($this->shipment_id);
        }
        return $this->Packages;
    }


    /**
     * Save a shipment to the database.
     * `$form` is expected to contain shipment info, including an array
     * named `orderitem` with orderitem IDs and quantities for each.
     *
     * @param   array   $form   Optional array of data to save ($_POST)
     * @return  boolean     True on success, False on DB error
     */
    public function Save($form = NULL)
    {
        global $_TABLES;

        if (is_array($form)) {
            // This sets the base info, ShipmentItems are created after saving
            // the shipment.
            $this->setVars($form);
        }

        if (!$this->_isValidRecord($form)) {
            return false;
        }

        if ($this->shipment_id > 0) {
            // New shipment
            $sql1 = "UPDATE {$_TABLES['shop.shipments']} ";
            $sql3 = " WHERE shipment_id = '{$this->shipment_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.shipments']} ";
            $sql3 = '';
        }
        $shipping_addr = $this->getOrder()->getAddress('shipping');
        $sql2 = "SET 
            order_id = '" . DB_escapeString($this->order_id) . "',
            ts = UNIX_TIMESTAMP(),
            comment = '" . DB_escapeString($this->comment) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->shipment_id == 0) {
                $this->shipment_id = DB_insertID();
                $ord_status = 'shipped';    // assume all shipped
                foreach ($form['ship_qty'] as $oi_id=>$qty) {
                    // Double-check that only physical products are being shipped
                    $qty = (float)$qty;
                    if ($qty > 0) {
                        $SI = ShipmentItem::Create($this->shipment_id, $oi_id, $qty);
                        $SI->Save();
                    } else {
                        // This is an empty quantity, so there are some items
                        // still to ship
                        $ord_status = 'processing';
                    }
                }
                $this->getOrder()->updateStatus($ord_status);
            }
            if (isset($form['tracking']) && is_array($form['tracking'])) {
                foreach ($form['tracking'] as $id=>$data) {
                    $this->addPackage(
                        $data['shipper_id'],
                        $data['shipper_name'],
                        $data['tracking_num']
                    );
                }
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check that required variables are set prior to saving the shipment.
     * - Checks that there is some quantity being shipped, for new shipments.
     * - Checks that there's at least one physical item being shipped.
     * Returns True when updating existing shipments.
     *
     * @param   array   $form   Array of form fields ($_POST)
     * @return  boolean     True if the record is OK to save, False if not
     */
    private function _isValidRecord(&$form)
    {
        // Check that only physical items are being shipped.
        // Remove any download or virtual items. If the resulting dataset has
        // no items, return false.
        foreach ($form['ship_qty'] as $oi_id=>$qty) {
            if (!OrderItem::getInstance($oi_id)->getProduct()->isPhysical()) {
                unset($form['ship_qty'][$oi_id]);
            }
            if (empty($form['ship_qty'])) {
                return false;
            }
        }

        // Check that the shipping quantity field is present, for new shipments.
        if ($this->shipment_id == 0) {
            if (!isset($form['ship_qty']) || !is_array($form['ship_qty'])) {
                return false;
            }

            // Check that at least one item is being shipped
            $total_qty = 0;
            foreach ($form['ship_qty'] as $oi_id=>$qty) {
                if ($qty == 0) {
                    unset($form['ship_qty'][$oi_id]);
                }
            }
        }

        // All check passed OK
        return true;
    }


    /**
     * Add a package with tracking info to this shipment.
     * A shipper name or tracking number must be specified.
     *
     * @param   string  $shipper_id     Shipper ID, if a saved shipper is used
     * @param   string  $shipper_info   Optional override for shipper name
     * @param   string  $tracking_num   Tracking number
     */
    public function addPackage($shipper_id, $shipper_info, $tracking_num)
    {
        if ($shipper_info == '') {
            if ($shipper_id > 0) {
                $shipper_info = Shipper::getInstance($shipper_id)->getName();
            }
        }

        // Have to have at least a shipper name or tracking number, otherwise
        // nothing to save
        if (empty($hipper_info) && empty($tracking_num)) {
            return false;
        }

        $Pkg = new ShipmentPackage();
        $Pkg->shipment_id = $this->shipment_id;
        $Pkg->shipper_id = $shipper_id;
        $Pkg->shipper_info = $shipper_info;
        $Pkg->tracking_num = $tracking_num;
        $Pkg->Save();
    }


    /**
     * Delete a shipment record. Also deletes related packages.
     *
     * @return  boolean     True on success, False on failure
     */
    public function Delete()
    {
        global $_TABLES;

        if ($this->shipment_id > 0) {
            DB_delete($_TABLES['shop.shipment_packages'], 'shipment_id', $this->shipment_id);
            DB_delete($_TABLES['shop.shipment_items'], 'shipment_id', $this->shipment_id);
            DB_delete($_TABLES['shop.shipments'], 'shipment_id', $this->shipment_id);
            $this->getOrder()->updateStatus('processing', true, false);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Shipment listing
     *
     * @return  string      HTML for the shipment listing
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;

        $sql = "SELECT shp.*, ord.billto_name, ord.shipto_name
            FROM {$_TABLES['shop.shipments']} shp
            LEFT JOIN {$_TABLES['shop.orders']} ord
                ON ord.order_id = shp.order_id";

        $header_arr = array(
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => '',
                'field' => 'action',
                'sort'  => false,
            ),
            array(
                'text'  => 'ID',
                'field' => 'shipment_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['datetime'],
                'field' => 'ts',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['order'],
                'field' => 'order_id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['customer'],
                'field' => 'customer',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => 'false',
                'align' => 'center',
            ),
        );

        $extra = array();

        $defsort_arr = array(
            'field' => 'ts',
            'direction' => 'DESC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.shopments',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/index.php?shipments=x',
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_shipments',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extra, '', ''
        );

        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the options admin list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra information passed in verbatim
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array('title'=>$LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/index.php?editshipment={$A['shipment_id']}"
            );
            break;

        case 'customer':
            $retval = empty($A['shipto_name']) ? $A['billto_name'] : $A['shipto_name'];
            $retval = htmlspecialchars($retval);
            break;

        case 'ts':
            $D = new \Date($fieldvalue, $_CONF['timezone']);
            $retval = $D->toMySQL(true);
            break;

        case 'order_id':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/index.php?order=' . urlencode($fieldvalue)
            );
            break;

        case 'action':
            $retval = COM_createLink(
                Icon::getHTML('list'),
                SHOP_ADMIN_URL . '/index.php?shipment_pl=' . $A['shipment_id'],
                array(
                    'target'    => '_blank',
                    'class'     => 'tooltip',
                    'title'     => $LANG_SHOP['packinglist'],
                )
            );
            break;

        case 'delete':
            $retval = COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?del_shipment=' . $A['shipment_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }

}

?>
