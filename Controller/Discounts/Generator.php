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

namespace Retargeting\Tracker\Controller\Discounts;

use Magento\Customer\Model\Group;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\SalesRule\Helper\Coupon;
use Magento\SalesRule\Model\Coupon\Codegenerator;
use Magento\SalesRule\Model\Coupon\Massgenerator;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\ScopeInterface as StoreModelInterface;
use Magento\Store\Model\StoreManager;
use Retargeting\Tracker\Helper\Data;

/**
 * Class Generator
 * @package Retargeting\Tracker\Controller\Discounts
 */
class Generator extends Action
{
    /**
     *
     */
    const RETARGETING_RULE_NAME = 'Retargeting Discounts - ';


    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    protected $discountValue = null;
    protected $discountType = null;
    protected $discountCount = null;
    protected $token = null;

    /**
     * @var Group
     */
    protected $customerGroup;

    /**
     * @var Rule
     */
    protected $discountRule;

    /**
     * @var Massgenerator
     */
    protected $massgenerator;

    /**
     * @var Codegenerator
     */
    protected $codegenerator;

    protected $scope;


    /**
     * Generator constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Data $helper
     * @param StoreManager $storeManager
     * @param Group $group
     * @param Rule $rule
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $helper,
        StoreManager $storeManager,
        Group $group,
        Rule $rule,
        ScopeConfigInterface $scopeConfig
    )
    {
        parent::__construct($context);
        $this->_objectManager = $context->getObjectManager();
        $this->helper = $helper;
        $this->resultPageFactory = $resultPageFactory;
        $this->storeManager = $storeManager;
        $this->customerGroup = $group;
        $this->discountRule = $rule;
        $this->scope = $scopeConfig;
    }

    /**
     * Dispatch request
     *
     * @return ResultInterface|ResponseInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHeader('Content-Type', 'application/json');

        if ($this->validateRequestParams() !== true) {
            return $result->setData(['Error!']);
        }
        return $result->setData($this->createNewCoupon());
    }

    /**
     * @return array|bool
     */
    protected function validateRequestParams()
    {
        if ($this->helper->isEnabled() === false) {
            return ['Retargeting Module is disabled!'];
        }

        $params = $this->getRequest()->getParams();

        $raKey = $this->scope->getValue(
            Data::RETARGETING_REST_API,
            StoreModelInterface::SCOPE_STORE
        );

        if (!isset($params['key']) ||
            !isset($params['value']) ||
            !isset($params['type']) ||
            !isset($params['count'])
        ) {
            return ['Missing parameters!'];
        }

        if ($params['key'] == '' ||
            $params['value'] == '' ||
            $params['type'] == '' ||
            $params['count'] == ''
        ) {
            return ['Missing parameters!'];
        }

        if (!is_numeric($params['count']) ||
            !is_numeric($params['type']) ||
            !is_numeric($params['value'])
        ) {
            return ['count parameter must be an integer!'];
        }

        if ($params['count'] > 1000) {
            return ['The max batch size reached'];
        }

        if ($params['key'] != $raKey) {
            return ['Invalid Token!'];
        }

        if ($params['type'] > 1) {
            return ['Discount Code Type not Supported!'];
        }

        switch ($params['type']) {
            case 0:
                $this->discountType = Rule::BY_FIXED_ACTION;
                break;
            case 1:
            default:
                $this->discountType = Rule::BY_PERCENT_ACTION;
                break;
        }

        $this->discountValue = htmlspecialchars($params['value']);
        $this->token = htmlspecialchars($params['key']);
        $this->discountCount = htmlspecialchars($params['count']);

        return true;
    }

    /**
     * @return array|string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function createNewCoupon()
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $fromDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime($fromDate . ' + 360 day'));

        $allGroups = $this->customerGroup->getCollection()->toOptionHash();
        $customerGroupIds = [];

        foreach ($allGroups as $groupId => $name) {
            $customerGroupIds[] = $groupId;
        }
        /** @noinspection PhpParamsInspection */
        $rule = $this->discountRule
            ->setName(self::RETARGETING_RULE_NAME . $this->discountValue . ' - ' . $this->discountType)
            ->setDescription(self::RETARGETING_RULE_NAME . $this->discountValue . ' - ' . $this->discountType)
            ->setStopRulesProcessing(0)
            ->setFromDate($fromDate)
            ->setToDate($endDate)
            ->setIsActive(1)
            ->setUsesPerCoupon(1)
            ->setUsesPerCustomer(1)
            ->setCustomerGroupIds($customerGroupIds)
            ->setProductIds('')
            ->setSortOrder(0)
            ->setSimpleAction($this->discountType)
            ->setDiscountAmount($this->discountValue)
            ->setDiscountQty(0)
            ->setDiscountStep(0)
            ->setApplyToShipping(0)
            ->setIsRss(0)
            ->setWebsiteIds($websiteId)
            ->setUseAutoGeneration(true);

        $rule->save();

        $codes = [];
        for ($i = 0; $i < $this->discountCount; $i++) {
            /**
             * @var $coupon Rule
             */
            $coupon = $rule->acquireCoupon(true);
            /** @noinspection PhpUndefinedMethodInspection */
            $coupon->setType(Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED);
            $coupon->save();
            /** @noinspection PhpUndefinedMethodInspection */
            $code = strtoupper($coupon->getCode());
            $codes[] = $code;
        }
        $rule->setCouponType(2);
        $rule->save();

        return $codes;
    }
}
