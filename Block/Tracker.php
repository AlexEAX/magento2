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
use Magento\Store\Model\ScopeInterface;
use Retargeting\Tracker\Helper\Data;

/**
 * Class Tracker
 * @package Retargeting\Tracker\Block
 */
class Tracker extends Template
{

    /** @var null|Data */
    protected $_retargetingData = null;

    /** @var CollectionFactory */
    protected $_orderCollection;

    /** @var Session */
    protected $_catalogSession;

    /**
     * Tracker constructor.
     * @param Context $context
     * @param Session $catalogSession
     * @param CollectionFactory $orderCollection
     * @param Data $retargetingData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $catalogSession,
        CollectionFactory $orderCollection,
        Data $retargetingData,
        array $data = []
    )
    {
        $this->_retargetingData = $retargetingData;
        $this->_orderCollection = $orderCollection;
        $this->_catalogSession = $catalogSession;
        parent::__construct($context, $data);
    }

    /**
     * @param $path
     * @return mixed
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $accountId
     * @return mixed
     */
    public function getPageTrackingCode($accountId)
    {
        return $accountId;
    }

    /**
     * @return bool
     */
    public function getRetargetingTrackerVersion()
    {
        return $this->_retargetingData->getRetargetingTrackerVersion();
    }

    /**
     * _ra.saveOrder
     * @return string|void
     */
    public function getSaveOrderTrackingCode()
    {
        $orderIds = $this->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }

        $collection = $this->_orderCollection->create();
        $collection->addFieldToFilter('entity_id', ['in' => $orderIds]);

        $saveOrderProducts = [];
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
                    'id' => $item->getProductId(),
                    'quantity' => $item->getQtyOrdered(),
                    'price' => number_format($orderPrice, 2, '.', ''),
                    'variation_code' => false
                ];
            }

            /**
             * @var $address Address
             */
            $saveOrderInfo = [
                'order_no' => $order->getIncrementId(),
                'lastname' => $order->getCustomerLastname(),
                'firstname' => $order->getCustomerFirstname(),
                'email' => $order->getCustomerEmail(),
                'phone' => $address->getTelephone(),
                'state' => $address->getCity(),
                'city' => $address->getCity(),
                'address' => $address->getStreet(),
                'discount_code' => $order->getCouponCode(),
                'discount' => $order->getDiscountAmount(),
                'shipping' => number_format($order->getBaseShippingAmount(), 2, '.', ''),
                'rebates' => number_format($order->getBaseTaxAmount(), 2, '.', ''),
                'fees' => 0,
                'total' => number_format($order->getBaseGrandTotal(), 2, '.', '')
            ];
        }

        $saveOrder = 'var _ra = _ra || {};' . "\n";
        $saveOrder .= '_ra.saveOrderInfo = ' . json_encode($saveOrderInfo) . ";\n";
        $saveOrder .= '_ra.saveOrderProducts = ' . json_encode($saveOrderProducts) . ";\n";
        $saveOrder .= 'if( _ra.ready !== undefined ){
        _ra.saveOrder(_ra.saveOrderInfo, _ra.saveOrderProducts);}';

        return $saveOrder;
    }

    /**
     * Set Session Data
     * @param $key
     * @param $value
     * @return mixed
     */
    public function setSessionData($key, $value)
    {
        return $this->_catalogSession->setData($key, $value);
    }

    /**
     * SessionData
     * @param string $key
     * @param bool $remove
     * @return mixed
     */
    public function getSessionData($key, $remove = false)
    {
        return $this->_catalogSession->getData($key, $remove);
    }

    /**
     * Get Session
     * @return Session
     */
    public function getCatalogSession()
    {
        return $this->_catalogSession;
    }

    /**
     * Render code
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->_retargetingData->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
