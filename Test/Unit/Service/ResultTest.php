<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\ResourceModel\Order\Payment;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Service\Card\CardMetaService;
use Rvvup\Payments\Service\Result;

class ResultTest extends TestCase
{
    private const ORDER_ID = '1';
    private const RVVUP_ID = 'OR01KYWGKG0EYE6BHPJGZBBHM8ZY';
    private const STORE_ID = '1';
    private const ORIGIN = 'customer-flow';

    /** @var ProcessorPool|MockObject */
    private $processorPoolMock;

    /** @var PaymentDataGetInterface|MockObject */
    private $paymentDataGetMock;

    /** @var Logger|MockObject */
    private $loggerMock;

    /** @var Order|MockObject */
    private $orderMock;

    /** @var Result */
    private $result;

    protected function setUp(): void
    {
        $this->processorPoolMock = $this->createMock(ProcessorPool::class);
        $this->paymentDataGetMock = $this->createMock(PaymentDataGetInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getPayment')->willReturn($this->createMock(OrderPayment::class));

        $orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $orderRepositoryMock->method('get')->willReturn($this->orderMock);

        $redirectMock = $this->createMock(Redirect::class);
        $redirectMock->method('setPath')->willReturnSelf();

        $resultFactoryMock = $this->createMock(ResultFactory::class);
        $resultFactoryMock->method('create')->willReturn($redirectMock);

        $this->result = new Result(
            $resultFactoryMock,
            $this->createMock(SessionManagerInterface::class),
            $orderRepositoryMock,
            $this->processorPoolMock,
            $this->paymentDataGetMock,
            $this->createMock(Order::class),
            $this->createMock(Emulation::class),
            $this->loggerMock,
            $this->createMock(Payment::class),
            $this->createMock(CardMetaService::class)
        );
    }

    public function testSkipsTheOrderStatusProcessorWhenThePoolDoesNotKnowTheStatus(): void
    {
        $this->givenRvvupData('CREATED', 'PENDING');
        $this->processorPoolMock->method('findProcessor')->with('CREATED')->willReturn(null);

        $this->processorPoolMock->expects($this->once())
            ->method('getProcessor')
            ->with('PENDING')
            ->willReturn($this->aProcessor());

        $this->loggerMock->expects($this->never())->method('addRvvupError');
        $this->orderMock->expects($this->never())->method('addStatusToHistory');

        $this->whenTheOrderResultIsProcessed();
    }

    public function testRunsTheOrderStatusProcessorWhenThePoolKnowsTheStatus(): void
    {
        $this->givenRvvupData('FAILED', 'PENDING');

        $requestedStatuses = [];
        $collectStatus = function (string $status) use (&$requestedStatuses): ProcessorInterface {
            $requestedStatuses[] = $status;
            return $this->aProcessor();
        };
        $this->processorPoolMock->method('findProcessor')->willReturnCallback($collectStatus);
        $this->processorPoolMock->method('getProcessor')->willReturnCallback($collectStatus);

        $this->whenTheOrderResultIsProcessed();

        $this->assertSame(['FAILED', 'PENDING'], $requestedStatuses);
    }

    public function testSkipsTheOrderStatusProcessorWhenThePaymentIsAuthorized(): void
    {
        $this->givenRvvupData('CREATED', 'AUTHORIZED');
        $this->processorPoolMock->expects($this->never())->method('findProcessor');

        $this->processorPoolMock->expects($this->once())
            ->method('getProcessor')
            ->with('AUTHORIZED')
            ->willReturn($this->aProcessor());

        $this->whenTheOrderResultIsProcessed();
    }

    private function givenRvvupData(string $orderStatus, string $paymentStatus): void
    {
        $this->paymentDataGetMock->method('execute')->willReturn([
            'id' => self::RVVUP_ID,
            'status' => $orderStatus,
            'dashboardUrl' => '',
            'payments' => [['id' => 'PA01', 'status' => $paymentStatus]],
        ]);
    }

    private function aProcessor(): ProcessorInterface
    {
        $processOrderResult = $this->createMock(ProcessOrderResultInterface::class);
        $processOrderResult->method('getRedirectPath')->willReturn(In::SUCCESS);

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('execute')->willReturn($processOrderResult);

        return $processor;
    }

    private function whenTheOrderResultIsProcessed(): void
    {
        $this->result->processOrderResult(
            self::ORDER_ID,
            self::RVVUP_ID,
            self::ORIGIN,
            self::STORE_ID
        );
    }
}
