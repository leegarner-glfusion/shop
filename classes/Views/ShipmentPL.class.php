<?php
/**
 * Class to create a packing list for a single shipment.
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
namespace Shop\Views;
use Shop\Shipper;
use Shop\Shipment;


/**
 * Shipment Packinglist class.
 * @package shop
 */
class ShipmentPL
{
    /** Shipment object.
     * @var object */
    protected $Shipment;

    /** Order object.
     * @var object */
    protected $Order;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $shipment_id    Shipment record ID
     */
    public function __construct($shipment_id)
    {
        $this->Shipment = new Shipment($shipment_id);
        $this->Order = $this->Shipment->getOrder();
    }


    /**
     * Create the display.
     *
     * @param   string  $title  Language key for form title
     * @param   string  $type   Type of output, HTML or PDF
     * @return  string      HTML or PDF content
     */
    public function Render($title='packinglist', $type='html')
    {
        global $_SHOP_CONF, $LANG_SHOP;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file(array(
            'order'     => $type == 'pdf' ? 'packinglist.pdf.thtml' : 'packinglist.thtml',
            'tracking'  => 'shipment_tracking_2.thtml',
        ) );
        $T->set_var(array(
            'page_title'    => $LANG_SHOP[$title],
            'shipment_id'    => $this->shipment_id,
        ) );
        $T->set_block('order', 'ItemRow', 'iRow');
        foreach ($this->Shipment->getItems() as $Item) {
            $OI = $Item->getOrderItem();
            $P = $OI->getProduct();
            $T->set_var(array(
                'oi_id'         => $OI->id,
                'item_dscp'     => htmlspecialchars($OI->description),
                'item_options'  => $OI->getOptionDisplay(),
                'item_quantity' => $Item->quantity,
                'sku'           => $P->getSKU($OI),
            ) );
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        $T->set_block('order', 'trackingPackages', 'TP');
        foreach ($this->Shipment->Packages as $Pkg) {
            $T->set_var(array(
                'shipper_code'  => $Pkg->getShipper()->code,
                'shipper_name'  => $Pkg->shipper_info,
                'tracking_num'  => $Pkg->tracking_num,
                'pkg_id'        => $Pkg->pkg_id,
                'tracking_url'  => Shipper::getInstance($Pkg->shipper_id)->getTrackingUrl($Pkg->tracking_num),
            ) );
            $T->parse('TP', 'trackingPackages', true);
        }

        $Shop = \Shop\Company::getInstance();
        $T->set_var(array(
            'shipment_id'        => $this->shipment_id,
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'order_date'    => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->Order->getOrderDate()->format($_SHOP_CONF['datetime_fmt'], false),
            'order_id'      => $this->Order->order_id,
            'order_instr'   => htmlspecialchars($this->instructions),
            'shop_name'     => $Shop->getCompany(),
            'shop_addr'     => $Shop->toHTML('address'),
            'shop_phone'    => $_SHOP_CONF['shop_phone'],
            'billto_addr'   => $this->Order->getBillto()->toHTML(),
            'shipto_addr'   => $this->Order->getShipto()->toHTML(),
            'status'        => $this->Order->status,
            'ship_method'   => Shipper::getInstance($this->Order->shipper_id)->getName(),
            'tracking_info' => count($this->Shipment->Packages),
            'tracking_form' => $T->parse('order', 'tracking'),
        ) );

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Create PDF output of one or more packing lists.
     *
     * @param   array   $ids    Array of order IDs
     * @param   string  $type   View type, 'pl' or 'order'
     * @return  boolean     True on success, False on error
     */
    public static function printPDF($ids, $type='pdfpl')
    {
        USES_lglib_class_html2pdf();
        try {
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
            //$html2pdf->setModeDebug();
            $html2pdf->setDefaultFont('Arial');
        } catch(HTML2PDF_exception $e) {
            COM_errorLog($e);
            return false;
        }

        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $shp_id) {
            $PL = new self($shp_id);
            if ($PL->Shipment->isNew) {
                continue;
            }
            $content = $PL->Render('packinglist', 'pdf');
            //echo $content;die;
            try {
                $html2pdf->writeHTML($content);
            } catch(HTML2PDF_exception $e) {
                COM_errorLog($e);
                return false;
            }
        }
        $html2pdf->Output($type . 'list.pdf', 'I');
        return true;
    }



    /**
     * Determine if the current user can view this packing list.
     *
     * @return  boolean     True if access is allowed, False if not.
     */
    public function canView()
    {
        return $this->Order->canView();
    }

}

?>
