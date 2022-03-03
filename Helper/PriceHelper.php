<?php

namespace Retargeting\Tracker\Helper;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Data;
use Magento\Tax\Model\Config;
use Magento\Catalog\Model\Product;

class PriceHelper extends AbstractHelper
{

    /**
     * @var Data
     */
    public $catalogHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;

    /**
     * Tax config model
     *
     * @var Config
     */
    public $taxConfig;

    /**
     * Tax display flag
     *
     * @var null|int
     */
    public $taxDisplayFlag = null;

    /**
     * Tax catalog flag
     *
     * @var null|int
     */
    public $taxCatalogFlag = null;

    /**
     * Store object
     *
     * @var null|Store
     */
    public $store = null;

    /**
     * Store ID
     *
     * @var null|int
     */
    public $storeId = null;

    /**
     * Base currency code
     *
     * @var null|string
     */
    public $baseCurrencyCode = null;

    /**
     * Current currency code
     *
     * @var null|string
     */
    public $currentCurrencyCode = null;

    public function __construct(
        Context $context,
        Data $catalogHelper,
        Config $taxConfig,
        StoreManager $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    )
    {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->catalogHelper = $catalogHelper;
        $this->taxConfig = $taxConfig;
        parent::__construct($context);
    }

    /**
     * @param $product
     * @return array|float|string
     * @throws Exception
     */
    public function getProductPrice($product)
    {
        $price = 0.0;

        switch ($product->getTypeId()) {
            case 'bundle':
                $price = $this->getBundleProductPrice($product);
                break;
            case 'configurable':
                $price = $this->getConfigurableProductPrice($product);
                break;
            case 'grouped':
                $price = $this->getGroupedProductPrice($product);
                break;
            default:
                $price = $this->getFinalPrice($product);
        }

        return $this->formatPrice($price);
    }

    /**
     * Returns bundle product price.
     *
     * @param Product $product
     * @return string
     * @throws Exception
     */
    public function getBundleProductPrice($product)
    {
        $includeTax = (bool)$this->getDisplayTaxFlag();

        return $this->getFinalPrice(
            $product,
            $product->getPriceModel()->getTotalPrices(
                $product,
                'min',
                $includeTax,
                1
            )
        );
    }


    /**
     * Returns configurable product price.
     *
     * @param Product $product
     * @return array|string
     * @throws Exception
     */
    public function getConfigurableProductPrice($product)
    {
        if ($product->getFinalPrice() === 0) {
            $simpleCollection = $product->getTypeInstance()
                ->getUsedProducts($product);

            foreach ($simpleCollection as $simpleProduct) {
                if ($simpleProduct->getPrice() > 0) {
                    return $this->getFinalPrice($simpleProduct);
                }
            }
        }

        return $this->getFinalPrice($product);
    }

    /**
     * Returns grouped product price.
     *
     * @param Product $product
     * @return string
     * @throws Exception
     */
    public function getGroupedProductPrice($product)
    {
        $assocProducts = $product->getTypeInstance(true)
            ->getAssociatedProductCollection($product)
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('tax_class_id')
            ->addAttributeToSelect('tax_percent');

        $minPrice = INF;
        foreach ($assocProducts as $assocProduct) {
            $minPrice = min($minPrice, $this->getFinalPrice($assocProduct));
        }

        return $minPrice;
    }

    /**
     * Returns final price.
     *
     * @param Product $product
     * @param string $price
     * @return string
     * @throws Exception
     */
    public function getFinalPrice(Product $product, $price = null)
    {
        if (empty($price)) {
            $price = $product->getFinalPrice();
        }

        if (empty($price)) {
            $price = $product->getData('special_price');
        }

        $productType = $product->getTypeId();

        if (($this->getBaseCurrencyCode() !== $this->getCurrentCurrencyCode())
            && $productType != 'configurable'
        ) {
            $price = $this->getStore()->getBaseCurrency()
                ->convert($price, $this->getCurrentCurrencyCode());
        }

        if ($productType != 'configurable' && $productType != 'bundle') {
            if ($this->getDisplayTaxFlag() && !$this->getCatalogTaxFlag()) {
                $price = $this->catalogHelper->getTaxPrice(
                    $product,
                    $price,
                    true,
                    null,
                    null,
                    null,
                    $this->getStoreId(),
                    false,
                    false
                );
            }
        }

        if ($productType != 'bundle') {
            if (!$this->getDisplayTaxFlag() && $this->getCatalogTaxFlag()) {
                $price = $this->catalogHelper->getTaxPrice(
                    $product,
                    $price,
                    false,
                    null,
                    null,
                    null,
                    $this->getStoreId(),
                    true,
                    false
                );
            }
        }

        return $this->formatPrice($price);
    }

