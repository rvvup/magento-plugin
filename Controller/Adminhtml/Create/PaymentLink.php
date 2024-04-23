<?php
declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Create;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Service\PaymentLink as PaymentLinkService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class PaymentLink extends Action implements HttpPostActionInterface
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var PaymentLinkService */
    private $paymentLinkService;

    /** @var ConfigInterface */
    private $config;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param PaymentLinkService $paymentLinkService
     * @param ConfigInterface $config
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PaymentLinkService $paymentLinkService,
        ConfigInterface $config,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentLinkService = $paymentLinkService;
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $amount = (float)$this->_request->getParam('amount');
        $amount = (float)number_format($amount, 2, '.', '');
        $storeId = $this->_request->getParam('store_id');
        $orderId = $this->_request->getParam('order_id');
        $currencyCode = $this->_request->getParam('currency_code');
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isActive(ScopeInterface::SCOPE_STORE, $storeId)) {
            $result->setData([
                'success' => false,
                'message' => 'Rvvup is disabled in that store view'
            ]);
            return $result;
        }

        if ($amount > 0) {
            try {
                $order = $this->orderRepository->get($orderId);
                $body = $this->paymentLinkService->createPaymentLink($storeId, $amount, $orderId, $currencyCode);
                $message = $this->config->getPayByLinkText(ScopeInterface::SCOPE_STORE, $storeId) . PHP_EOL . $body['url'];
                $this->paymentLinkService->addCommentToOrder($order->getStatus(), $orderId, $message);
                $this->paymentLinkService->savePaymentLink($order->getPayment(), $body['id'], $message);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create Rvvup payment link from order_view page', [
                    $amount,
                    $storeId,
                    $orderId,
                    $currencyCode,
                    $e->getMessage()
                ]);

                $result->setData([
                    'success' => false
                ]);
                return $result;
            }
        } else {
            $result->setData([
                'success' => false,
                'message' => 'Amount is empty!'
            ]);
            return $result;
        }

        $result->setData([
            'success' => true
        ]);

        return $result;
    }
}
