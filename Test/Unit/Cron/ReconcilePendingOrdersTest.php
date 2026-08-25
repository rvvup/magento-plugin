<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Cron\ReconcilePendingOrders;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Service\Order\ProcessOrder;

class ReconcilePendingOrdersTest extends TestCase
{
    /** @var OrderCollectionFactory|MockObject */
    private $collectionFactory;
    /** @var ProcessOrder|MockObject */
    private $processOrderService;
    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfig;
    /** @var DateTime|MockObject */
    private $dateTime;
    /** @var Emulation|MockObject */
    private $emulation;
    /** @var Logger|MockObject */
    private $logger;
    /** @var Collection|MockObject */
    private $collection;
    /** @var ReconcilePendingOrders */
    private $cron;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->getMockBuilder(OrderCollectionFactory::class)
            ->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $this->processOrderService = $this->createMock(ProcessOrder::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->emulation = $this->createMock(Emulation::class);
        $this->logger = $this->createMock(Logger::class);

        $select = $this->createMock(Select::class);
        $select->method('join')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $this->collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSelect', 'getTable', 'addFieldToFilter', 'getItems'])
            ->getMock();
        $this->collection->method('getSelect')->willReturn($select);
        $this->collection->method('getTable')->willReturn('sales_order_payment');
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $this->collectionFactory->method('create')->willReturn($this->collection);

        $this->dateTime->method('gmtTimestamp')->willReturn(1700000000);
        $this->dateTime->method('gmtDate')->willReturn('2023-11-14 00:00:00');

        $this->cron = new ReconcilePendingOrders(
            $this->collectionFactory,
            $this->processOrderService,
            $this->scopeConfig,
            $this->dateTime,
            $this->emulation,
            $this->logger
        );
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $this->scopeConfig->method('isSetFlag')->with(ReconcilePendingOrders::XML_PATH_ENABLED)->willReturn(false);

        $this->collectionFactory->expects($this->never())->method('create');
        $this->processOrderService->expects($this->never())->method('execute');

        $this->cron->execute();
    }

    public function testProcessesStuckOrderWithinStoreEmulation(): void
    {
        $this->enable();
        $order = $this->createOrder('OR123', 'PA123', 5, 42);
        $this->collection->method('getItems')->willReturn([$order]);

        $this->emulation->expects($this->once())->method('startEnvironmentEmulation')->with(5);
        $this->emulation->expects($this->once())->method('stopEnvironmentEmulation');
        $this->processOrderService->expects($this->once())
            ->method('execute')
            ->with($order, 'OR123', 'PA123', 'reconcile-cron', '5');

        $this->cron->execute();
    }

    public function testSkipsOrderWithoutRvvupOrderId(): void
    {
        $this->enable();
        $order = $this->createOrder('', '', 5, 42);
        $this->collection->method('getItems')->willReturn([$order]);

        $this->processOrderService->expects($this->never())->method('execute');

        $this->cron->execute();
    }

    public function testOneFailingOrderDoesNotAbortBatch(): void
    {
        $this->enable();
        $bad = $this->createOrder('OR1', 'PA1', 1, 11);
        $good = $this->createOrder('OR2', 'PA2', 1, 12);
        $this->collection->method('getItems')->willReturn([$bad, $good]);

        $this->processOrderService->method('execute')->willReturnCallback(
            function ($order, $rvvupOrderId) {
                if ($rvvupOrderId === 'OR1') {
                    throw new \RuntimeException('boom');
                }
            }
        );

        // Failure is logged for the bad order...
        $this->logger->expects($this->once())
            ->method('addRvvupError')
            ->with(
                'Failed to reconcile pending Rvvup order via cron',
                'boom',
                'OR1',
                'PA1',
                '11',
                'reconcile-cron'
            );
        // ...and emulation is always stopped (once per order).
        $this->emulation->expects($this->exactly(2))->method('stopEnvironmentEmulation');
        // ...and the good order is still processed.
        $this->processOrderService->expects($this->exactly(2))->method('execute');

        $this->cron->execute();
    }

    private function enable(): void
    {
        $this->scopeConfig->method('isSetFlag')->with(ReconcilePendingOrders::XML_PATH_ENABLED)->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn('50');
    }

    /**
     * @return OrderInterface|MockObject
     */
    private function createOrder(string $rvvupOrderId, string $rvvupPaymentId, int $storeId, int $entityId)
    {
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getAdditionalInformation')->willReturnMap([
            [Method::ORDER_ID, $rvvupOrderId],
            [Method::PAYMENT_ID, $rvvupPaymentId],
        ]);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getEntityId')->willReturn($entityId);

        return $order;
    }
}
