<?php
/**
 * DataBase Object trait to provide common functions for other classes.
 * Included wrapper for DB_* functions in case the schema is not current.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Traits;


/**
 * Utility trait containing common database operations.
 * Classes using this trait must define at least the `$TABLE` variable.
 * @package shop
 */
trait DBO
{
    /** Key field name. Can be overridden by defining `$F_ID`.
     * @var string */
    private static $_F_ID = 'id';

    /** Order field name. Can be overridden by defining `$F_ORDERBY`.
     * @var string */
    private static $_F_ORDERBY = 'orderby';


    /**
     * Move a record up or down the admin list.
     *
     * @param   string  $id     ID field value
     * @param   string  $where  Direction to move (up or down)
     */
    public static function moveRow($id, $where)
    {
        global $_TABLES;

        // Do nothing if the derived class did not specify a table key.
        if (static::$TABLE == '') {
            return;
        }

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

        $f_orderby = isset(static::$F_ORDERBY) ? static::$F_ORDERBY : static::$_F_ORDERBY;
        $f_id = isset(static::$F_ID) ? static::$F_ID : static::$_F_ID;
        if (!empty($oper)) {
            $sql = "UPDATE {$_TABLES[static::$TABLE]}
                    SET $f_orderby = $f_orderby $oper 11
                    WHERE $f_id = '" . DB_escapeString($id) . "'";
            DB_query($sql);
            self::ReOrder();
        }
    }


    /**
     * Reorder all records.
     */
    public static function ReOrder()
    {
        global $_TABLES;

        // Do nothing if the derived class did not specify a table key.
        if (!isset(static::$TABLE)) {
            return;
        }

        $f_orderby = isset(static::$F_ORDERBY) ? static::$F_ORDERBY : static::$_F_ORDERBY;
        $f_id = isset(static::$F_ID) ? static::$F_ID : static::$_F_ID;
        $table = $_TABLES[static::$TABLE];
        $sql = "SELECT $f_id, $f_orderby
                FROM $table
                ORDER BY $f_orderby ASC;";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        while ($A = DB_fetchArray($result, false)) {
            if ($A[$f_orderby] != $order) {  // only update incorrect ones
                $sql = "UPDATE $table
                    SET $f_orderby = '$order'
                    WHERE $f_id = '" . DB_escapeString($A[$f_id]) . "'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID of record to modify
     * @return  integer     New value, or old value upon failure
     */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // Do nothing if the derived class did not specify a table key.
        if (!isset(static::$TABLE)) {
            return $oldvalue;
        }

        $f_id = isset(static::$F_ID) ? static::$F_ID : static::$_F_ID;
        $id = DB_escapeString($id);

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 1 ? 1 : 0;
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES[static::$TABLE]}
                SET $varname = $newvalue
                WHERE $f_id = '$id'";
        // Ignore SQL errors since varname is indeterminate
        DB_query($sql, 1);
        if (DB_error()) {
            SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
            return $oldvalue;
        } else {
            return $newvalue;
        }
    }


    /**
     * Public-facing function to toggle some field from oldvalue.
     * Used for objects that don't have their own Toggle function and don't
     * need any other action taken, like clearing caches.
     *
     * @param   integer $oldval Original value to be changed
     * @param   string  $field  Name of field to change
     * @param   mixed   $id     Record ID
     * @return  integer     New value on success, Old value on error
     */
    public static function Toggle($oldval, $field, $id)
    {
        return self::_toggle($oldval, $field, $id);
    }


    /**
     * Convert a UTC-based datetime string or timestamp value to a Date object.
     * Used to read UTC datetimes from MySQL and apply the local timezone.
     *
     * @param   string|integer  $utc    UTC-based datetime string or timestamp
     * @param   string  $tz         Timezone string, NULL to use UTC
     * @return  object      Date object with the date/time and timezone set
     */
    private static function dateFromValue($utc, $tz=NULL)
    {
        global $_CONF;

        if ($tz === NULL) {
            $tz = $_CONF['timezone'];
        }
        // If $utc is a string will be assumed to be a local time, if the TZ
        // is passed to the contructor. Adding the TZ later works for both
        // strings and timestamp values.
        $dt = new \Date($utc);
        $dt->setTimeZone(new \DateTimeZone($tz));
        return $dt;
    }


    /**
     * Wrapper for `DB_query()` to check that the plugin schema is current.
     * All queries are executed with `ignore_errors` set to true. Errors
     * are logged and never interrupt the application.
     *
     * @param   string  $sql    Query to execute
     * @param   boolean $ig_err Ignore errors (not used)
     * @return  mixed       DB query result, or false on error
     */
    private static function dbQuery($sql, $ig_err = 1)
    {
        $res = false;
        if (SHOP_isMinVersion()) {
            $res = DB_query($sql, 1);
            if (!$res) {
                SHOP_log(
                    __CLASS__ . '::' . __FUNCTION__ .
                    "SQL error: $sql"
                );
            }
        }
        return $res;
    }


    /**
     * Wrapper for `DB_getItem()` that first checks that the schema is current.
     *
     * @param   string  $table  DB Table name
     * @param   string  $key    Field name to retrieve
     * @param   string  $where  SQL where clause
     * @return  mixed       Item value or False if schema not current
     */
    private static function dbGetItem($table, $key, $where='')
    {
        if (SHOP_isMinVersion()) {
            return DB_getItem($table, $key, $where);
        } else {
            return false;
        }
    }


    /**
     * Wrapper for `DB_delete()` that first checks that the schema is current.
     *
     * @param   string  $table  DB table name
     * @param   string  $key    Field name
     * @param   string  $value  Field value
     * @return  mixed       Results from DB_delete() or false if not current
     */
    private static function dbDelete($table, $key, $value)
    {
        if (SHOP_isMinVersion()) {
            return DB_delete($table, $key, $value);
        } else {
            return false;
        }
    }


    /**
     * Wrapper for `DB_count()` that first checks that the schema is current.
     *
     * @param   string  $table  DB table name
     * @param   string  $key    Optional field name
     * @param   string  $value  Optional field value
     * @return  mixed       Results from DB_count() or false if not current
     */
    private static function dbCount($table, $key=NULL, $where=NULL)
    {
        if (SHOP_isMinVersion()) {
            return DB_count($table, $key, $where);
        } else {
            return false;
        }
    }

}
