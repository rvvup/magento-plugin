<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\App\Emulation;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Service\Order\ProcessOrder;

/**
 * Self-healing reconciliation cron.
 *
 * Recovers Rvvup orders that are stuck in `pending_payment` due to the webhook race condition:
 * when PAYMENT_AUTHORIZED and PAYMENT_COMPLETED are published to the message queue in the same
 * cron window and PAYMENT_COMPLETED is consumed before PAYMENT_AUTHORIZED finishes creating the
 * order, getOrderByRvvupId() finds nothing, throws PaymentValidationException, and the exception
 * is swallowed — leaving the order permanently stuck.
 *
 * For each stuck order it fetches the live Rvvup payment status and routes the order through the
 * same processing logic used by the webhook queue handler
 * ({@see \Rvvup\Payments\Service\Order\ProcessOrder}). The processors it routes to are idempotent
 * (see Complete::isProcessed() and Processing::changeNewOrderStatus()), so running this alongside
 * the normal webhook flow is harmless.
 */
class ReconcilePendingOrders
{
    /** Config path: whether the reconciliation cron is enabled. */
    public const XML_PATH_ENABLED = 'payment/rvvup/order_reconciliation/enabled';

    /** Config path: maximum number of orders to process per cron run. */
    public const XML_PATH_BATCH_SIZE = 'payment/rvvup/order_reconciliation/batch_size';

    /** Config path: how far back (in hours) to look for stuck orders. */
    public const XML_PATH_LOOKBACK_HOURS = 'payment/rvvup/order_reconciliation/lookback_hours';

    /** Do not touch orders newer than this (seconds) to avoid racing the normal webhook flow. */
    private const MIN_AGE_SECONDS = 300;

    /** Fallback batch size if config is missing/invalid. */
    private const DEFAULT_BATCH_SIZE = 50;

    /** Fallback lookback window if config is missing/invalid. */
    private const DEFAULT_LOOKBACK_HOURS = 48;

    /** Origin marker recorded against any errors raised during reconciliation. */
    private const ORIGIN = 'reconcile-cron';

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /** @var ProcessOrder */
    private $processOrderService;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var DateTime */
    private $dateTime;

    /** @var Emulation */
    private $emulation;

    /** @var Logger */
    private $logger;

    /**
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param ProcessOrder $processOrderService
     * @param ScopeConfigInterface $scopeConfig
     * @param DateTime $dateTime
     * @param Emulation $emulation
     * @param Logger $logger
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        ProcessOrder $processOrderService,
        ScopeConfigInterface $scopeConfig,
        DateTime $dateTime,
        Emulation $emulation,
        Logger $logger
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->processOrderService = $processOrderService;
        $this->scopeConfig = $scopeConfig;
        $this->dateTime = $dateTime;
        $this->emulation = $emulation;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            return;
        }

        foreach ($this->getStuckOrders()->getItems() as $order) {
            /** @var OrderInterface $order */
            $this->reconcileOrder($order);
        }
    }

    /**
     * Reconcile a single order in isolation so one failure cannot abort the batch.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function reconcileOrder(OrderInterface $order): void
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }

        $rvvupOrderId = (string) $payment->getAdditionalInformation(Method::ORDER_ID);
        $rvvupPaymentId = (string) $payment->getAdditionalInformation(Method::PAYMENT_ID);
        if ($rvvupOrderId === '') {
            return;
        }

        $storeId = (string) $order->getStoreId();
        if ($storeId === '' || $storeId === '0') {
            return;
        }

        $this->emulation->startEnvironmentEmulation((int) $storeId);
        try {
            $this->processOrderService->execute($order, $rvvupOrderId, $rvvupPaymentId, self::ORIGIN, $storeId);
        } catch (\Exception $e) {
            $this->logger->addRvvupError(
                'Failed to reconcile pending Rvvup order via cron',
                $e->getMessage(),
                $rvvupOrderId,
                $rvvupPaymentId,
                (string) $order->getEntityId(),
                self::ORIGIN
            );
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Build the bounded, indexed selection of stuck Rvvup orders.
     *
     * Selection criteria:
     *  - order state = pending_payment
     *  - payment method starts with the Rvvup prefix
     *  - payment carries a rvvup_order_id in additional_information
     *  - created_at older than MIN_AGE_SECONDS (avoid racing the normal flow)
     *  - created_at within the configurable lookback window
     *  - limited to the configurable batch size
     *
     * @return Collection
     */
    private function getStuckOrders(): Collection
    {
        $now = $this->dateTime->gmtTimestamp();
        $olderThan = $this->dateTime->gmtDate('Y-m-d H:i:s', $now - self::MIN_AGE_SECONDS);
        $lookbackStart = $this->dateTime->gmtDate('Y-m-d H:i:s', $now - ($this->getLookbackHours() * 3600));

        /** @var Collection $collection */
        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()->join(
            ['sop' => $collection->getTable('sales_order_payment')],
            'main_table.entity_id = sop.parent_id',
            []
        );
        $collection->addFieldToFilter('main_table.state', Order::STATE_PENDING_PAYMENT);
        $collection->addFieldToFilter('sop.method', ['like' => RvvupConfigProvider::CODE . '%']);
        $collection->addFieldToFilter(
            'sop.additional_information',
            ['like' => '%' . Method::ORDER_ID . '%']
        );
        $collection->addFieldToFilter('main_table.created_at', ['lteq' => $olderThan]);
        $collection->addFieldToFilter('main_table.created_at', ['gteq' => $lookbackStart]);
        $collection->getSelect()->order('main_table.created_at ASC');
        $collection->getSelect()->limit($this->getBatchSize());

        return $collection;
    }

    /**
     * @return int
     */
    private function getBatchSize(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE);

        return $value > 0 ? $value : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * @return int
     */
    private function getLookbackHours(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_LOOKBACK_HOURS);

        return $value > 0 ? $value : self::DEFAULT_LOOKBACK_HOURS;
    }
}
