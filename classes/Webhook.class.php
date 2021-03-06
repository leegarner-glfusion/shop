<?php
/**
 * Webhook class for the Shop plugin.
 * Base class for webhooks, each webhook provider (gateway) will have its
 * own class based on this one.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use Shop\Logger\IPN as logIPN;
use Shop\Models\IPN as IPNModel;
use Shop\Models\OrderState;


/**
 * Base webhook class.
 * @package shop
 */
class Webhook
{
    const FAILURE_UNKNOWN = 0;
    const FAILURE_VERIFY = 1;
    const FAILURE_COMPLETED = 2;
    const FAILURE_UNIQUE = 3;
    const FAILURE_EMAIL = 4;
    const FAILURE_FUNDS = 5;

    /** Event type for regular payment notifications.
     * @const string */
    const EV_PAYMENT = 'payment_created';

    /** Event type for regular payment notifications.
     * @const string */
    const EV_PAYMENT_UPDATED = 'payment_updated';

    /** Standard event type for invoice payment.
     * @const string */
    const EV_INV_PAYMENT = 'invoice_paid';

    /** Standard event type for invoice creation.
     * @const string */
    const EV_INV_CREATED = 'invoice_created';

    /** Standard event type for unidentified events.
     * @const string */
    const EV_UNDEFINED = 'undefined';

    /** Raw webhook data.
     * @var array */
    protected $whData = array();

    /** Webhook Reference ID.
     * @var string */
    protected $whID = '';

    /** Webhook source, eg. gateway name.
     * @var string */
    protected $whSource = '';

    /** Event type, e.g. "payment received".
     * @var string */
    protected $whEvent = '';

    /** Order ID related to this notification.
     * @var string */
    protected $whOrderID = '';

    /** Total Payment Amount.
     * @var float */
    protected $whPmtTotal = 0;

    /** Status of webhook verification via callback.
     * @var boolean */
    protected $whVerified = 0;

    /** Headers sent with the webhook.
     * @var array */
    protected $whHeaders = array();

    /** Payment method, comment, etc. Default is gateway name.
     * @var string */
    protected $pmt_method = '';

    /** Currency object, based on order or default.
     * @var object */
    protected $Currency = NULL;

    /** Order object.
     * @var object */
    protected $Order = NULL;

    /** Gateway object.
     * @var object */
    protected $GW = NULL;

    /** Payment object.
     * @var object */
    protected $Payment = NULL;

    /** IPN Model to hold payment data in a standard layout.
     * @var object */
    protected $IPN = NULL;

    /** Payload as a JSON string.
     * @var string */
    protected $blob = '';


    /**
     * Instantiate and return a Webhook object.
     *
     * @param   string  $name   Gateway name, e.g. shop
     * @param   array   $blob   Gateway variables to be passed to the IPN
     * @return  object          IPN handler object
     */
    public static function getInstance($name, $blob='')
    {
        $cls = __NAMESPACE__ . '\\Gateways\\' . $name . '\\Webhook';
        if (class_exists($cls)) {
            return new $cls($blob);
        } else {
            SHOP_log("Webhook::getInstance() - $cls doesn't exist");
            return NULL;
        }
    }


    /**
     * Save the webhook to the database.
     */
    protected function saveToDB()
    {
        global $_TABLES;

        $sql ='';
    }


    /**
     * Set the webhook ID variable.
     *
     * @param   string  $whID   Webhook ID
     * @return  object  $this
     */
    public function setID($whID)
    {
        $this->whID = $whID;
        $this->setIPN('txn_id', $this->whID);
        return $this;
    }


    /**
     * Get the webhook ID variable.
     *
     * @return  string      Webhook ID
     */
    public function getID()
    {
        return $this->whID;
    }


    /**
     * Set the webhook source.
     *
     * @param   string  $source     Webhook source (gateway name)
     * @return  object  $this
     */
    public function setSource($source)
    {
        $this->whSource = $source;
        $this->setIPN('gw_name', $this->whSource);
        return $this;
    }


    /**
     * Get the webhook source.
     *
     * @return  string      Webhook source (gateway name)
     */
    public function getSource()
    {
        return $this->whSource;
    }


    /**
     * Set the transaction date, current date/time by default.
     *
     * @param   string|null $dt     Date/time string or null for current
     * @return  object  $this
     */
    public function setTxnDate($dt=NULL)
    {
        global $_CONF;

        if ($dt === NULL) {
            $dt = $_CONF['_now']->toMySQL(true);
        }
        $this->IPN['sql_date'] = $dt;
    }


