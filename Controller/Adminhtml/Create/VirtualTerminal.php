<?php
declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Create;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Service\VirtualCheckout;

class VirtualTerminal extends Action implements HttpPostActionInterface
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var VirtualCheckout */
    private $virtualCheckoutService;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param VirtualCheckout $virtualCheckoutService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        VirtualCheckout $virtualCheckoutService,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->virtualCheckoutService = $virtualCheckoutService;
        $this->logger = $logger;
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
        $result = $this->resultJsonFactory->create();

        try {
            $body = $this->virtualCheckoutService->createVirtualCheckout($amount, $storeId, $orderId, $currencyCode);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Rvvup virtual checkout', [$e->getMessage()]);
            $result->setData([
                'success' => false
            ]);
            return $result;
        }

        $result->setData([
            'iframe-url' => $body['url'],
            'success' => true
        ]);

        return $result;
    }
}
