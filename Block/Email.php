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

use DateTime;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Customer\Model\Session;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Email
 * @package Retargeting\Tracker\Block
 */
class Email extends Template implements SectionSourceInterface
{
    const RA_SET_EMAIL_GENDER_MAN = 1;
    const RA_SET_EMAIL_GENDER_WOMAN = 0;

    /*
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /*
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * Constructor
     *
     * @param CurrentCustomer $currentCustomer
     * @param CookieManagerInterface $cookieManager
     * @param Context $context
     * @param Session $customerSession
     * @param array $data
     */
    public function __construct(
        CurrentCustomer $currentCustomer,
        CookieManagerInterface $cookieManager,
        Context $context,
        Session $customerSession,
        $data = []
    )
    {
        $this->currentCustomer = $currentCustomer;
        $this->cookieManager = $cookieManager;
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function getSectionData()
    {
        $data = [];
        if ($this->currentCustomer instanceof CurrentCustomer
            && $this->currentCustomer->getCustomerId()
        ) {
            $customer = $this->currentCustomer->getCustomer();
            $data = [
                'email' => $customer->getEmail(),
                'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                'birthday' => $this->prepareDob($customer->getDob()),
                'sex' => $this->prepareGender($customer->getGender()),
            ];
        }

        if (empty($data)) {
            return false;
        }

        return json_encode($data);
    }

    /**
     * @param $dob
     * @return string
     * @throws \Exception
     * @link https://retargeting.biz/documentation/index.html#setEmail
     */
    protected function prepareDob($dob)
    {
        if ($dob === null) {
            return '';
        }
        $formattedDate = new DateTime($dob);
        return $formattedDate->format('d-m-Y');
    }

    /**
     * @param $gender
     * @return int|string
     */
    protected function prepareGender($gender)
    {
        switch ($gender) {
            case 1:
                return 1;
                break;
            case 2:
                return 2;
                break;
            default:
                return '';
        }
    }

    /**
     * @return Template
     */
    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }
}
