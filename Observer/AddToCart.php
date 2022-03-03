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

namespace Retargeting\Tracker\Observer;

use Magento\Catalog\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Retargeting\Tracker\Block\Tracker;
use Retargeting\Tracker\Helper\Data;

/**
 * Class AddToCart
 * @package Retargeting\Tracker\Observer
 */
class AddToCart implements ObserverInterface
{
    /**
     * Retargeting Helper
     * @var Data $helper
     */
    protected $_helper;

    /**
     * Catalog Session
     * @var Session $catalogSession
     */
    protected $_catalogSession;

    /**
     * Retargeting Tracker Block
     * @var Tracker $block
     */
    protected $_block;

    /**
     * Constructor
     * @param Data $helper
     * @param Session $catalogSession
     * @param Tracker $block
     */
    public function __construct(
        Data $helper,
        Session $catalogSession,
        Tracker $block
    )
    {
        $this->_helper = $helper;
        $this->_catalogSession = $catalogSession;
        $this->_block = $block;
    }

    /**
     * Add To Cart and Remove From Cart
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $_item = $observer->getQuoteItem();
        $objectManager = ObjectManager::getInstance();
        /** @noinspection ObjectManagerInspection */
        /** @noinspection PhpUndefinedMethodInspection */
        $_product = $objectManager->get('Magento\Catalog\Model\Product')->load($_item->getProductId());

        $action = null;

        if (empty($_product)) {
            return false;
        }

        $result = [];
        $result[] = 'var _ra = _ra || {};' . "\r\n";

        $event = $observer->getEvent()->getName();

        switch ($event) {
            /*case 'checkout_cart_product_add_after':
                $action = 'add';
                break;*/
            case 'sales_quote_remove_item':
                $action = 'remove';
                break;
        }
/*
        if ($action == 'add') {
            /** @noinspection PhpUndefinedMethodInspection * /
            $result[] = sprintf("
                _ra.addToCartInfo = {
                        'product_id': '%s',
                        'quantity': %s,
                        'variation': false
                };", $this->_block->escapeJsQuote($_product->getId()), $_item->getQty());

            $result[] = "
                if (_ra.ready !== undefined) {
                    _ra.addToCart(
                    _ra.addToCartInfo.product_id, 
                    _ra.addToCartInfo.quantity,
                    _ra.addToCartInfo.variation
                    );
                }";
        } else*/
        if ($action == 'remove') {
            /** @noinspection PhpUndefinedMethodInspection */
            $result[] = sprintf("
                _ra.removeFromCartInfo = {
                        'product_id': '%s',
                        'quantity': %s,
                        'variation': false
                };", $this->_block->escapeJsQuote($_product->getId()), $_item->getQty());
            $result[] = "
                if (_ra.ready !== undefined) {
                    _ra.removeFromCart(
                        _ra.removeFromCartInfo.product_id,
                        _ra.removeFromCartInfo.quantity,
                        _ra.removeFromCartInfo.variation
                    );
                }";
        }

        /** @noinspection PhpUndefinedMethodInspection */
        // $addToCartData = $this->_catalogSession->getAddToCart();
        $data = implode("\n", $result);
        /** @noinspection PhpUndefinedMethodInspection $addToCartData .*/
        $this->_catalogSession->setAddToCart($data);
        return true;
    }
}