    /**
     * Set the webhook data.
     *
     * @param   array   $data   Raw webhook data array
     * @return  object  $this
     */
    public function setData($data)
    {
        $this->whData = $data;
        return $this;
    }


    /**
     * Get the raw webhook data.
     *
     * @return  array   Webhook data
     */
    public function getData()
    {
        return $this->whData;
    }


    /**
     * Set the webhook timestamp.
     *
     * @param   integer $ts     Timestamp value
     * @return  object  $this
     */
    public function setTimestamp($ts=NULL)
    {
        global $_CONF;

        if ($ts === NULL) {
            $ts = time();
        }
        $this->whTS = $ts;
        $dt = new \Date($ts, $_CONF['timezone']);
        $this->setTxnDate($dt->toMySQL(true));
        return $this;
    }


    /**
     * Get the webhook timestamp.
     *
     * @return  integer     Timestamp value
     */
    public function getTimestamp()
    {
        return $this->whTS;
    }


    /**
     * Set the webhook event type.
     *
     * @param   string  $event      Webhook event type
     * @return  object  $this
     */
    public function setEvent($event)
    {
        $this->whEvent = $event;
        return $this;
    }


    /**
     * Get the webhook event type.
     *
     * @return  string      Event string
     */
    public function getEvent()
    {
        return $this->whEvent;
    }


    /**
     * Set the order ID related to this webhook.
     *
     * @param   string  $order_id   Order ID
     * @return  object  $this
     */
    public function setOrderID($order_id)
    {
        $this->whOrderID = $order_id;
        return $this;
    }


    /**
     * Get the order ID for this webhook.
     *
     * @return  string      Order ID
     */
    public function getOrderID()
    {
        return $this->whOrderID;
    }


    /**
     * Set the payment amount for this webhook.
     *
     * @param   float   $amount     Payment amount
     * @return  object  $this
     */
    public function setPayment($amount)
    {
        $this->whPmtTotal = (float)$amount;
        $this->setIPN('pmt_gross', $this->whPmtTotal);
        return $this;
    }


    /**
     * Get the payment amount for this webhook.
     *
     * @return  float       Payment amount
     */
    public function getPayment()
    {
        return (float)$this->whPmtTotal;
    }


    /**
     * Set the payment method, comment, etc. Default is gateway name.
     *
     * @param   string  $method     Payment method
     * @return  object  $this
     */
    public function setPmtMethod($method)
    {
        $this->pmt_method = $method;
        return $this;
    }


    /**
     * Get the payment method or comment.
     *
     * @return  string      Payment method
     */
    public function getPmtMethod()
    {
        return $this->pmt_method;
    }


    /**
     * Set the verification status after running Verify().
     *
     * @param   boolean $verified   True if verified, False if not
     * @return  object  $this
     */
    public function setVerified($verified)
    {
        $this->whVerified = $verified ? 1 : 0;
        return $this;
    }


    /**
     * Check if this webhook has been verified.
     *
     * @return  integer     1 if verified, 0 if not
     */
    public function isVerified()
    {
        return $this->whVerified ? 1 : 0;
    }


    /**
     * Record a payment via webhook in the database.
     * Creates a Payment object from the webhook data and saves it.
     *
     * @return  object      Payment object
     */
    public function recordPayment()
    {
        $Payment = new Payment();
        $Payment->setRefID($this->getID())
            ->setAmount($this->getPayment())
            ->setGateway($this->getSource())
            ->setMethod($this->getPmtMethod())
            ->setComment('Webhook ' . $this->getData()->id)
            ->setComplete(1)
            ->setStatus($this->getData()->type)
            ->setOrderID($this->getOrderID());
        return $Payment->Save();
    }


    /**
     * Log the webhook to the IPN log.
     *
     * @return  integer     Record ID returned from Logger\IPN::Write()
     */
    public function logIPN()
    {
        $ipn = new logIPN();
        $ipn->setOrderID($this->whOrderID)
            ->setTxnID($this->whID)
            ->setGateway($this->whSource)
            ->setEvent($this->whEvent)
            ->setVerified($this->isVerified())
            ->setData(json_decode($this->blob, true));
        return $ipn->Write();
    }


    /**
     * Set the header array from the received webhook headers.
     *
     * @param   array   $arr    Specified array of headers for debugging
     * @return  object  $this
     */
    public function setHeaders($arr = NULL)
    {
        $this->whHeaders = array();
        if ($arr === NULL) {
            $arr = $_SERVER;
        }
        foreach($arr as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $this->whHeaders[$header] = $value;
        }
        return $this;
    }


