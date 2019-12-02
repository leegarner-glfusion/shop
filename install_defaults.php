<?php
/**
 * Configuration Defaults Shop plugin for glFusion.
 * Based on the gl-shop Plugin for Geeklog CMS.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (C) 2005-2006 Vincent Furia <vinny01@users.sourceforge.net>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 *
 */

// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

// Check if the Paypal plugin is installed and has service functions defined.
// If so, disable Shop service functions.
if (function_exists('plugin_getCurrency_paypal')) {
    $enable_svc_funcs = 0;
} else {
    $enable_svc_funcs = 1;
}

/** @var global config data */
global $shopConfigData;
$shopConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'admin_email_addr',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'currency',
        'default_value' => 'USD',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'anon_buy',
        'default_value' => true,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'menuitem',
        'default_value' => true,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'order',
        'default_value' => 'name',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 5,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'prod_per_page',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'catalog_columns',
        'default_value' => '5',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'show_plugins',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 90,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_comments',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 100,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_ratings',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 110,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'anon_can_rate',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 120,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'displayblocks',
        'default_value' => '3',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 13,
        'sort' => 130,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'centerblock',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 140,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ena_cart',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 150,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'weight_unit',
        'default_value' => 'lbs',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 15,
        'sort' => 160,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tc_link',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 170,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'days_purge_cart',
        'default_value' => '14',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 180,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'days_purge_pending',
        'default_value' => '180',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 190,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'product_tpl_ver',
        'default_value' => 'v2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 200,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'list_tpl_ver',
        'default_value' => 'v2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 210,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'hp_layout',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 19,
        'sort' => 215,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'enable_svc_funcs',
        'default_value' => $enable_svc_funcs,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 220,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'shop_enabled',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 230,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'ipn_url',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 240,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'use_sku',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 250,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'adm_def_view',
        'default_value' => 'products',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 20,
        'sort' => 260,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_paths',               // Paths fieldset
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'max_thumb_size',
        'default_value' => '250',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'max_file_size',
        'default_value' => '8',     // MB
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_prod_defaults',   // Products Defaults and Views
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_prod_type',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_enabled',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_taxable',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_featured',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_expiration',
        'default_value' => '3',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_track_onhand',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 2,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'def_oversell',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 16,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_blocks',   // Plugin block control
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'blk_random_limit',
        'default_value' => '1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'blk_featured_limit',
        'default_value' => '1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'blk_popular_limit',
        'default_value' => '1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'cache_max_age',
        'default_value' => '900',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_debug',   // Debugging settings
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 40,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'log_level',
        'default_value' => '200',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 40,
        'selection_array' => 18,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_addresses',   // Address Collection
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'get_street',
        'default_value' => '2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 14,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'get_city',
        'default_value' => '2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 14,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'get_state',
        'default_value' => '2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 14,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'get_country',
        'default_value' => '2',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 14,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'get_postal',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 50,
        'selection_array' => 14,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    // Feeds FS
    array(
        'name' => 'fs_feeds',
        'default_value' => 0,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 60,
        'selection_array' => 0,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'feed_facebook',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 60,
        'selection_array' => 2,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),

    // Shop Information SG
    array(
        'name' => 'sg_shop',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_shop',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'company',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'address1',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'address2',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'city',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'state',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'zip',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'country',
        'default_value' => 'US',
        'type' => 'select',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'remit_to',       // Remittance/Support contact
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'shop_phone',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 90,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'shop_email',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 100,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_rate',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 110,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'purge_sale_prices',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 120,
        'set' => true,
        'group' => 'shop',
    ),
    // Gift Card SG
    array(
        'name' => 'sg_gc',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_gc',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_enabled',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_exp_days',
        'default_value' => '365',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),

    array(
        'name' => 'fs_gc_format',       // Gift Card Formatting
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_letters',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 17,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_numbers',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_symbols',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_prefix',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_suffix',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_length',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'gc_mask',
        'default_value' => 'XXXX-XXXX-XXXX-XXXX',
        'type' => 'text',
        'subgroup' => 20,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'shop',
    ),

    // Sales Tax Processing SG
    array(
        'name' => 'sg_tax',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 30,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'fs_tax',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_provider',
        'default_value' => 'internal',
        'type' => 'select',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 21,
        'sort' => 10,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_test_mode',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_avatax_account',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'shop',
    ),
    array(
        'name' => 'tax_avatax_key',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 30,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'shop',
    ),
);


/**
 * Initialize Shop plugin configuration
 *
 * No longer imports a pre-0.4.0 config.php. Only configuration items shown
 * above are imported.
 *
 * @param   integer $group_id   Admin Group ID (not used)
 * @return  boolean             True
 */
function plugin_initconfig_shop($group_id = 0)
{
    global $shopConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('shop')) {
        USES_lib_install();
        foreach ($shopConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    } else {
        COM_errorLog('initconfig error: Shop config group already exists');
    }
    return true;
}

?>
