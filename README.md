# Shop
Shopping plugin for glFusion. Supports multiple payment gateways.

This plugin provides a product catalog and shopping cart for physical
and virtual products. The following payment gateways are supported:
- PayPal
- Authorize.Net
- Square

You must sign up with the payment providers and enter your keys in the
gateway configuration. You should also sign up for a developer or
sandbox account for testing.

If you use the Bad Behavior plugin, be sure that you whitelist your Shop IPN
URL (`shop/ipn/*provider*_ipn.php`). Bad Behavior may otherwise block IPN messages from
your gateway provider.

This version of the Shop plugin requires at least version 1.0.7 of the lgLib plugin for supporting functions.

## Installation
Installation is accomplished by using the glFusion automated plugin installer.

If you have the Paypal plugin installed, version 0.6.1 or later, then data from that plugin is automatically
transferred into the Shop plugin.

If you have an earlier version installed, you *must* either disable the Paypal plugin or update it to v0.6.1+.
There will be function name conflicts if you don't do this.

## Plugin APIs
Plugins may leverage this plugin to process payments and have their products included in the catalog.
Functions are called via `LGLIB_invokeService()`, which is similar to `PLG_invokeService()` for web services.
Arguments are passed in an array, an "output" variable receives the output, and the return is a standard `PLG_RET_*` value.

### `service_getproductinfo_<plugin_name>`
Gets general information about the product for inclusion in the catalog or to determine pricing when processing an order.
```
$args = array(
    // Item ID components
    'item_id' => array(item_id, sub_item1, sub_item2),
    // Item modifiers. May be periodically updated
    'mods'    => array('uid' => current user ID),
);

$output = array(
    'product_id'        => implode(':', $args['item_id'],
    'name'              => 'Product Name or SKU',
    'short_description' => 'Short Product Description',
    'price'             => Unit price
    'override_price' => 1,      // set if the payment price will be accepted as full payment
    'fixed_q'       => 0,     // Optional, 0 = buyer enters quanty, >1 means only that number can be purchased
);
```

### `service_handlePurchase_<plugin_name>`
Handles the purchase of the item
```
$args = array(
    'item'  => array(
        'item_id' => $Item->product_id, // Product ID as a string (item:subitem1:subitem2)
        'quantity' => $Item->quantity,  // Quantity
        'name' => $Item->item_name,     // Item name supplied by IPN
        'price' => $Item->price,        // Unit price determined from getproductinfo()
        'paid' => $Item->paid,          // Total amount paid for the line item
    ),
    'ipn_data'  => $ipn_data,   // Complete IPN information array
    'order' => $Order,      // Pass the order object, may be used in the future
 );

$output = array(        // Note: currently not used for plugin items
    'name' => $item['name'],                // Product Name
    'short_description' => $item['name'],   // Short Description
    'price' => (float)$item['price'],   // Unit price
    'expiration' => NULL,       // expiration, for downloads
    'download' => 0,            // 1 if this is a downloadable product
    'file' => '',               // download file
);
```

### `service_addCartItem_shop()`
This is a function provided by the Shop plugin to allow other plugins to add their items to the shopping cart.
```
$args = array(
    'item_id'   => Item number string, including plugin name (plugin:item_id:sub1:sub2),
    'quantity'  => Quantity,
    'item_name' => Item Name or SKU,
    'price'     => Unit Price,
    'short_description' => Item Description
    'options'   => Array of product options
    'extras'    => Array of product extras (comments, custom strings)
    'override'  => Set to force the price to the supplied value
    'uid'       => User ID, default Anonymous
    'unique'    => Set if only one of these items should be added to the cart
    'update'    => Array of fields that may be updated if 'unique' is set. e.g. New price
);

$output is not set
```

### `service_formatAmount_shop()`
Get a currency amount formatted based on the default currency.
```
$args = array(
    'amount' => Floating-point amount
);
//or//
$args = amount

$ouptut contains the formatted string
```

## Functions Provided by Plugins
These service functions should be provided by plugins wishing to leveraget the Shop plugin.

### `service_getproducts_piname`
Returns an array of product information to be included in the catalog display.
```
$args = not used
$output = array(
    array(
        'id'        => pi_name . ':' . item_id (. '|' . item_options),
        'item_id'   => item ID, without plugin name or options,
        'name'      => item name or SKU,
        'short_description' => One-line item description,
        'description' => Full item description,
        'price'     => Unit price,
        'buttons' => array(
            button_type => button_contents (see plugin_genButton_shop()),
        ),
        'url'       => URL to item detail page, or...
        'have_detail_svc' => True to wrap the detail detail page in the catalog format (see plugin_getDetailPage_piname()),
    ),
    array(
        ...
    ),
);
```

### `service_getDetailPage_piname`
Returns the product detail page HTML to be displayed by the Shop plugin
```
$args = array(
    'item_id' => piname . ':' . item_id ( . '|' . item_options)
);
$output = HTML for product detail
```

## `service_handlePurchase_piname`
Handle the purchase of a plugin item
```
$args = array(
    'item' => array(
        'item_id' => piname . ':' . item_id ( . '|' . item_options)
    ),
    'ipn_data' => array(
        // complete IPN data array
    ),
);
$output = array(
    'product_id' => Full item id,
    'name'      => Product name or SKU,
    'short_description' => One-line product description,
    'description' => Full product description,
    'price'     => Unit price,
    'expiration' => Expiration date for downloads,
    'download'  => 1 if downloadable, else 0,
    'file'      => Filename for downloadable product,
);
```

### `service_productinfo_piname`
Gets the basic product information to populate the PluginProduct object
```
$args = array(
    'item_id' => Array of item ID components (item_number, option1, option2, etc.)
);
$output = array(
    'product_id' => Full item id,
    'name'      => Product name or SKU,
    'short_description' => One-line product description,
    'description' => Full product description,
    'price'     => Unit price,
    'taxable'   => 1 if the product is taxable, else 0,
    'override_price' => 1 if the price is set in the link, 0 to get from the product,
    'fixed_q' => 0 if the buyer can set the quantty, > 0 for a fixed quantity allowed,
);
```
