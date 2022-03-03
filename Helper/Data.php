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

namespace Retargeting\Tracker\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Bundle\Model\Product\Price as BundlePrice;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\Website;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Bundle\Model\Product\Type as Bundled;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;


/**
 * Class Data
 * @package Retargeting\Tracker\Helper
 */
class Data extends AbstractHelper
{
    const RETARGETING_STATUS = 'retargeting/retargeting/status';
    const RETARGETING_TRACKING_API_KEY = 'retargeting/retargeting/trackingApi';
    const RETARGETING_REST_API = 'retargeting/retargeting/restApi';
    const RETARGETING_ADD_TO_CART_BUTTON_ID = 'retargeting/advanced_settings/addToCart';
    const RETARGETING_PRICE_LABEL_ID = 'retargeting/advanced_settings/priceLabelSelector';
    const RETARGETING_SHOPPING_CART_URL = 'retargeting/advanced_settings/shoppingCartUrl';
    const RETARGETING_IMAGE_SELECTOR = 'retargeting/advanced_settings/imageSelector';
    const RETARGETING_CRON_FEED = 'retargeting/advanced_settings/cronfeed';
    const RETARGETING_DEFAULT_STOCK = 'retargeting/advanced_settings/defaultStock';

    /**
     * ModuleList Interface
     * @var ModuleListInterface
     */
    protected $_moduleListing;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $_categoryRepository;

    /**
     * @var
     */
    protected $_productRepository;

    /**
     * Data
     * @var CatalogHelper
     */
    private $_catalogHelper;

    private $taxHelper;

    private $storeManager;
    private $taxCalculation;


    /**
     * Data constructor.
     * @param Context $context
     * @param ModuleListInterface $moduleListing
     * @param CatalogHelper $catalogHelper
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ProductRepositoryInterface $productRepository
     * @param TaxHelper $taxHelper
     * @param StoreManager $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxCalculationInterface $taxCalculation
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleListing,
        CatalogHelper $catalogHelper,
        CategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository,
        TaxHelper $taxHelper,
        StoreManager $storeManager,
        ScopeConfigInterface $scopeConfig,
        TaxCalculationInterface $taxCalculation
    )
    {
        parent::__construct($context);
        $this->_moduleListing = $moduleListing;
        $this->_catalogHelper = $catalogHelper;
        $this->_categoryRepository = $categoryRepository;
        $this->_productRepository = $productRepository;
        $this->taxHelper = $taxHelper;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->taxCalculation = $taxCalculation;
    }

    /**
     * Check if module is enabled
     * @param null $store
     * @return bool
     */
    public function isEnabled($store = null)
    {
        $accountId = $this->scopeConfig->getValue(
            self::RETARGETING_TRACKING_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return $accountId && $this->scopeConfig->isSetFlag(
                self::RETARGETING_STATUS,
                ScopeInterface::SCOPE_STORE,
                $store
            );
    }

    public function getConfig($configPath, $scope = 'default') {
        return $this->scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $scope
        ) ?? 0;
    }
    /**
     * Return Retargeting Tracker Version
     * @return bool
     */
    public function getRetargetingTrackerVersion()
    {
        $retargetingTracker = $this->_moduleListing->getOne('Retargeting_Tracker');
        if (!empty($retargetingTracker['setup_version'])) {
            return $retargetingTracker['setup_version'];
        } else {
            return false;
        }
    }

    /**
     * Build Category
     * @param Category $category
     * @return array|null
     */
    public function buildCategory($category)
    {
        $data = [];
        $path = $category->getPath();
        foreach (explode('/', $path) as $categoryId) {
            try {
                $category = $this->_categoryRepository->get($categoryId);
            } catch (NoSuchEntityException $e) {
            }

            if ($category) {
                $data['id'] = $category->getId();
                $data['name'] = $category->getName();
                $data['parent'] = false;
                $data['breadcrumb'] = [];
            }
        }

        return $data;
    }

    /**
     * @param array $categories
     * @return string|null
     */
    public function getProductCategory(array $categories = [])
    {
        if (!count($categories)) {
            return '';
        }
        foreach ($categories as $categoryId) {
            try {
                $category = $this->_categoryRepository->get($categoryId);
                return $category->getName();
            } catch (NoSuchEntityException $e) {

            }
        }
        return '';
    }