    /**
     * Get one or all webhook headers.
     *
     * @param   string|null $header     Header name, NULL for all
     * @return  string|array    One or all headers
     */
    public function getHeader($header=NULL)
    {
        if ($header === NULL) {
            return $this->whHeaders;
        } elseif (isset($this->whHeaders[$header])) {
            return $this->whHeaders[$header];
        } else {
            return '';
        }
    }


    /**
     * Set the payment currency object.
     *
     * @param   string  $code   Currency code, empty for site default
     * @return  object  $this
     */
    public function setCurrency($code='')
    {
        $this->Currency = Currency::getInstance(strtoupper($code));
        return $this;
    }


    /**
     * Get the payment currency object.
     *
     * @return string  Currency code
     */
    public function getCurrency()
    {
        if ($this->Currency === NULL) {
            $this->setCurrency();
        }
        return $this->Currency;
    }


    /**
     * Check that provided funds are sufficient to cover the cost of the purchase.
     *
     * @return boolean                 True for sufficient funds, False if not
     */
    protected function isSufficientFunds()
    {
        if (!$this->Order) {
            return false;
        }
        $bal_due = $this->Order->getBalanceDue();
        $Cur = $this->Order->getCurrency();
        $msg = $Cur->FormatValue($this->getPayment()) . ' received, require ' .
            $Cur->FormatValue($bal_due);
        if ($bal_due <= $this->getPayment() + .0001) {
            SHOP_log("OK: $msg", SHOP_LOG_DEBUG);
            return true;
        } else {
            SHOP_log("Insufficient Funds: $msg", SHOP_LOG_ERROR);
            return false;
        }
    }


    /**
     * Handle what to do in the event of a purchase/IPN failure.
     *
     * This method does some basic failure handling.  For anything more
     * advanced it is recommend you override this method.
     *
     * @param   integer $type   Type of failure that occurred
     * @param   string  $msg    Failure message
     */
    protected function handleFailure($type = self::FAILURE_UNKNOWN, $msg = '')
    {
        // Log the failure to glFusion's error log
        $this->Error($this->gw_id . '-IPN: ' . $msg, 1);
    }


    /**
     * Handles the item purchases.
     * The purchase should already have been validated; this function simply
     * records the purchases. Purchased files will be emailed to the
     * customer by Order::Notify().
     *
     * @return  boolean     True if processed successfully, False if not
     */
    public function handlePurchase()
    {
        if (is_null($this->Order)) {
            $this->Order = Order::getInstance($this->getOrderID());
        }
        if ($this->Order->isNew()) {
            SHOP_log("Error: Order {$this->getOrderID()} is not valid", SHOP_LOG_ERROR);
            return false;
        }

        if (
            $this->GW->okToProcess($this->Order)
            //&& !$Order->statusAtLeast(OrderState::PROCESSING)
        ) {
            $this->IPN->setUid($this->Order->getUid());
            // Handle the purchase for each order item
            $this->Order->handlePurchase($this->IPN);
        } else {
            SHOP_log('Cannot process order ' . $this->getOrderID(), SHOP_LOG_ERROR);
            SHOP_log('canprocess? ' . var_export($GW->okToProcess($this->Order),true), SHOP_LOG_DEBUG);
            SHOP_log('status ' . $this->Order->getStatus(), SHOP_LOG_DEBUG);
            return false;
        }
        return true;
    }


    /**
     * Checks that the transaction id is unique to prevent double counting.
     *
     * @return  boolean             True if unique, False otherwise
     */
    protected function isUniqueTxnId()
    {
        global $_TABLES, $_SHOP_CONF;

        if (isset($_SHOP_CONF['sys_test_ipn']) && $_SHOP_CONF['sys_test_ipn']) {
            // Special config value set only in config.php for IPN testing
            return true;
        }

        // Count IPN log records with txn_id = this one.
        $count = DB_count(
            $_TABLES['shop.ipnlog'],
            array('gateway', 'txn_id', 'event'),
            array($this->GW->getName(), $this->getID(), $this->getEvent())
        );
        if ($count > 0) {
            SHOP_log("Received duplicate IPN {$this->getID()} for {$this->GW->getName()}", SHOP_LOG_ERROR);
            return false;
        } else {
            return true;
        }
    }


    /**
     * Set a generic IPN variable.
     *
     * @param   string  $key    Key name
     * @param   mixed   $value  Value
     * @return  object  $this
     */
    protected function setIPN($key, $value)
    {
        if ($this->IPN === NULL) {
            $this->IPN = new IPNModel;
        }
        $this->IPN[$key] = $value;
        return $this;
    }


    /**
     * Redirect or display output upon completion.
     * Typical webhooks only display a message and return HTTP 200, which is
     * done in webhook.php.
     */
    public function redirectAfterCompletion()
    {
        return;
    }

}
