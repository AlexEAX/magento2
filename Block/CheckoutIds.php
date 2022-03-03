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

use Magento\Checkout\Model\Cart;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class CheckoutIds
 * @package Retargeting\Tracker\Block
 */
class CheckoutIds extends Template
{
    protected $cart;

    /**
     * CheckoutIds constructor.
     * @param Cart $cart
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Cart $cart,
        Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->cart = $cart;
    }

    /**
     * @return array
     */
    public function checkoutIds()
    {
        return array_values($this->cart->getQuoteProductIds());
    }
}
