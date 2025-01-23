<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Api\Model\ApplicationSource;
use Rvvup\Api\Model\Checkout;
use Rvvup\Api\Model\CheckoutCreateInput;
use Rvvup\Api\Model\MoneyInput;
use Rvvup\Payments\Gateway\Method;

class VirtualCheckout
{

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var Payment */
    private $paymentResource;

    /** @var Emulation */
    private $emulation;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var PaymentLink */
    private $paymentLinkService;

    /** @var UrlInterface */
    private $url;

    /** @var ApiProvider */
    private $apiProvider;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param Payment $paymentResource
     * @param Emulation $emulation
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param PaymentLink $paymentLinkService
     * @param UrlInterface $url
     * @param ApiProvider $apiProvider
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Payment                  $paymentResource,
        Emulation                $emulation,
        LoggerInterface          $logger,
        StoreManagerInterface    $storeManager,
        PaymentLink              $paymentLinkService,
        UrlInterface $url,
        ApiProvider  $apiProvider
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentResource = $paymentResource;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->paymentLinkService = $paymentLinkService;
        $this->url = $url;
        $this->apiProvider = $apiProvider;
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return Checkout
     * @throws \Exception
     */
    public function createVirtualCheckout(string $amount, string $storeId, string $orderId, string $currencyCode): Checkout
    {

        $checkoutInput = $this->buildRequestData($amount, $storeId, $orderId, $currencyCode);
        $result = $this->apiProvider->getSdk($storeId)->checkouts()->create($checkoutInput, $orderId);
        $motoId = $result->getId();
        $order = $this->orderRepository->get($orderId);
        $payment = $this->paymentLinkService->getQuotePaymentByOrder($order);
        $payment->setAdditionalInformation(Method::MOTO_ID, $motoId);
        $this->paymentResource->save($payment);
        return $result;
    }

    /**
     * @param string $virtualCheckoutId
     * @param string $storeId
     * @param OrderInterface $order
     * @return string|null
     */
    public function getRvvupIdByMotoId(string $virtualCheckoutId, string $storeId, OrderInterface $order): ?string
    {
        try {
            $paymentSessionId = $this->getPaymentSessionId($virtualCheckoutId, $storeId);
            $id = $this->getRvvupIdByPaymentSessionId($storeId, $virtualCheckoutId, $paymentSessionId, $order);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to get Rvvup Id by Moto Id',
                [
                    $virtualCheckoutId,
                    $order->getId(),
                    $storeId,
                    $e->getMessage()
                ]
            );
            return null;
        }

        return $id;
    }

    /**
     * @param int $orderId
     * @return string
     */
    public function getOrderViewUrl(int $orderId): string
    {
        $stores = $this->storeManager->getStores();
        $adminStoreId = 0;
        foreach ($stores as $store) {
            if ($store->getCode() === Store::ADMIN_CODE) {
                $adminStoreId = $store->getId();
            }
        }

        $this->emulation->startEnvironmentEmulation($adminStoreId, Area::AREA_ADMINHTML);
        $url = $this->url->getUrl(
            'sales/order/view',
            [
                'order_id' => $orderId,
                '_type' => UrlInterface::URL_TYPE_WEB,
                '_scope' => $adminStoreId
            ]
        );
        $this->emulation->stopEnvironmentEmulation();
        return $url;
    }

    /**
     * @param string $storeId
     * @param string $virtualCheckoutId
     * @param string $paymentSessionId
     * @param OrderInterface $order
     * @return mixed
     * @throws AlreadyExistsException
     * @throws \Exception
     */
    private function getRvvupIdByPaymentSessionId(
        string $storeId,
        string $virtualCheckoutId,
        string $paymentSessionId,
        OrderInterface $order
    ): string {
        $request = $this->apiProvider->getSdk($storeId)->paymentSessions()->get($virtualCheckoutId, $paymentSessionId);
        $id = $request->getId();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation(Method::ORDER_ID, $id);
        $this->paymentResource->save($payment);
        $this->orderRepository->save($order);
        return $id;
    }

    /**
     * @param string $virtualCheckoutId
     * @param string $storeId
     * @return string
     * @throws \Exception
     */
    private function getPaymentSessionId(string $virtualCheckoutId, string $storeId): string
    {
        return $this->apiProvider->getSdk($storeId)->checkouts()->get($virtualCheckoutId)->getPaymentSessionIds()[0];
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return CheckoutCreateInput
     */
    private function buildRequestData(string $amount, string $storeId, string $orderId, string $currencyCode): CheckoutCreateInput
    {
        $url = $this->url->getBaseUrl(['_scope' => $storeId, '_type' => UrlInterface::URL_TYPE_WEB])
            . "rvvup/redirect/in?store_id=$storeId&checkout_id={{CHECKOUT_ID}}";

        return (new CheckoutCreateInput())
            ->setSource(ApplicationSource::MAGENTO_MOTO)
            ->setAmount((new MoneyInput())->setAmount($amount)->setCurrency($currencyCode))
            ->setReference($orderId)
            ->setSuccessUrl($url)
            ->setPendingUrl($url);
    }
}
