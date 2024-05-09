<?php
declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Create;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Service\VirtualCheckout;

class VirtualTerminal extends Action implements HttpPostActionInterface
{
    /** @var VirtualCheckout */
    private $virtualCheckoutService;

    /** @var LoggerInterface */
    private $logger;

    /** @var ConfigInterface */
    private $config;

    /**
     * @param Context $context
     * @param VirtualCheckout $virtualCheckoutService
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        VirtualCheckout $virtualCheckoutService,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->virtualCheckoutService = $virtualCheckoutService;
        $this->logger = $logger;
        $this->config = $config;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $amount = $this->_request->getParam('amount');
        $storeId = $this->_request->getParam('store_id');
        $orderId = $this->_request->getParam('order_id');
        $currencyCode = $this->_request->getParam('currency_code');
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$this->config->isActive(ScopeInterface::SCOPE_STORE, $storeId)) {
            $result->setData([
                'success' => false,
                'message' => 'Rvvup is disabled in that store view'
            ]);
            return $result;
        }

        try {
            $body = $this->virtualCheckoutService->createVirtualCheckout($amount, $storeId, $orderId, $currencyCode);
            $result->setData([
                'iframe-url' => $body['url'],
                'success' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Rvvup virtual checkout', [$e->getMessage()]);
            $result->setData([
                'success' => false
            ]);
        }

        return $result;
    }
}
