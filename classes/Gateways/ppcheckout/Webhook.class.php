<?php
/**
 * Paypal Webhook class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     vTBD
 * @since       vTBD
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Gateways\ppcheckout;
use Shop\Payment;
use Shop\Order;
use Shop\Models\OrderState;
use Shop\Models\IPN as IPNModel;


/**
 * Paypal webhook class.
 * @package shop
 */
class Webhook extends \Shop\Webhook
{
    /**
     * Set up the webhook data from the supplied JSON blob.
     *
     * @param   string  $blob   JSON data
     */
    public function __construct($blob='')
    {
        $this->setSource('ppcheckout');

        // Load the payload into the blob property for later use in Verify().
        if (isset($_POST['vars'])) {
            $this->blob = base64_decode($_POST['vars']);
            if (isset($_POST['headers'])) {
                $this->setHeaders(json_decode(base64_decode($_POST['headers']),true));
            }
        } else {
            $this->blob = file_get_contents('php://input');
            $this->setHeaders(NULL);
        }
        //SHOP_log("Paypal Webook Payload: " . $this->blob, SHOP_LOG_DEBUG);
        //SHOP_log("Paypal Webhook Headers: " . var_export($_SERVER,true), SHOP_LOG_DEBUG);

        $this->setTimestamp();
        $this->setData(json_decode($this->blob));
        $this->GW = \Shop\Gateway::create($this->getSource());
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        $retval = true;

        if (isset($this->getData()->resource)) {
            $resource = $this->getData()->resource;
        } else {
            $resource = new \stdClass;
        }

        switch ($this->getEvent()) {
        case 'GL.CHECKOUT.AUTHORIZED':
            // Change the resource to be similar to a Paypal webhook
            $auth_id = $resource->id;   // Need to add this back as $resource->id
            if (isset($resource->purchase_units) && is_array($resource->purchase_units)) {
                $resource = $resource->purchase_units[0];
                $resource->id = $auth_id;
            } else {
                break;
            }
        case 'PAYMENT.AUTHORIZATION.CREATED':
            if (isset($resource->amount) && isset($resource->custom_id)) {
                /*$this->setPayment($resource->amount->value);
                $this->setCurrency($resource->amount->currency_code);*/
                //$this->setOrderId($resource->custom_id);
                $response = $this->GW->captureAuth($resource->id);
                if ($response && isset($response->result)) {
                    if ($response->statusCode >= 200 && $response->statusCode < 300) {
                        SHOP_log("Order {$resource->custom_id} captured successfully: capture id " .
                            $response->result->id . " status: " . $response->result->status);
                    }
                }
            }
            break;

        case 'GL.CHECKOUT.CAPTURED':
            $response = NULL;
            if (isset($resource->purchase_units) && is_array($resource->purchase_units)) {
                $payments = $resource->purchase_units[0]->payments;
                if (isset($payments->captures) && is_array($payments->captures)) {
                    $capture = $payments->captures[0];
                    $response = $this->GW->getCaptureDetails($capture->id);
                }
            }
            if ($response) {
                // Got a response, now set $resource to be the same as a Paypal
                // webhook resource from payment.capture.completed
                $resource = $response->result;
                if (
                    $resource->id != $capture->id ||
                    $resource->status != 'COMPLETED'
                ) {
                    break;
                }
            } else {
                break;
            }
        case 'PAYMENT.CAPTURE.COMPLETED':
            if (isset($resource->amount) && isset($resource->custom_id)) {
                $this->setOrderID($resource->custom_id);
                $this->Order = Order::getInstance($this->getOrderID());
                $this->setPayment($resource->amount->value);
                $this->setCurrency($resource->amount->currency_code);
                $ref_id = $resource->id;
                // Get the payment by reference ID to make sure it's unique
                $Pmt = Payment::getByReference($ref_id);
                if ($Pmt->getPmtID() == 0) {
                    $Pmt->setRefID($ref_id)
                        ->setAmount($this->getPayment())
                        ->setGateway($this->getSource())
                        ->setMethod($this->getSource())
                        ->setComment('Webhook ' . $this->getID())
                        ->setOrderID($this->getOrderID());
                    if ($Pmt->Save()) {
                        $retval = $this->handlePurchase();
                    }
                }
                $this->setID($ref_id);  // use the payment ID
                $this->logIPN();
            }
            break;

        case 'CHECKOUT.ORDER.APPROVED_X':
            $intent = 'CAPTURE';
            if (isset($resource->intent)) {
                $intent = $resource->intent;
            }
            if ($intent == 'AUTHORIZE') {
                break;
                $gw->captureAuth($resource->id);
            }
            if ($resource && isset($resource->payer)) {
                $payer = $resource->payer;
                if (isset($payer->name)) {
                    $fname = '';
                    $lname = '';
                    if (isset($payer->name->given_name)) {
                        $fname = $payer->name->given_name;
                        $this->setIPN('first_name', $fname);

                    }
                    if (isset($payer->name->surname)) {
                        $lname = $payer->name->surname;
                        $this->setIPN('last_name', $lname);
                    }
                    $this->setIPN('payer_name', $fname . ' ' . $lname);
                }
                if (isset($payer->email_address)) {
                    $this->setIPN('payer_email', $payer->email_address);
                }

                $purchase_units = $resource->purchase_units;
                if (is_array($purchase_units)) {
                    $unit = $purchase_units[0];
                    if (isset($unit->custom_id)) {
                        $this->setOrderID($unit->custom_id);
                        $this->Order = Order::getInstance($this->getOrderID());
                    }
                    if (
                        isset($unit->payments) &&
                        isset($unit->payments->captures) &&
                        is_array($unit->payments->captures)
                    ) {
                        $capture = $unit->payments->captures[0];
                        $ref_id = $capture->id;
                        if (isset($capture->amount)) {
                            $this->setPayment($unit->amount->value);
                            $this->setCurrency($unit->amount->currency_code);
                        }
                        $Pmt = Payment::getByReference($ref_id);
                        //if ($Pmt->getPmtID() == 0) {
                            $Pmt->setRefID($ref_id)
                                ->setAmount($this->getPayment())
                                ->setGateway($this->getSource())
                                ->setMethod('Paypal Checkout')
                                ->setComment('Webhook ' . $this->getID())
                                ->setOrderID($this->getOrderID());
                            return $Pmt->Save();
                            $this->handlePurchase();
                        //}
                        $this->setID($ref_id);  // use the payment ID for logging
                        $this->logIPN();
                    }
                }
            }
            break;

        case 'INVOICING.INVOICE.PAID':
            if (isset($resource->invoice)) {
                $invoice = $resource->invoice;
            }
            if ($invoice) {
                $this->IPN = new IPNModel(array(
                    'sql_date' => '',       // SQL-formatted date string
                    'uid' => 0,             // user ID to receive credit
                    'pmt_gross' => 0,       // gross amount paid
                    'txn_id' => '',         // transaction ID
                    'gw_name' => '',        // gateway short name
                    'memo' => '',           // misc. comment
                    'first_name' => '',     // payer's first name
                    'last_name' => '',      // payer's last name
                    'payer_name' => '',     // payer's full name
                    'payer_email' => '',    // payer's email address
                    'custom' => array(  // backward compatibility for plugins
                        'uid' => 0,
                    ),
                ) );
                if (isset($invoice->detail)) {
                    $this->setOrderId($invoice->detail->reference);
                } else {
                    SHOP_log("Order number not found");
                    break;
                }

                if (isset($invoice->payments)) {
                    $payments = $invoice->payments;
                    if (
                        isset($payments->transactions) &&
                        is_array($payments->transactions) &&
                        !empty($payments->transactions)
                    ) {
                        // Get just the latest payment.
                        // If there are multiple payments for the order, all are included.
                        $payment = array_pop($payments->transactions);
                        if ($payment) {
                            $ref_id = $payment->payment_id;
                            // Get the payment by reference ID to make sure it's unique
                            $Pmt = Payment::getByReference($ref_id);
                            if ($Pmt->getPmtID() == 0) {
                                $Pmt->setRefID($ref_id)
                                    ->setAmount($payment->amount->value)
                                    ->setGateway($this->getSource())
                                    ->setMethod($payment->method)
                                    ->setComment('Webhook ' . $this->getID())
                                    ->setStatus($invoice->status)
                                    ->setOrderID($this->getOrderID());
                                $retval = $Pmt->Save();
                            }
                        }
                        $this->setID($ref_id);  // use the payment ID
                        $this->logIPN();
                     }
                }
            }
            break;

        case 'INVOICING.INVOICE.CREATED':
            $status = false;
            if (isset($resource->invoice)) {
                $invoice = $resource->invoice;
                if ($invoice) {
                    $detail = $invoice->detail;
                    if ($detail) {
                        $this->setOrderID($detail->reference);
                        $status = true;
                    }
                }
                SHOP_log("Invoice created for {$this->getOrderID()}", SHOP_LOG_DEBUG);
                $this->Order = Order::getInstance($this->getOrderID());
                if (!$this->Order->isNew()) {
                    $terms_gw = \Shop\Gateway::create($this->Order->getPmtMethod());
                    $this->Order->setInfo('gw_pmt_url', $invoice->detail->metadata->recipient_view_url);
                    $this->Order->setGatewayRef($invoice->id)
                          ->setInfo('terms_gw', $this->GW->getName())
                          ->Save();
                    $this->Order->updateStatus($terms_gw->getConfig('after_inv_status'));
                }
            }
            if (!$status) {
                SHOP_log("Error processing webhook " . $this->getEvent());
            }
            break;

        case 'PAYMENT.CAPTURE.REFUNDED':
            if (isset($resource->custom_id)) {
                $this->setOrderID($resource->custom_id);
                $this->setPayment($resource->amount->value);
                $this->setCurrency($resource->amount->currency_code);
                $ref_id = $resource->id;
                // Get the payment by reference ID to make sure it's unique
                $Pmt = Payment::getByReference($ref_id);
                if ($Pmt->getPmtID() == 0) {
                    $Pmt->setRefID($ref_id)
                        ->setAmount($this->getPayment() * -1)
                        ->setGateway($this->getSource())
                        ->setMethod($this->getSource())
                        ->setComment('Webhook ' . $this->getID())
                        ->setOrderID($this->getOrderID());
                    $retval = $Pmt->Save();
                }
                $this->setID($ref_id);  // use the payment ID
                $this->logIPN();
            } else {
                SHOP_log("Order number not found for refund");
                $retval = false;
                break;
            }
            break;
        }
        return $retval;;
    }


