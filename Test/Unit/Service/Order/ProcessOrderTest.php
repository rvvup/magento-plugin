<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment as PaymentResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Service\Cache;
use Rvvup\Payments\Service\Card\CardMetaService;
use Rvvup\Payments\Service\Order\ProcessOrder;

class ProcessOrderTest extends TestCase
{
    /** @var PaymentDataGetInterface|MockObject */
    private $paymentDataGet;
    /** @var ProcessorPool|MockObject */
    private $processorPool;
    /** @var PaymentResource|MockObject */
    private $paymentResource;
    /** @var Cache|MockObject */
    private $cacheService;
    /** @var CardMetaService|MockObject */
    private $cardMetaService;
    /** @var Logger|MockObject */
    private $logger;
    /** @var ProcessOrder */
    private $service;

    protected function setUp(): void
    {
        $this->paymentDataGet = $this->createMock(PaymentDataGetInterface::class);
        $this->processorPool = $this->createMock(ProcessorPool::class);
        $this->paymentResource = $this->createMock(PaymentResource::class);
        $this->cacheService = $this->createMock(Cache::class);
        $this->cardMetaService = $this->createMock(CardMetaService::class);
        $this->logger = $this->createMock(Logger::class);

        $this->service = new ProcessOrder(
            $this->paymentDataGet,
            $this->processorPool,
            $this->paymentResource,
            $this->cacheService,
            $this->cardMetaService,
            $this->logger
        );
    }

    public function testSkipsNonRvvupOrders(): void
    {
        $order = $this->createOrderMock('checkmo');

        $this->paymentDataGet->expects($this->never())->method('execute');
        $this->processorPool->expects($this->never())->method('getProcessor');

        $this->service->execute($order, 'OR123', 'PA123', 'reconcile-cron', '1');
    }

    public function testLogsAndReturnsWhenRvvupDataMissingStatus(): void
    {
        $order = $this->createOrderMock('rvvup_payment-card');
        $this->paymentDataGet->method('execute')->with('OR123', '1')->willReturn([]);

        $this->logger->expects($this->once())->method('error');
        $this->processorPool->expects($this->never())->method('getProcessor');

        $this->service->execute($order, 'OR123', 'PA123', 'reconcile-cron', '1');
    }

    public function testRoutesToStandardProcessor(): void
    {
        $order = $this->createOrderMock('rvvup_payment-card');
        $rvvupData = ['payments' => [['status' => 'SUCCEEDED', 'id' => 'PA123']], 'dashboardUrl' => 'http://x'];
        $this->paymentDataGet->method('execute')->with('OR123', '1')->willReturn($rvvupData);

        $processor = $this->createMock(ProcessorInterface::class);
        $this->processorPool->expects($this->once())
            ->method('getProcessor')
            ->with('SUCCEEDED')
            ->willReturn($processor);
        $this->processorPool->expects($this->never())->method('getPaymentLinkProcessor');
        $processor->expects($this->once())->method('execute')->with($order, $rvvupData, 'reconcile-cron');
        $this->paymentResource->expects($this->once())->method('save');

        $this->service->execute($order, 'OR123', 'PA123', 'reconcile-cron', '1');
    }

    public function testRoutesToPaymentLinkProcessor(): void
    {
        $order = $this->createOrderMock('rvvup_payment-link');
        $rvvupData = ['payments' => [['status' => 'SUCCEEDED', 'id' => 'PA123']]];
        $this->paymentDataGet->method('execute')->with('OR123', '1')->willReturn($rvvupData);

        $processor = $this->createMock(ProcessorInterface::class);
        $this->processorPool->expects($this->once())
            ->method('getPaymentLinkProcessor')
            ->with('SUCCEEDED')
            ->willReturn($processor);
        $this->processorPool->expects($this->never())->method('getProcessor');
        $processor->expects($this->once())->method('execute')->with($order, $rvvupData, 'reconcile-cron');
        $this->paymentResource->expects($this->once())->method('save');

        $this->service->execute($order, 'OR123', 'PA123', 'reconcile-cron', '1');
    }

    /**
     * @param string $method
     * @return OrderInterface|MockObject
     */
    private function createOrderMock(string $method)
    {
        $payment = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod', 'setAdditionalInformation'])
            ->getMock();
        $payment->method('getMethod')->willReturn($method);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn('pending_payment');

        return $order;
    }
}
