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

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Retargeting\Tracker\Helper\Data as RetargetingData;

/**
 * Class Category
 * @package Retargeting\Tracker\Block
 */
class Category extends Template
{
    /**
     * @var Registry
     */
    protected $_registry;

    /** @var null|RetargetingData */
    protected $_retargetingData = null;

    /**
     * Category constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param $retargetingData RetargetingData
     * @param array $data
     *
     */
    public function __construct(
        Context $context,
        Registry $registry,
        RetargetingData $retargetingData,
        array $data = []
    )
    {
        $this->_retargetingData = $retargetingData;
        $this->_registry = $registry;

        parent::__construct($context, $data);
    }

    /**
     * Retargeting sendCategory
     * @link https://retargeting.biz/documentation/index.html#sendCategory
     * @return string
     */
    public function getSendCategory()
    {
        $category = $this->_registry->registry('current_category');
        return json_encode($this->buildSendCategory($category), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Prepare Category
     * @param \Magento\Catalog\Model\Category $category
     * @return array
     */
    protected function buildSendCategory(
        \Magento\Catalog\Model\Category $category
    )
    {
        return [
            'id' => $category->getId(),
            'name' => $this->escapeHtml($category->getName()),
            'parent' => false,
            'breadcrumb' => [],
        ];
    }

    /**
     * Get Parent Category
     * @param \Magento\Catalog\Model\Category $category
     * @return bool|mixed
     */
    protected function getParentCategory(
        \Magento\Catalog\Model\Category $category
    )
    {
        $parentId = $category->getParentCategory()->getId();
        if ($parentId > 2) {
            return $parentId;
        }
        return false;
    }

    /**
     * Build Category Breadcrumb
     * @param \Magento\Catalog\Model\Category $category
     * @return array|false
     */
    protected function buildCategoryBreadcrumbs(
        \Magento\Catalog\Model\Category $category
    )
    {
        $categoryBreadcrumbs = [];
        $categoryId = $category->getId();
        foreach ($category->getParentCategories() as $breadcrumb) {
            if ($categoryId != $breadcrumb->getId()) {
                $categoryBreadcrumbs[] = [
                    'id' => $breadcrumb->getId(),
                    'name' => $this->escapeHtml($breadcrumb->getName()),
                    'parent' => $breadcrumb->getParentCategory()->getId() == 2 ? false : $breadcrumb->getParentCategory()->getId()
                ];
            }
        }
        return $categoryBreadcrumbs;
    }

    /**
     * Return data
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