    /**
     * Verify that the webhook is valid.
     *
     * @return  boolean     True if valid, False if not.
     */
    public function Verify()
    {
        $status = false;        // default to invalid

        // Check that the blob was decoded successfully.
        // If so, extract the key fields and set Webhook variables.
        $data = $this->getData();
        if ($data) {     // Indicates that the blob was decoded
            $this->setID($data->id);
            $this->setEvent($data->event_type);
        } else {
            return $status;
        }

        if (isset($_GET['testhook'])) {
            $this->setVerified(true);
            return true;
        }
        $gw = \Shop\Gateway::getInstance($this->getSource());
        /*for ($i = 0; $i < 3; $i++) {
            if ($i > 0) {
                sleep(10);
            }
            $resp = json_decode($gw->getWebhookDetails($this->getID()));
            COM_errorLog("DEBUG: " . var_export($resp, true));
            if ($resp) {
                if (
                    isset($resp->id) && isset($resp->event_id) &&
                    $resp->id == $data->id &&
                    $resp->event_type == $data->event_type
                ) {
                    $status = true;
                    break;
                }
            }
        }
        $this->setVerified($status);
        return $status;
         */

        $body = '{
            "auth_algo": "' . $this->getHeader('Paypal-Auth-Algo') . '",
            "cert_url": "' . $this->getHeader('Paypal-Cert-Url') . '",
            "transmission_id": "' . $this->getHeader('Paypal-Transmission-Id') . '",
            "transmission_sig": "'. $this->getHeader('Paypal-Transmission-Sig') . '",
            "transmission_time": "'. $this->getHeader('Paypal-Transmission-Time') . '",
            "webhook_id": "' . $gw->getWebhookID() . '",
            "webhook_event": ' . $this->blob . '
        }';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gw->getApiUrl() . '/v1/notifications/verify-webhook-signature');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $gw->getBearerToken(),
        ) ); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = @json_decode(curl_exec($ch), true);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!is_array($result)) {
            SHOP_log("Error decoding response: Code $code, Data " . print_r($result,true));
            $status = false;
        }
        SHOP_log("Received code $code from PayPal", SHOP_LOG_DEBUG);
        switch ($code) {
        case 200:
            SHOP_log("Result " . print_r($result,true), SHOP_LOG_DEBUG);
            $status = SHOP_getVar($result, 'verification_status') == 'SUCCESS' ? true : false;
            break;
        default:
            SHOP_log("Error $code : " . var_export($result, true));
            $status = false;
            if (isset($result['name']) && $result['name'] == 'VALIDATION_ERROR') {
                // fatal error
                break;
            } else {
                // Failed to get the information, return a 503 so PayPal will resend
                header('HTTP/1.1 503 Validation Error');
                header('Status: 503 Invalid Response');
                echo '503: Invalid Response';
                exit;
            }
            break;
        }
        $this->setVerified($status);
        return $status;
    }

}