    /**
     * AddToCart
     * @param int $qty
     * @param Product $product
     * @return array
     */
    public function addToCartPushData($qty, $product)
    {
        $result = [];

        $result['event'] = 'addToCart';
        $result['retargeting'] = [];
        $result['retargeting']['add'] = [];
        $result['retargeting']['add']['products'] = [];

        $productData = [];
        $productData['name'] = html_entity_decode($product->getName());
        $productData['id'] = $product->getId();
        $productData['price'] = number_format($product->getFinalPrice(), 2);

        $productData['quantity'] = $qty;

        $result['retargeting']['add']['products'][] = $productData;

        return $result;
    }

    public function getStock(Product $product)
    {
        $qty = 0;
        switch ($product->getTypeId()) {
            case ProductType::TYPE_BUNDLE:
                /** @var Bundled $productType */
                $productType = $product->getTypeInstance();
                $bundledItemIds = $productType->getChildrenIds($product->getId(), $required = true);
                $products = [];
                foreach ($bundledItemIds as $variants) {
                    if (is_array($variants) && count($variants) > 0) {
                        foreach ($variants as $productId) {
                            $products[] = $productId;
                        }
                    }
                }
                $qty = $this->getMinQty($products);
                break;
            case Grouped::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof Grouped) {
                    $productIds = $productType->getAssociatedProductIds($product);
                    $qty = $this->getMinQty($productIds);
                }
                break;
            case Configurable::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof Configurable) {
                    $productIds = $productType->getChildrenIds($product->getId());
                    if (isset($productIds[0]) && is_array($productIds[0])) {
                        $productIds = $productIds[0];
                    }
                    $qty = $this->getQtySum($productIds);
                }
                break;
            default:
                $qty += 1;
                break;
        }

        return $qty;
    }

    /**
     * @param array $products
     * @return int|mixed
     */
    private function getMinQty(array $products)
    {
        $quantities = array();
        $minQty = 0;

        foreach ($products as $product) {
            $p = $this->_productRepository->getById($product);
            $quantities[] = $this->getStock($p);
        }

        if (!empty($quantities)) {
            rsort($quantities, SORT_NUMERIC);
            $minQty = array_pop($quantities);
        }

        return $minQty;
//        $quantities = $this->stockProvider->getQuantitiesByIds($productIds, $website);
//        $minQty = 0;
//        if (!empty($quantities)) {
//            rsort($quantities, SORT_NUMERIC);
//            $minQty = array_pop($quantities);
//        }
//        return $minQty;
    }

    private function getQtySum($productIds)
    {
        $qty = 0;

        foreach ($productIds as $product) {
            $p = $this->_productRepository->getById($product);
            $qty += $this->getStock($p);
        }

        return $qty;
//        $qty = 0;
//        $quantities = $this->stockProvider->getQuantitiesByIds($productIds, $website);
//        foreach ($quantities as $quantity) {
//            $qty += $quantity;
//        }
//        return $qty;
    }

    /**
     * @param array $categories
     * @return string|array
     */
    public function getProductCategoryNamesById(array $categories = [])
    {
        if (!count($categories)) {
            return [];
        }
        $categoryNames = [];
        foreach ($categories as $categoryId) {

            if ($categoryId == 2) { // skip default category
                continue;
            }

            try {
                $category = $this->_categoryRepository->get($categoryId);
                $categoryNames[$categoryId] = $category->getName();
            } catch (NoSuchEntityException $e) {

            }
        }
        
        return $categoryNames;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getMediaGallery(Product $product) {

        $productGallery = $product->getMediaGalleryImages();

        $gallery = [];
        foreach ($productGallery as $key => $image) {

            if ($image->getDisabled() === 1) { // skip hidden image
                continue;
            }
            $gallery[] = $product->getMediaConfig()->getMediaUrl($image->getFile());
        }

       return $gallery;

    }

    /**
     * @param array $mediaGallery
     * @return array
     */
    public function mediaGalleryTransform(array $mediaGallery) {

        $formattedGallery = null;
        foreach ($mediaGallery as $key => $gallery) {

            if (is_array($gallery)) {
                foreach ($gallery as $image) {
                    $formattedGallery[] = $image;
                }
            } else {
                $formattedGallery[] = $gallery;
            }

        }

        return array_unique($formattedGallery);
    }


}
