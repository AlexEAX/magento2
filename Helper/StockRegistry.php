<?php


namespace Retargeting\Tracker\Helper;

use Magento\CatalogInventory\Api\Data\StockStatusCollectionInterface;
use Magento\CatalogInventory\Model\StockRegistryProvider as MagentoStockRegistryProvider;

class StockRegistry extends MagentoStockRegistryProvider
{
    const DEFAULT_STOCK_SCOPE = 0;

    public function getStockStatuses(array $productIds, $scopeId = self::DEFAULT_STOCK_SCOPE)
    {
        $criteria = $this->stockStatusCriteriaFactory->create();
        /** @noinspection PhpParamsInspection */
        $criteria->setProductsFilter($productIds); // @codingStandardsIgnoreLine
        $criteria->setScopeFilter($scopeId);

        return $this->stockStatusRepository->getList($criteria);
    }
}