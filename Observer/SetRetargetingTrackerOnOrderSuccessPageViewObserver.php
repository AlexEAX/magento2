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

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Retargeting\Tracker\Helper\Data;

/**
 * Class SetRetargetingTrackerOnOrderSuccessPageViewObserver
 * @package Retargeting\Tracker\Observer
 */
class SetRetargetingTrackerOnOrderSuccessPageViewObserver implements ObserverInterface
{

    /**
     * Retargeting Data
     * @var null|Data
     */
    protected $_retargetingData = null;

    /**
     * Layout Interface
     * @var LayoutInterface
     */
    protected $_layout;

    /**
     * Store Manager Interface
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * SetRetargetingTrackerOnOrderSuccessPageViewObserver constructor.
     * @param StoreManagerInterface $storeManager
     * @param LayoutInterface $layout
     * @param Data $retargetingData
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        LayoutInterface $layout,
        Data $retargetingData
    )
    {
        $this->_retargetingData = $retargetingData;
        $this->_layout = $layout;
        $this->_storeManager = $storeManager;
    }

    /**
     * Add saveOrder information into Retargeting Block
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $orderIds = $observer->getEvent()->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }
        $block = $this->_layout->getBlock('retargeting_tracker');
        if ($block) {
            /** @noinspection PhpUndefinedMethodInspection */
            $block->setOrderIds($orderIds);
        }
    }
}
