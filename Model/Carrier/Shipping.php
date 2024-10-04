<?php
namespace Eleanorsoft\ShippingBySubtotal\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class Shipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    protected $_code = 'shipping_by_subtotal';

    protected $_rateResultFactory;

    protected $_rateMethodFactory;

    public $_checkoutSession;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @return float
     */
    private function getShippingMinRate()
    {
        return $this->getConfigData('minimum_rate') ?? 0;
    }

    /**
     * @return float
     */
    private function getShippingMaxRate()
    {
        return $this->getConfigData('maximum_rate') ?? 0;
    }

    /**
     * @return float
     */
    private function getShippingFee()
    {
        return $this->getConfigData('handling_fee') ?? 0;
    }

    /**
     * @return float
     */
    private function getShippingSubtotalPerent()
    {
        return $this->getConfigData('subtotal_percent') ?? 0;
    }


    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $getCurrentQuote = $this->_checkoutSession->getQuote();

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $amount = min(
            $this->getShippingMaxRate(),
            max(
                $this->getShippingMinRate(),
                ($this->getShippingFee() + $getCurrentQuote->getSubtotal() * ($this->getShippingSubtotalPerent() / 100))
            )
        );

        $method->setPrice($amount);
        $method->setCost($amount);

        $result->append($method);

        return $result;
    }
}
