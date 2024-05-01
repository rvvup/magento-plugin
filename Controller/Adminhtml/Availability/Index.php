<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Availability;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;

class Index extends Action implements HttpPostActionInterface
{
    /** @var ConfigInterface */
    private $config;

    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param ConfigInterface $config
     * @param SdkProxy $sdkProxy
     * @param Context $context
     */
    public function __construct(
        ConfigInterface $config,
        SdkProxy $sdkProxy,
        Context $context
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->config = $config;
        parent::__construct($context);
    }
    /**
     * @return Json
     */
    public function execute()
    {
        $amount = (float)$this->_request->getParam('amount');
        $amount = number_format($amount, 2, '.', '');
        $method = $this->_request->getParam('method');
        $storeId = $this->_request->getParam('store_id');
        $currencyCode = $this->_request->getParam('currency_code');
        $allowedPaymentLinksMethods = ['YAPILY','CARD','APPLE_PAY'];

        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$this->config->isActive(ScopeInterface::SCOPE_STORE, $storeId)) {
            $result->setData([
                'available' => false,
            ]);
            return $result;
        }
        $methods = $this->sdkProxy->getMethods($amount, $currencyCode);

        if ($method == 'rvvup_payment-link') {
            foreach ($methods as $method) {
                if (in_array($method['name'], $allowedPaymentLinksMethods)) {
                    $result->setData([
                        'available' => true,
                    ]);
                    return $result;
                }
            }
        } elseif ($method == 'rvvup_virtual-terminal') {
            foreach ($methods as $method) {
                if (isset($method['settings']['motoEnabled']) && $method['settings']['motoEnabled']) {
                    $result->setData([
                        'available' => true,
                    ]);
                    return $result;
                }
            }
        }
        $result->setData([
            'available' => false,
        ]);

        return $result;
    }
}
