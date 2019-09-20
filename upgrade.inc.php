<?php
/**
 * Upgrade routines for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

global $_CONF, $_SHOP_CONF;

/** Include the table creation strings */
require_once __DIR__ . "/sql/mysql_install.php";

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True for development update, ignore errors
 * @return  boolean     True on success, False on failure
 */
function SHOP_do_upgrade($dvlp = false)
{
    global $_TABLES, $_CONF, $_SHOP_CONF, $shopConfigData, $SHOP_UPGRADE, $_PLUGIN_INFO, $_DB_name;

    $pi_name = $_SHOP_CONF['pi_name'];
    if (isset($_PLUGIN_INFO[$pi_name])) {
        $current_ver = $_PLUGIN_INFO[$pi_name]['pi_version'];
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_shop();

    if (!COM_checkVersion($current_ver, '0.7.1')) {
        $current_ver = '0.7.1';
        // See if the shipper_id column is already in place. If not then
        // the shipper info will be moved from the info array to the new column
        // after it is created.
        $set_shippers = _SHOPtableHasColumn('shop.orders', 'shipper_id') ? false : true;
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if ($set_shippers) {
            // Need to copy the shipper_id value from the info section to the
            // new DB field.
            $sql = "SELECT order_id, info FROM {$_TABLES['shop.orders']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $info = @unserialize($A['info']);
                if ($info !== false && isset($info['shipper_id'])) {
                    $shipper_id = (int)$info['shipper_id'];
                    unset($info['shipper_id']);
                    unset($info['shipper_name']);
                    $info = @serialize($info);
                    $sql = "UPDATE {$_TABLES['shop.orders']} SET
                            shipper_id = $shipper_id,
                            info = '" . DB_escapeString($info) . "'
                        WHERE order_id = '" . DB_escapeString($A['order_id']) ."'";
                    DB_query($sql);
                }
            }
        }
        if (!SHOP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.0')) {
        $current_ver = '1.0.0';
        if (!DB_checkTableExists('shop.attr_grp')) {
            // Initial populate of the new attribute group table
            // The table won't exist yet, these statememts get appended
            // to the upgrade SQL.
            $SHOP_UPGRADE[$current_ver][] = "INSERT INTO {$_TABLES['shop.attr_grp']} (ag_name) (SELECT DISTINCT attr_name FROM {$_TABLES['shop.prod_attr']})";
            $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.prod_attr']} AS pa INNER JOIN (SELECT ag_id,ag_name FROM {$_TABLES['shop.attr_grp']}) AS ag ON pa.attr_name=ag.ag_name SET pa.ag_id = ag.ag_id";
        }
        // This has to be done after updating the attribute group above
        $SHOP_UPGRADE[$current_ver][] = "ALTER {$_TABLES['shop.prod_attr']} DROP attr_name";

        if (_SHOPcolumnType('shop.sales', 'start') != 'datetime') {
            $tz_offset = $_CONF['_now']->format('P', true);
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} ADD st_tmp datetime after `start`";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} ADD end_tmp datetime after `end`";
            $SHOP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['shop.sales']} SET
                st_tmp = convert_tz(from_unixtime(start), @@session.time_zone, '$tz_offset'),
                end_tmp = convert_tz(from_unixtime(end), @@session.time_zone, '$tz_offset')";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} DROP start, DROP end";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} CHANGE st_tmp start datetime NOT NULL DEFAULT '1970-01-01 00:00:00'";
            $SHOP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['shop.sales']} CHANGE end_tmp end datetime NOT NULL DEFAULT '9999-12-31 23:59:59'";
        }


        // Make a note if the OrderItemOptions table exists.
        // Will use this after all the other SQL updates are done if necessary.
        $populate_oi_opts = !DB_checkTableExists('shop.oi_opts');
        if (!SHOP_do_upgrade_sql($current_ver, $dvlp)) return false;

        // Synchronize the options and custom fields from the orderitem into the
        // new ordeitem_options table. This should only be done once when the
        // oi_opts table is created. Any time after this update the required
        // source fields may be removed.
        if ($populate_oi_opts) {
            COM_errorLog("Transferring orderitem options to orderitem_options table");
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                // Transfer the option info from numbered options.
                if (!empty($A['options'])) {
                    $opt_ids = explode(',', $A['options']);
                    $Item = new Shop\OrderItem($A);
                    foreach ($opt_ids as $opt_id) {
                        $OIO = new Shop\OrderItemOption();
                        $OIO->oi_id = $A['id'];
                        $OIO->setOpt($opt_id);
                        $OIO->Save();
                    }
                }
                // Now transfer custom text fields defined in the product.
                $extras = json_decode($A['extras'], true);
                if (isset($extras['custom']) && !empty($extras['custom'])) {
                    $values = $extras['custom'];
                    $P = Shop\Product::getByID($A['product_id']);
                    $names = explode('|', $P->custom);
                    foreach($names as $id=>$name) {
                        if (!empty($values[$id])) {
                            $OIO = new Shop\OrderItemOption();
                            $OIO->oi_id = $A['id'];
                            $OIO->setOpt(0, $name, $values[$id]);
                            $OIO->Save();
                        }
                    }
                }
            }
        }

        if (!SHOP_do_set_version($current_ver)) return false;
    }

    SHOP_update_config();
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!SHOP_do_set_version($installed_ver)) return false;
        $current_ver = $installed_ver;
    }
    \Shop\Cache::clear();
    SHOP_remove_old_files();
    CTL_clearCache();   // clear cache to ensure CSS updates come through
    SHOP_log("Successfully updated the {$_SHOP_CONF['pi_display_name']} Plugin", SHOP_LOG_INFO);
    // Set a message in the session to replace the "has not been upgraded" message
    COM_setMsg("Shop Plugin has been updated to $current_ver", 'info', 1);
    return true;
}


