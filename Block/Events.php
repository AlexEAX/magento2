<?php
/**
 * The Retargeting Magento 2 extension implements the required tagging for Retargeting's
 * functions in Magento 2 based web-shops.
 *
 * @category    Retargeting
 * @package     Retargeting_Tracking
 * @author      Retargeting Team <info@retargeting.biz>
 * @copyright   Retargeting (https://retargeting.biz)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Retargeting\Tracker\Block;

use Magento\Catalog\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Retargeting\Tracker\Helper\Data as RetargetingData;

/**
 * Class Events
 * @package Retargeting\Tracker\Block
 */
class Events extends Template
{

    /**
     * @var null|RetargetingData
     */
    protected $_retargetingData = null;

    /**
     * @var CollectionFactory
     */
    protected $_orderCollection;
    /**
     * @var Session
     */
    protected $_catalogSession;

    /**
     * Events constructor.
     * @param Context $context
     * @param Session $catalogSession
     * @param CollectionFactory $orderCollection
     * @param RetargetingData $retargetingData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $catalogSession,
        CollectionFactory $orderCollection,
        RetargetingData $retargetingData,
        array $data = []
    )
    {
        $this->_retargetingData = $retargetingData;
        $this->_orderCollection = $orderCollection;
        $this->_catalogSession = $catalogSession;
        parent::__construct($context, $data);
    }

    /**
     * Retargeting saveOrder
     *
     * @link https://retargeting.biz/documentation/index.html#saveOrder
     *
     * @return string|void
     */
    public function getSaveOrderTrackingCode()
    {
        $orderIds = $this->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return false;
        }

        $collection = $this->_orderCollection->create();
        $collection->addFieldToFilter('entity_id', ['in' => $orderIds]);

        $saveOrderProducts = [];
        $saveOrderInfo = [];
        foreach ($collection as $order) {
            if ($order->getIsVirtual()) {
                $address = $order->getBillingAddress();
            } else {
                $address = $order->getShippingAddress();
            }

            foreach ($order->getAllVisibleItems() as $item) {
                $orderPrice = (int) $order->getBaseTaxAmount() > 0 ?
                $item->getPriceInclTax() : $item->getBasePrice();

                $saveOrderProducts[] = [
                    'id' => $item->getId(),
                    'quantity' => $item->getQtyOrdered(),
                    'price' => $orderPrice,
                    'variation_code' => false
                ];
            }
            /**
             * @var $address Address
             */
            $saveOrderInfo = [
                'order_no' => $order->getIncrementId(),
                'lastname' => $this - $this->escapeHtml(
                        $order->getCustomerLastname()
                    ),
                'firstname' => $this->escapeHtml($order->getCustomerFirstname()),
                'email' => $this->escapeHtml($order->getCustomerEmail()),
                'phone' => $this->escapeHtml($address->getTelephone()),
                'state' => $this->escapeHtml($address->getCity()),
                'city' => $this->escapeHtml($address->getCity()),
                'address' => $this->escapeHtml($address->getStreet()),
                'discount_code' => $this->escapeHtml($order->getCouponCode()),
                'discount' => $order->getDiscountAmount(),
                'shipping' => $order->getBaseShippingAmount(),
                'rebates' => $order->getBaseTaxAmount(),
                'fees' => 0,
                'total' => $order->getBaseGrandTotal()
            ];
        }

        $saveOrder = 'var _ra = _ra || {};' . "\n";
        $saveOrder .= '_ra.saveOrderInfo = ' . json_encode($saveOrderInfo) . ";\n";
        $saveOrder .= '_ra.saveOrderProducts = ' . json_encode($saveOrderProducts) . ";\n";
        $saveOrder .= 'if( _ra.ready !== undefined ){
        _ra.saveOrder(_ra.saveOrderInfo, _ra.saveOrderProducts);
        }';

        return $saveOrder;
    }

    /**
     * Set session data
     *
     * @param $key
     * @param $value
     * @return mixed
     *
     */
    public function setSessionData($key, $value)
    {
        return $this->_catalogSession->setData($key, $value);
    }

    /**
     * Retrieve data from session
     *
     * @param $key
     * @param bool $remove
     * @return mixed
     *
     */
    public function getSessionData($key, $remove = false)
    {
        return $this->_catalogSession->getData($key, $remove);
    }

    /**
     * Retrieve catalog session
     *
     * @return mixed
     *
     */
    public function getCatalogSession()
    {
        return $this->_catalogSession;
    }

    /**
     * Render the code
     *
     * @return string
     *
     */
    protected function _toHtml()
    {
        if (!$this->_retargetingData->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
