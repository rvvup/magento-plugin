<?php

namespace Unit\Model\Queue\Handler;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Api\Data\ValidationInterface;
use Rvvup\Payments\Api\Data\WebhookInterface;
use Rvvup\Payments\Api\WebhookRepositoryInterface;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Model\Queue\Handler\Handler;
use Rvvup\Payments\Model\Queue\QueueContextCleaner;
use Rvvup\Payments\Model\Webhook\WebhookEventType;
use Rvvup\Payments\Service\Cache;
use Rvvup\Payments\Service\Capture;
use Rvvup\Payments\Service\Card\CardMetaService;

class HandlerTest extends TestCase
{
    private $handler;
    private $webhookRepository;
    private $paymentDataGet;
    private $logger;
    private $captureService;
    private $orderRepository;
    private $cartRepository;
    private $queueContextCleaner;
    private $cardMetaService;

    private $quoteMock;
    private $methodInstanceMock;
    private $orderMock;

    protected function setUp(): void
    {
        $this->webhookRepository = $this->createMock(WebhookRepositoryInterface::class);
        $serializer = new Json();
        $this->paymentDataGet = $this->createMock(PaymentDataGetInterface::class);
        $processorPool = $this->createMock(ProcessorPool::class);
        $this->logger = $this->createMock(Logger::class);
        $this->captureService = $this->createMock(Capture::class);
        $paymentResource = $this->createMock(Payment::class);
        $cacheService = $this->createMock(Cache::class);
        $json = new Json();
        $emulation = $this->createMock(Emulation::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->queueContextCleaner = $this->createMock(QueueContextCleaner::class);
        $this->cardMetaService = $this->createMock(CardMetaService::class);

        $this->handler = new Handler(
            $this->webhookRepository,
            $serializer,
            $this->paymentDataGet,
            $processorPool,
            $this->logger,
            $paymentResource,
            $cacheService,
            $this->captureService,
            $emulation,
            $json,
            $this->orderRepository,
            $this->cartRepository,
            $this->queueContextCleaner,
            $this->cardMetaService
        );

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getId')->willReturn(123);
        $paymentMock = $this->getMockBuilder('stdClass')
            ->setMethods(['getAdditionalInformation', 'getMethodInstance'])->getMock();
        $this->quoteMock->method('getPayment')->willReturn($paymentMock);
        $paymentMock->method('getAdditionalInformation')->willReturn('PA123');
        $this->methodInstanceMock = $this->getMockBuilder('stdClass')
            ->setMethods(['getCaptureType'])->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($this->methodInstanceMock);
        $this->cartRepository->method('get')->willReturn($this->quoteMock);
        $this->orderMock = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('get')->willReturn($this->orderMock);
    }

    public function testExecuteLogsErrorIfStoreIdMissing()
    {
        $input = ['id' => 1];
        $payload = [
            'order_id' => 'OR123',
            'payment_id' => 'PA123',
            'store_id' => null,
            'checkout_id' => 'CO123',
            'origin' => 'webhook',
            'application_source' => 'MAGENTO_CHECKOUT'
        ];
        $webhook = new WebhookStub(123, json_encode($payload));

        $this->webhookRepository->method('getById')->with($input['id'])->willReturn($webhook);
        $this->logger->expects($this->once())->method('addRvvupError')->with(
            'StoreId not present in webhook payload',
            null,
            'OR123',
            'PA123',
            null,
            'webhook'
        );

        $this->handler->execute(json_encode($input));
    }

    public function testPaymentAuthorizedHandlingRunsCardMetadataServiceForManualCapturePayments()
    {
        $payload = [
            'event_type' => WebhookEventType::PAYMENT_AUTHORIZED,
            'merchant_id' => 'ME123',
            'order_id' => 'OR123',
            'quote_id' => 123,
            'payment_id' => 'PA123',
            'store_id' => 3,
            'refund_id' => false,
            'checkout_id' => 'CO123',
            'payment_link_id' => false,
            'origin' => 'webhook',
            'application_source' => 'MAGENTO_CHECKOUT'];

        $webhook = new WebhookStub(123, json_encode($payload));

        $validation = new ValidationStub(false, 456, true);
        $this->webhookRepository->method('getById')->willReturn($webhook);
        $this->captureService->method('validate')
            ->with($this->quoteMock, 'OR123', null, 'webhook')
            ->willReturn($validation);
        $this->captureService->method('setCheckoutMethod')->with($this->quoteMock);
        $this->captureService->method('createOrder')->willReturn($validation);
        $rvvupData = ['payments' => [['status' => 'AUTHORIZED', 'id' => 'PA123']]];
        $this->paymentDataGet->method('execute')->with('OR123', '3')->willReturn($rvvupData);

        $this->methodInstanceMock->method('getCaptureType')->willReturn('MANUAL');
        // Assert that we call the card meta service with the correct parameters
        $this->cardMetaService->expects($this->once())
            ->method('process')
            ->with($rvvupData['payments'][0], $this->orderMock);

        $this->handler->execute(json_encode(['id' => 1]));
    }


    public function testPaymentAuthorizedHandlingDoesNotRunPaymentCaptureForManualCapturePayments()
    {
        $payload = [
            'event_type' => WebhookEventType::PAYMENT_AUTHORIZED,
            'merchant_id' => 'ME123',
            'order_id' => 'OR123',
            'quote_id' => 123,
            'payment_id' => 'PA123',
            'store_id' => 3,
            'refund_id' => false,
            'checkout_id' => 'CO123',
            'payment_link_id' => false,
            'origin' => 'webhook',
            'application_source' => 'MAGENTO_CHECKOUT'];

        $webhook = new WebhookStub(123, json_encode($payload));

        $validation = new ValidationStub(false, 456, true);
        $this->webhookRepository->method('getById')->willReturn($webhook);
        $this->captureService->method('validate')
            ->with($this->quoteMock, 'OR123', null, 'webhook')
            ->willReturn($validation);
        $this->captureService->method('setCheckoutMethod')->with($this->quoteMock);
        $this->captureService->method('createOrder')->willReturn($validation);
        $rvvupData = ['payments' => [['status' => 'AUTHORIZED', 'id' => 'PA123']]];
        $this->paymentDataGet->method('execute')->with('OR123', '3')->willReturn($rvvupData);

        $this->methodInstanceMock->method('getCaptureType')->willReturn('MANUAL');

        $this->captureService->expects($this->never())->method('paymentCapture');

        $this->handler->execute(json_encode(['id' => 1]));
    }

    public function testPaymentAuthorizedHandlingDoesNotCallCardMetaServiceForAutomaticPluginPayment()
    {
        $payload = [
            'event_type' => WebhookEventType::PAYMENT_AUTHORIZED,
            'merchant_id' => 'ME123',
            'order_id' => 'OR123',
            'quote_id' => 123,
            'payment_id' => 'PA123',
            'store_id' => 3,
            'refund_id' => false,
            'checkout_id' => 'CO123',
            'payment_link_id' => false,
            'origin' => 'webhook',
            'application_source' => 'MAGENTO_CHECKOUT'
        ];

        $webhook = new WebhookStub(123, json_encode($payload));

        $validation = new ValidationStub(false, 456, true);
        $this->webhookRepository->method('getById')->willReturn($webhook);
        $this->captureService->method('validate')
            ->with($this->quoteMock, 'OR123', null, 'webhook')
            ->willReturn($validation);
        $this->captureService->method('setCheckoutMethod')->with($this->quoteMock);
        $this->captureService->method('createOrder')->willReturn($validation);
        $rvvupData = ['payments' => [['status' => 'AUTHORIZED', 'id' => 'PA123']]];
        $this->paymentDataGet->method('execute')->with('OR123', '3')->willReturn($rvvupData);

        $this->methodInstanceMock->method('getCaptureType')->willReturn('AUTOMATIC_PLUGIN');
        // Assert that we do NOT call the card meta service
        $this->cardMetaService->expects($this->never())->method('process');

        $this->handler->execute(json_encode(['id' => 1]));
    }

    public function testPaymentAuthorizedHandlingCallsPaymentCaptureForAutomaticPluginPayment()
    {
        $payload = [
            'event_type' => WebhookEventType::PAYMENT_AUTHORIZED,
            'merchant_id' => 'ME123',
            'order_id' => 'OR123',
            'quote_id' => 123,
            'payment_id' => 'PA123',
            'store_id' => 3,
            'refund_id' => false,
            'checkout_id' => 'CO123',
            'payment_link_id' => false,
            'origin' => 'webhook',
            'application_source' => 'MAGENTO_CHECKOUT'
        ];

        $webhook = new WebhookStub(123, json_encode($payload));

        $validation = new ValidationStub(false, 456, true);
        $this->webhookRepository->method('getById')->willReturn($webhook);
        $this->captureService->method('validate')
            ->with($this->quoteMock, 'OR123', null, 'webhook')
            ->willReturn($validation);
        $this->captureService->method('setCheckoutMethod')->with($this->quoteMock);
        $this->captureService->method('createOrder')->willReturn($validation);
        $rvvupData = ['payments' => [['status' => 'AUTHORIZED', 'id' => 'PA123']]];
        $this->paymentDataGet->method('execute')->with('OR123', '3')->willReturn($rvvupData);

        $this->methodInstanceMock->method('getCaptureType')->willReturn('AUTOMATIC_PLUGIN');

        $this->captureService->expects($this->once())
            ->method('paymentCapture')
            ->with('OR123', 'PA123', 'webhook', 3);

        $this->handler->execute(json_encode(['id' => 1]));
    }
}

class ValidationStub implements ValidationInterface
{
    private $alreadyExists;
    private $orderId;
    private $isValid;

    public function __construct($alreadyExists, $orderId, $isValid)
    {
        $this->alreadyExists = $alreadyExists;
        $this->orderId = $orderId;
        $this->isValid = $isValid;
    }

    public function getAlreadyExists()
    {
        return $this->alreadyExists;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function getIsValid()
    {
        return $this->isValid;
    }

    public function validate(
        ?Quote &$quote,
        string $rvvupId = null,
        string $paymentStatus = null,
        string $origin = null
    ): ValidationInterface {
        return $this;
    }
}

class WebhookStub implements WebhookInterface
{
    private $id;
    private $payload;

    public function __construct($id, $payload)
    {
        $this->id = $id;
        $this->payload = $payload;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function setPayload(?string $payload): void
    {
        $this->payload = $payload;
    }
}
