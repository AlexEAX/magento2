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

namespace Retargeting\Tracker\Model;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

/**
 * Class Helppages
 * @package Retargeting\Tracker\Model
 */
class Helppages
{

    /**
     * CMS Collection Factory
     * @var CollectionFactory
     */
    protected $cmsResourceModelPageCollectionFactory;

    /**
     * Helppages constructor.
     * @param CollectionFactory $cmsResourceModelPageCollectionFactory
     */
    public function __construct(
        CollectionFactory $cmsResourceModelPageCollectionFactory
    )
    {
        $this->cmsResourceModelPageCollectionFactory = $cmsResourceModelPageCollectionFactory;
    }

    /**
     * Get all pages
     * @return array
     */
    public function toOptionArray()
    {
        $pageCollection = $this->cmsResourceModelPageCollectionFactory->create();
        $pages = [];
        foreach ($pageCollection as $page) {
            $pages[] = [
                'value' => $page->getId(),
                'label' => $page->getTitle()
            ];
        }
        return $pages;
    }
}