/**
 * Actually perform any sql updates.
 * Gets the sql statements from the $UPGRADE array defined (maybe)
 * in the SQL installation file.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $ignore_error   True to ignore SQL errors.
 * @return  boolean     True on success, False on failure
 */
function SHOP_do_upgrade_sql($version, $ignore_error = false)
{
    global $_TABLES, $_SHOP_CONF, $SHOP_UPGRADE, $_DB_dbms, $_VARS;

    // If no sql statements passed in, return success
    if (!is_array($SHOP_UPGRADE[$version])) {
        return true;
    }

    if (
        $_DB_dbms == 'mysql' &&
        isset($_VARS['database_engine']) &&
        $_VARS['database_engine'] == 'InnoDB'
    ) {
        $use_innodb = true;
    } else {
        $use_innodb = false;
    }

    // Execute SQL now to perform the upgrade
    SHOP_log("--- Updating Shop to version $version", SHOP_LOG_INFO);
    foreach($SHOP_UPGRADE[$version] as $sql) {
        if ($use_innodb) {
            $sql = str_replace('MyISAM', 'InnoDB', $sql);
        }

        SHOP_log("Shop Plugin $version update: Executing SQL => $sql", SHOP_LOG_INFO);
        try {
            DB_query($sql, '1');
            if (DB_error()) {
                // check for error here for glFusion < 2.0.0
                SHOP_log('SQL Error during update', SHOP_LOG_INFO);
                if (!$ignore_error) return false;
            }
        } catch (Exception $e) {
            SHOP_log('SQL Error ' . $e->getMessage(), SHOP_LOG_INFO);
            if (!$ignore_error) return false;
        }
    }
    SHOP_log("--- Shop plugin SQL update to version $version done", SHOP_LOG_INFO);
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function SHOP_do_set_version($ver)
{
    global $_TABLES, $_SHOP_CONF, $_PLUGIN_INFO;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '{$_SHOP_CONF['gl_version']}',
            pi_homepage = '{$_SHOP_CONF['pi_url']}'
        WHERE pi_name = '{$_SHOP_CONF['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        SHOP_log("Error updating the {$_SHOP_CONF['pi_display_name']} Plugin version", SHOP_LOG_INFO);
        return false;
    } else {
        SHOP_log("{$_SHOP_CONF['pi_display_name']} version set to $ver", SHOP_LOG_INFO);
        // Set in-memory config vars to avoid tripping SHOP_isMinVersion();
        $_SHOP_CONF['pi_version'] = $ver;
        $_PLUGIN_INFO[$_SHOP_CONF['pi_name']]['pi_version'] = $ver;
        return true;
    }
}


/**
 * Update the plugin configuration
 */
function SHOP_update_config()
{
    USES_lib_install();

    require_once __DIR__ . '/install_defaults.php';
    _update_config('shop', $shopConfigData);
}


/**
 * Remove deprecated files
 * Errors in unlink() and rmdir() are ignored.
 */
function SHOP_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/shop
        __DIR__ => array(
            // 0.7.1
            'shop_functions.inc.php',
            // 1.0.0
            'classes/ProductImage.class.php',
        ),
        // public_html/shop
        $_CONF['path_html'] . 'shop' => array(
        ),
        // admin/plugins/shop
        $_CONF['path_html'] . 'admin/plugins/shop' => array(
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            @unlink("$path/$file");
        }
    }
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _SHOPtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $col_name = DB_escapeString($col_name);
    $res = DB_query("SHOW COLUMNS FROM {$_TABLES[$table]} LIKE '$col_name'");
    return DB_numRows($res) == 0 ? false : true;
}


/**
 * Get the datatype for a specific column.
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  string      Column datatype
 */
function _SHOPcolumnType($table, $col_name)
{
    global $_TABLES, $_DB_name;

    $retval = '';
    $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_schema = '{$_DB_name}'
        AND table_name = '{$_TABLES[$table]}'
        AND COLUMN_NAME = '$col_name'";
    $res = DB_query($sql,1);
    if ($res) {
        $A = DB_fetchArray($res, false);
        $retval = $A['DATA_TYPE'];
    }
    return $retval;
}

?>
