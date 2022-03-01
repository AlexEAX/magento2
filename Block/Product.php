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

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\View;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface as UrlEncoder;
use Magento\Store\Model\Store;
use Retargeting\Tracker\Helper\Data;
use Retargeting\Tracker\Helper\PriceHelper;
use Retargeting\Tracker\Helper\StockHelper;

//use Magento\Framework\Json\EncoderInterface as JsonEncoder;

/**
 * Class Product
 *
 * @package Retargeting\Tracker\Block
 *
 */
class Product extends View
{

    /**
     * Retargeting Helper Data
     * @var null|Data
     */
    protected $_retargetingData;

    /**
     * Category Repo
     * @var CategoryRepositoryInterface
     */
    protected $_categoryRepository;

    protected $_retargetingPriceHelper;
    protected $_retargetingStockHelper;

    /**
     * Product constructor.
     * @param Context $context
     * @param UrlEncoder $urlEncoder
     * @param EncoderInterface $jsonEncoder
     * @param StringUtils $string
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param ConfigInterface $productTypeConfig
     * @param FormatInterface $localeFormat
     * @param Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param Data $_retargetingData
     * @param PriceHelper $_retargetingPriceHelper
     * @param StockHelper $_retargetingStockHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlEncoder $urlEncoder,
        EncoderInterface $jsonEncoder,
        StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelper,
        ConfigInterface $productTypeConfig,
        FormatInterface $localeFormat,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        Data $_retargetingData,
        PriceHelper $_retargetingPriceHelper,
        StockHelper $_retargetingStockHelper,
        array $data = []
        ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );

        $this->_retargetingData = $_retargetingData;
        $this->_retargetingPriceHelper = $_retargetingPriceHelper;
        $this->_retargetingStockHelper = $_retargetingStockHelper;
        $this->_productRepository = $productRepository;
    }

    /**
     * @return false|string
     */
    public function sendProduct()
    {
        /* @var Store $store */
        try {
            $store = $this->_storeManager->getStore();
            return $this->buildSendProduct($this->getProduct(), $store);
        } catch (NoSuchEntityException $e) {
        } catch (\Exception $e) {
        }

        return json_encode(['error' => true]);
    }

    /**
     * Build SendProduct.
     * @param \Magento\Catalog\Model\Product $product
     * @param Store $store
     * @return string
     * @throws \Exception
     */
    protected function buildSendProduct(
        \Magento\Catalog\Model\Product $product,
        $store
    )
    {
        return json_encode([
            'id' => $product->getId(),
            'name' => $this->escapeHtml($product->getName()),
            'url' => $this->prepareUrl($product, $store),
            'img' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
            'price' => $this->_retargetingPriceHelper->getFullPrice($product),
            'promo' => $this->_retargetingPriceHelper->getProductPrice($product),
            'brand' => $this->getProductManufacturer($product),
            'inventory' => $this->prepareInventory($product, $store),
            'category' => $this->getCurrentCategory($product)
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Prepare product URL
     * @param \Magento\Catalog\Model\Product $product
     * @param Store $store
     * @return string
     */
    protected function prepareUrl($product, $store) {
        $options = [
            '_ignore_category' => true,
            '_nosid' => true,
            '_scope_to_url' => true,
            '_scope' => $store->getCode()
        ];
        return $product->getUrlInStore($options);
    }

    /**
     * Prepare sendBrand
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array|false
     */
    protected function getProductManufacturer($product)
    {
        return ($product->hasData('manufacturer') ? [
            'id' => $product->getManufacturer(),
            'name' => $product->getAttributeText('manufacturer')
        ] : false);
    }

    public function checkQty($qty) {
        return $qty < 0 ?
            $this->_retargetingData->getConfig(\Retargeting\Tracker\Helper\Data::RETARGETING_DEFAULT_STOCK) : $qty;
    }

    /**
     * Prepare inventory object
     * @param \Magento\Catalog\Model\Product $product
     * @param Store $store
     * @return array
     */
    protected function prepareInventory(\Magento\Catalog\Model\Product $product, Store $store) {

        return [
            'variations' => false,
            'stock' => $this->checkQty($this->_retargetingStockHelper->getQuantity($product, $store))
        ];
    }

    /**
     * Get CurrentCategory
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    protected function getCurrentCategory($product)
    {
        $categories = [];
        foreach ($product->getCategoryCollection() as $category) {

            $buildCategory = $this->_retargetingData->buildCategory($category);

            if (!empty($buildCategory)) {
                $categories[] = $buildCategory;
            }
        }

        return $categories;
    }
}
