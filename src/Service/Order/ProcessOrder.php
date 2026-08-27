<?php
declare(strict_types=1);

namespace Rvvup\Payments\Service\Order;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Service\Cache;
use Rvvup\Payments\Service\Card\CardMetaService;

/**
 * Shared order processing logic: fetch live Rvvup payment data and route the order to the
 * relevant processor based on the payment status.
 *
 * This is the single source of truth for the "fetch live data -> route to processor" flow and is
 * consumed by both the async webhook queue handler (Rvvup\Payments\Model\Queue\Handler\Handler)
 * and the reconciliation cron (Rvvup\Payments\Cron\ReconcilePendingOrders). The processors it
 * routes to (e.g. Complete) are idempotent, so it is safe to call more than once for the same order.
 */
class ProcessOrder
{
    /** @var PaymentDataGetInterface */
    private $paymentDataGet;

    /** @var ProcessorPool */
    private $processorPool;

    /** @var Payment */
    private $paymentResource;

    /** @var Cache */
    private $cacheService;

    /** @var CardMetaService */
    private $cardMetaService;

    /** @var LoggerInterface|Logger */
    private $logger;

    /**
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param Payment $paymentResource
     * @param Cache $cacheService
     * @param CardMetaService $cardMetaService
     * @param LoggerInterface $logger
     */
    public function __construct(
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        Payment $paymentResource,
        Cache $cacheService,
        CardMetaService $cardMetaService,
        LoggerInterface $logger
    ) {
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->paymentResource = $paymentResource;
        $this->cacheService = $cacheService;
        $this->cardMetaService = $cardMetaService;
        $this->logger = $logger;
    }

    /**
     * Fetch the live Rvvup payment data for the order and route it to the matching processor.
     *
     * @param OrderInterface $order
     * @param string $rvvupOrderId
     * @param string $rvvupPaymentId
     * @param string $origin
     * @param string $storeId
     * @return void
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function execute(
        OrderInterface $order,
        string $rvvupOrderId,
        string $rvvupPaymentId,
        string $origin,
        string $storeId
    ): void {
        // if Payment method is not Rvvup, exit.
        if (strpos($order->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
            if (strpos($order->getPayment()->getMethod(), RvvupConfigProvider::CODE) !== 0) {
                return;
            }
        }

        $rvvupData = $this->paymentDataGet->execute($rvvupOrderId, $storeId);
        if (empty($rvvupData) || !isset($rvvupData['payments'][0]['status'])) {
            $this->logger->error('Webhook error. Rvvup order data could not be fetched.', [
                    Method::ORDER_ID => $rvvupOrderId
                ]);
            return;
        }
        $payment = $order->getPayment();
        $dashboardUrl = $rvvupData['dashboardUrl'] ?? '';
        $payment->setAdditionalInformation(Method::ORDER_ID, $rvvupOrderId);
        $payment->setAdditionalInformation(Method::PAYMENT_ID, $rvvupPaymentId);
        $payment->setAdditionalInformation(Method::DASHBOARD_URL, $dashboardUrl);
        $this->cardMetaService->process($rvvupData['payments'][0], $order);
        $this->paymentResource->save($payment);
        $this->cacheService->clear($rvvupOrderId, $order->getState());
        if ($order->getPayment()->getMethod() == 'rvvup_payment-link') {
            $this->processorPool->getPaymentLinkProcessor($rvvupData['payments'][0]['status'])->execute(
                $order,
                $rvvupData,
                $origin
            );
        } else {
            $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])->execute(
                $order,
                $rvvupData,
                $origin
            );
        }
    }
}
