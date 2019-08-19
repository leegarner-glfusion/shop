<?php
/**
 * Class to manage product attribute groups.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for product attribute groups.
 * @package shop
 */
class OptionGroup
{
    /** Property fields accessed via `__set()` and `__get()`.
     * @var array */
    var $properties;

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    var $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     * @var array */
    var $Errors = array();


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Attributeal type ID
     */
    public function __construct($id=0)
    {
        $this->properties = array();
        $this->isNew = true;

        if (is_array($id)) {
            $this->setVars($id);
        } else {
            $id = (int)$id;
            if ($id < 1) {
                // New entry, set defaults
                $this->og_id = 0;
                $this->og_og_name = 0;
                $this->og_og_orderby = '';
            } else {
                $this->og_id = $id;
                if (!$this->Read()) {
                    $this->og_id = 0;
                }
            }
        }
    }


    /**
     * Set a property's value.
     *
     * @param   string  $key    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'og_id':
        case 'og_orderby':
            // Integer values
            $this->properties[$key] = (int)$value;
            break;

        case 'og_name':
            // String values
            $this->properties[$key] = trim($value);
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
     * Get the value of a property.
     *
     * @param   string  $key    Name of property to retrieve.
     * @return  mixed           Value of property, NULL if undefined.
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
     * Get all attribute groups.
     *
     * @return  array       Array of OptionGroup objects
     */
    public static function getAll()
    {
        global $_TABLES;

        $cache_key = 'shop_opt_grp_all';
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $retval = array();
            $sql = "SELECT * FROM {$_TABLES['shop.opt_grp']}
                ORDER BY og_orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['og_id']] = new self($A);
            }
            Cache::set($cache_key, $retval, 'attributes');
        }
        return $retval;
    }


    /**
     * Get an instance of a specific attribute group.
     *
     * @param   integer $og_id  OptionGroup record ID
     * @return  object      OptionGroup object
     */
    public static function getInstance($og_id)
    {
        $grps = self::getAll();
        if (array_key_exists($og_id, $grps)) {
            return $grps[$og_id];
        } else {
            return new self;
        }
    }


    /**
     * Sets all variables to the matching values from $row.
     *
     * @param   array $A    Array of values, from DB or $_POST
     */
    public function setVars($A)
    {
        if (!is_array($A)) {
            return;
        }
        $this->og_id = $A['og_id'];
        $this->og_name = $A['og_name'];
        $this->og_orderby = $A['og_orderby'];
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Attributeal ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->og_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query(
            "SELECT * FROM {$_TABLES['shop.opt_grp']}
            WHERE og_id='$id'"
        );
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $A = DB_fetchArray($result, false);
            $this->setVars($A);
            $this->isNew = false;
            return true;
        }
    }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A      Array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A = array())
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
            // Put this field at the end of the line by default
            $this->setVars($A);
        }

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['shop.opt_grp']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.opt_grp']} SET ";
            $sql3 = " WHERE og_id={$this->og_id}";
        }

        $sql2 = "og_name = '" . DB_escapeString($this->og_name) . "',
                og_orderby='{$this->og_orderby}'";
        $sql = $sql1 . $sql2 . $sql3;

        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->isNew) {
                $this->og_id = DB_insertID();
            }
            self::reOrder();
            //Cache::delete('prod_attr_' . $this->item_id);
            self::clearCache();
            return true;
        } else {
            $this->AddError($err);
            return false;
        }
    }


    /**
     * Delete the current attrribute group record from the database.
     *
     * @param   integer $og_id    Attribute ID, empty for current object
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($og_id)
    {
        global $_TABLES;

        if ($og_id <= 0) {
            return false;
        }

        DB_delete($_TABLES['shop.prod_attr'], 'og_id', $og_id);
        DB_delete($_TABLES['shop.opt_grp'], 'og_id', $og_id);
        self::cleaCache();
        return true;
    }


    /**
     * Determines if the current record is valid.
     *
     * @return  boolean     True if ok, False when first test fails.
     */
    public function isValidRecord()
    {
        // Check that basic required fields are filled in
        if ($this->og_name == '') {
            return false;
        }
        return true;
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Attributeal ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = SHOP_getTemplate('opt_grp_form', 'form');
        $id = $this->og_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit_og'] . ': ' . $this->og_name);
        } else {
            $retval = COM_startBlock($LANG_SHOP['new_og']);
        }

        $orderby_sel = $this->og_orderby - 10;
        $T->set_var(array(
            'og_id'         => $id,
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('opt_grp_form', $_CONF['language']),
            'og_name'       => $this->og_name,
            //'og_orderby'    => $this->og_orderby,
            'orderby_opts'  => COM_optionList($_TABLES['shop.opt_grp'], 'og_orderby,og_name', $orderby_sel, 0),
            'orderby_last'  => $this->isNew ? 'selected="selected"' : '',
        ) );

        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }   // function Edit()


    /**
     * Add an error message to the Errors array.
     * Also could be used to log certain errors or perform other actions.
     *
     * @param  string  $msg    Error message to append
     */
    public function AddError($msg)
    {
        $this->Errors[] = $msg;
    }


    /**
     * Reorder all attribute items with the same product ID and attribute og_name.
     */
    private function reOrder()
    {
        global $_TABLES;

        $sql = "SELECT og_id, og_orderby
                FROM {$_TABLES['shop.opt_grp']}
                ORDER BY og_orderby, og_name ASC;";
        $result = DB_query($sql);

        $order = 10;        // First og_orderby value
        $stepNumber = 10;   // Increment amount
        $changed = false;   // Assume no changes
        while ($A = DB_fetchArray($result, false)) {
            SHOP_log("checking item {$A['og_id']}", SHOP_LOG_DEBUG);
                SHOP_log("Order by is {$A['og_orderby']}, should be $order", SHOP_LOG_DEBUG);
            if ($A['og_orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES['shop.opt_grp']}
                    SET og_orderby = '$order'
                    WHERE og_id = '{$A['og_id']}'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
        if ($changed) {
            self::clearCache();
        }
    }


    /**
     * Move an attribute group up or down the admin list.
     *
     * @param   string  $where  Direction to move (up or down)
     */
    public function moveRow($where)
    {
        global $_TABLES;

        switch ($where) {
        case 'up':
            $oper = '-';
            break;
        case 'down':
            $oper = '+';
            break;
        default:
            $oper = '';
            break;
        }

        if (!empty($oper)) {
            $sql = "UPDATE {$_TABLES['shop.opt_grp']}
                    SET og_orderby = og_orderby $oper 11
                    WHERE og_id = '{$this->og_id}'";
            //echo $sql;die;
            DB_query($sql);
            $this->reOrder();
        }
    }


    /**
     * Admin List View.
     *
     * @param   integer $cat_id     Optional attribute ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT * FROM {$_TABLES['shop.opt_grp']}";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'og_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'og_name',
                'sort' => true,
            ),
            array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'og_orderby',
                'align' => 'center',
                'sort'  => true,
            ),
            /*array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),*/
        );

        $defsort_arr = array(
            'field' => 'og_orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_og'],
            SHOP_ADMIN_URL . '/index.php?og_edit=0',
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        $query_arr = array(
            'table' => 'shop.opt_grp',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );
        $options = array('chkdelete' => true, 'chkfield' => 'og_id');
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_og_list',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );

        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the attribute list.
     *
     * @param   string  $fieldog_name  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldog_name, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldog_name) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?og_edit=x&amp;og_id={$A['og_id']}"
            );
            break;

        case 'og_orderby':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-arrow-up"></i>',
                SHOP_ADMIN_URL . '/index.php?agmove=up&id=' . $A['og_id']
            ) .
            COM_createLink('<i class="uk-icon uk-icon-arrow-down"></i>',
                SHOP_ADMIN_URL . '/index.php?og_move=down&id=' . $A['og_id']
            );
            break;

        case 'delete':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-trash uk-text-danger"></i>',
                SHOP_ADMIN_URL. '/index.php?og_del=x&amp;og_id=' . $A['og_id'],
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


    /**
     * Clear cache entries related to attributes.
     */
    public static function clearCache()
    {
        Cache::clear('products');
        Cache::clear('attributes');
    }


    /**
     * Get the first OptionGroup object in the DB.
     * Used to determine the first element in selection lists.
     *
     * @uses    self::getAll()
     * @return  object      AttibuteGroup object.
     */
    public static function getFirst()
    {
        global $_TABLES;

        $grps = self::getAll();
        reset($grps);
        $retval = array_pop($grps);
        return $retval;
    }

}

?>
