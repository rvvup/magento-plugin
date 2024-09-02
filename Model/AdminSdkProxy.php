<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use GuzzleHttp\Client;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Sdk\GraphQlSdk;
use Rvvup\Sdk\GraphQlSdkFactory;

class AdminSdkProxy extends SdkProxy
{
    /** @var RequestInterface */
    private $request;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var InvoiceRepositoryInterface */
    private $invoiceRepository;

    /** @var Registry */
    private $registry;

    /** @var GraphQlSdk */
    private $subject;

    /**
     * @param ConfigInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param StoreManagerInterface $storeManager
     * @param GetEnvironmentVersionsInterface $getEnvironmentVersions
     * @param RequestInterface $request
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param Registry $registry
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        UserAgentBuilder $userAgent,
        GraphQlSdkFactory $sdkFactory,
        StoreManagerInterface $storeManager,
        GetEnvironmentVersionsInterface $getEnvironmentVersions,
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        Registry $registry,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->registry = $registry;
        parent::__construct(
            $config,
            $userAgent,
            $sdkFactory,
            $storeManager,
            $getEnvironmentVersions,
            $logger
        );
    }

    /**
     * @return int|null
     */
    private function getStoreIdUsed(): ?int
    {
        $storeId = null;
        $orderId = $this->request->getParam('order_id');
        if (!$orderId) {
            $creditMemo = $this->registry->registry('current_creditmemo');
            $orderId = $creditMemo ? $creditMemo->getOrderId() : null;
        }

        if ($orderId) {
            try {
                $order = $this->orderRepository->get((int) $orderId);
                $storeId = (int)$order->getStoreId();
                $this->storeManager->setCurrentStore($storeId);
            } catch (\Exception $e) {
                return $storeId;
            }
        } else if ($this->request->getParam('invoice_id')) {
            $invoiceId = $this->request->getParam('invoice_id');
            try {
                $invoice = $this->invoiceRepository->get((int) $invoiceId);
                $storeId = (int) $invoice->getStoreId();
                $this->storeManager->setCurrentStore($storeId);
            } catch (\Exception $e) {
                return $storeId;
            }
        }
        return $storeId;
    }

    /**
     * Get proxied instance
     *
     * @return GraphQlSdk
     * @throws NoSuchEntityException
     */
    protected function getSubject(): GraphQlSdk
    {
        $storeId = $this->getStoreIdUsed();
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        if (!isset($this->subject[$storeId])) {
            $endpoint = $this->config->getEndpoint();
            $merchant = $this->config->getMerchantId();
            $authToken = $this->config->getAuthToken();
            $debugMode = $this->config->isDebugEnabled();
            /** @var GraphQlSdk instance */
            $this->subject[$storeId] = $this->sdkFactory->create([
                'endpoint' => $endpoint,
                'merchantId' => $merchant,
                'authToken' => $authToken,
                'userAgent' => $this->userAgent->get(),
                'debug' => $debugMode,
                'adapter' => (new Client()),
                'logger' => $this->logger
            ]);
        }
        return $this->subject[$storeId];
    }
}
