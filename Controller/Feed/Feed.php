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

namespace Retargeting\Tracker\Controller\Feed;

use Laminas\Db\Sql\Ddl\Column\Integer;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\Website;
use Magento\Tax\Api\TaxCalculationInterface;
use Retargeting\Tracker\Helper\Data;
use Retargeting\Tracker\Helper\PriceHelper;
use Retargeting\Tracker\Helper\StockHelper;
use Magento\Store\Model\Store;

/**
 * Class Feed
 * @package Retargeting\Tracker\Controller\Feed
 */
class Feed extends Action
{

    protected $stockState;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var
     */
    protected $productCollectionFactory;

    /**
     * @var
     */
    protected $productStatus;

    /**
     * @var
     */
    protected $productVisibility;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    protected $storeManager;

    protected $taxCalculation;

    protected $scopeConfig;
    protected $priceHelper;
    protected $retargetingData;
    protected $fileFactory;
    protected $directory;
    protected $stockHelper;


    /**
     * Feed constructor.
     * @param Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param PageFactory $resultPageFactory
     * @param StoreManager $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxCalculationInterface $taxCalculation
     * @param PriceHelper $priceHelper
     * @param Data $retargetingData
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     * @param StockHelper $retargetingStockHelper
     * @throws FileSystemException
     */
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        PageFactory $resultPageFactory,
        StoreManager $storeManager,
        ScopeConfigInterface $scopeConfig,
        TaxCalculationInterface $taxCalculation,
        PriceHelper $priceHelper,
        Data $retargetingData,
        FileFactory $fileFactory,
        Filesystem $filesystem,
        StockHelper $retargetingStockHelper,
        \Magento\Catalog\Model\ProductFactory $productFactory
    )
    {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->resultPageFactory = $resultPageFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->taxCalculation = $taxCalculation;
        $this->priceHelper = $priceHelper;
        $this->retargetingData = $retargetingData;
        $this->fileFactory = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::PUB);
        $this->stockHelper = $retargetingStockHelper;
        $this->productFactory = $productFactory;

    }
    private static $ids = [];
    private static $isExec = false;
    /**
     * Dispatch request
     *
     * @return ResultInterface|ResponseInterface
     * @throws Exception
     */
    public function getConfig($configPath, $scope = 'default')
    {
        return $this->scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $scope
        ) ?? 0;
    }

    public function execute()
    {
        if (!self::$isExec) {

            $name = date('m_d_Y_H_i_s');
            $filepath = 'retargeting'.$name.'.csv';
            //$this->directory->create('export');
            self::$isExec = true;

            $defStock = (int) $this->getConfig(\Retargeting\Tracker\Helper\Data::RETARGETING_DEFAULT_STOCK);

            $stream = $this->directory->openFile($filepath, 'w+');
            $stream->lock();

            $columns = [
                'product id',
                'product name',
                'product url',
                'image url',
                'stock',
                'price',
                'sale price',
                'brand',
                'category',
                'extra data'
            ];

            $stream->writeCsv($columns);

            $productPage = 1;
            $productLoopExit = false;
            $lastProductId = 0;
            $storeId = $this->storeManager->getStore()->getId();
            $store = $this->storeManager->getStore($storeId);

            while (!$productLoopExit) {
                $products = $this->getProducts($productPage);
                $productPage++;
                if (!count($products)
                    || (array_values(array_slice($products, -1))[0]->getId() == $lastProductId)
                ) {
                    $productLoopExit = true;
                } else {
                    foreach ($products as $product) {
                        if (in_array($product->getId(), self::$ids)) {
                            continue;
                        } else {
                            self::$ids[$product->getId()] = $product->getId();
                            $productType = $product->getTypeInstance();
                            $bundledItemIds = $productType->getChildrenIds($product->getId(), $required = true);

                            $stock = $this->stockHelper->getQuantity($product, $store);
                            
                            $stock = $stock < 0 ? $defStock : $stock;

                            /** @noinspection PhpParamsInspection */
                            $stream->writeCsv([
                                'product id' => $product->getId(),
                                'product name' => $product->getName(),
                                'product url' => $product->getProductUrl(false),
                                'image url' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
                                'stock' => $stock,
                                'price' => $this->priceHelper->getFullPrice($product),
                                'sale price' => $this->priceHelper->getProductPrice($product),
                                'brand' => '',
                                'category' => $this->retargetingData->getProductCategory($product->getCategoryIds()),
                                'extra data' => str_replace(['\"'], ["'"],
                                    json_encode($this->getExtraDataProduct($bundledItemIds, $store, $product->getId()), JSON_UNESCAPED_UNICODE)
                                )
                            ]);
                        }
                    }

                    $lastProductId = array_values(array_slice($products, -1))[0]->getId();
                }
            }

            
            $csvFilename = 'retargeting.csv';

            $content = [];
            $content['type'] = 'filename'; // must keep filename
            $content['value'] = $csvFilename;
            $content['rm'] = '0'; //remove csv from var folder

            $this->directory->renameFile($filepath, $csvFilename);
            
            return $this->fileFactory->create($csvFilename, $content, DirectoryList::PUB);
        }
    }

    /**
     * @param int $page
     * @param int $pageSize
     * @return ProductInterface[]
     */
    public function getProducts($page = 1, $pageSize = 100)
    {
        $this->searchCriteriaBuilder->addFilter(
            'type_id',
            [
                Product\Type::TYPE_SIMPLE,
                Product\Type::TYPE_VIRTUAL,
                ConfigurableType::TYPE_CODE
            ],
            'in'
        );

        $this->searchCriteriaBuilder->addFilter(
            'status',
            Status::STATUS_ENABLED
        );

        $this->searchCriteriaBuilder->addFilter(
            'visibility',
            Visibility::VISIBILITY_BOTH
        );

        $this->searchCriteriaBuilder->setCurrentPage($page);
        $this->searchCriteriaBuilder->setPageSize($pageSize);
        $searchCriteria = $this->searchCriteriaBuilder->create();

        return $this->productRepository->getList($searchCriteria)->getItems();
    }

    /**
     * @param array $productIds
     * @param Store $store
     * @param Integer $productId
     * @return array|null
     * @throws NoSuchEntityException
     */
    protected function getExtraDataProduct(array $productIds, Store $store, $productId) {

        try {
            $website = $store->getWebsite();
        } catch (NoSuchEntityException $e) {
            return null;
        }
        if ($website instanceof Website === false) {
            return null;
        }

        $stockItems = $this->stockHelper->getStockStatuses($productIds, $website);
        $parentProd = $this->productRepository->getById($productId);

        $extraData = [];
        $extraData['margin'] = null;
        $extraData['categories'] = $this->retargetingData->getProductCategoryNamesById($parentProd->getCategoryIds());
        $extraData['media_gallery'][] = $parentProd->getMediaConfig()->getMediaUrl($parentProd->getImage());
        $extraData['in_supplier_stock'] = null;

        foreach ($stockItems as $productId => $product) {


            $productCollection = $this->productRepository->getById($product->getId());

            $extraData['media_gallery'][] = $this->retargetingData->getMediaGallery($productCollection);
            $extraData['variations'][] = [
                'code' => $productCollection->getId(),
                'price' => $this->priceHelper->getFullPrice($productCollection),
                'sale_price' => $this->priceHelper->getProductPrice($productCollection),
                'stock' => $this->stockHelper->getQuantity($productCollection, $store),
                'margin' => null,
                'in_supplier_stock' => null
            ];


        }

        $extraData['media_gallery'] = $this->retargetingData->mediaGalleryTransform($extraData['media_gallery']);

        if (!isset($extraData['variations'])) {
            $extraData['variations'] = null;
        }

        return $extraData;

    }

}