    /**
     * @param Product $product
     * @param null $price
     * @return float|mixed|null
     * @throws NoSuchEntityException
     */
    public function getFullPrice(Product $product, $price = null)
    {
        if (empty($price)) {
            $price = $product->getPrice();
        }
        
        if (empty($price)) {
            $price = $product->getData('regular_price');
        }

        if (empty($price)) {
            $price = (string) $product->getPriceInfo()->getPrice('regular_price')->getValue();
        }

        if (empty($price)) {
            $price = $this->getFinalPrice($product);
        }

        $productType = $product->getTypeId();

        if (($this->getBaseCurrencyCode() !== $this->getCurrentCurrencyCode())
            && $productType != 'configurable'
        ) {
            $price = $this->getStore()->getBaseCurrency()
                ->convert($price, $this->getCurrentCurrencyCode());
        }

        if ($productType != 'configurable' && $productType != 'bundle') {
            if ($this->getDisplayTaxFlag() && !$this->getCatalogTaxFlag()) {
                $price = $this->catalogHelper->getTaxPrice(
                    $product,
                    $price,
                    true,
                    null,
                    null,
                    null,
                    $this->getStoreId(),
                    false,
                    false
                );
            }
        }

        if ($productType != 'bundle') {
            if (!$this->getDisplayTaxFlag() && $this->getCatalogTaxFlag()) {
                $price = $this->catalogHelper->getTaxPrice(
                    $product,
                    $price,
                    false,
                    null,
                    null,
                    null,
                    $this->getStoreId(),
                    true,
                    false
                );
            }
        }

        return $this->formatPrice($price);
    }

    /**
     * Returns formatted price.
     *
     * @param string $price
     * @return string
     */
    public function formatPrice($price)
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Returns Stores > Configuration > Sales > Tax > Calculation Settings
     * > Catalog Prices configuration value
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getCatalogTaxFlag()
    {
        // Are catalog product prices with tax included or excluded?
        if ($this->taxCatalogFlag === null) {
            $this->taxCatalogFlag = (int)$this->getConfig(
                'tax/calculation/price_includes_tax',
                $this->getStoreId()
            );
        }

        // 0 means excluded, 1 means included
        return $this->taxCatalogFlag;
    }

    /**
     * Returns flag based on "Stores > Configuration > Sales > Tax
     * > Price Display Settings > Display Product Prices In Catalog"
     * Returns 0 or 1 instead of 1, 2, 3.
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getDisplayTaxFlag()
    {
        if ($this->taxDisplayFlag === null) {
            // Tax Display
            // 1 - excluding tax
            // 2 - including tax
            // 3 - including and excluding tax
            $flag = $this->taxConfig->getPriceDisplayType($this->getStoreId());

            // 0 means price excluding tax, 1 means price including tax
            if ($flag == 1) {
                $this->taxDisplayFlag = 0;
            } else {
                $this->taxDisplayFlag = 1;
            }
        }

        return $this->taxDisplayFlag;
    }

    /**
     * Returns Store Id
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        if ($this->storeId === null) {
            $this->storeId = $this->getStore()->getId();
        }

        return $this->storeId;
    }

    /**
     * Returns store object
     *
     * @return Store
     * @throws NoSuchEntityException
     */
    public function getStore()
    {
        if ($this->store === null) {
            $this->store = $this->storeManager->getStore();
        }

        return $this->store;
    }

    /**
     * Based on provided configuration path returns configuration value.
     *
     * @param string $configPath
     * @param string|int $scope
     * @return string
     */
    public function getConfig($configPath, $scope = 'default')
    {
        return $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $scope
        );
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getBaseCurrencyCode()
    {
        if ($this->baseCurrencyCode === null) {
            $this->baseCurrencyCode = strtoupper(
                $this->getStore()->getBaseCurrencyCode()
            );
        }

        return $this->baseCurrencyCode;
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getCurrentCurrencyCode()
    {
        if ($this->currentCurrencyCode === null) {
            $this->currentCurrencyCode = strtoupper(
                $this->getStore()->getCurrentCurrencyCode()
            );
        }

        return $this->currentCurrencyCode;
    }

}