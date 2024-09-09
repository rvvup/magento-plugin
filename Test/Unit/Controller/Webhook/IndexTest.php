<?php
declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Controller\Webhook;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\App\Request\StorePathInfoValidator;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Controller\Webhook\Index;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\ProcessRefund\ProcessorPool;
use Rvvup\Payments\Model\WebhookRepository;
use Rvvup\Payments\Service\Capture;

class IndexTest extends TestCase
{
    /** @var RequestInterface */
    private $request;

    /** @var ConfigInterface */
    private $config;

    /** @var SerializerInterface */
    private $serializer;

    /** @var Json */
    private $resultMock;

    /** @var WebhookRepository */
    private $webhookRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var ProcessorPool */
    private $refundPool;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var StorePathInfoValidator */
    private $storePathInfoValidator;

    /** @var Capture */
    private $captureService;

    /** @var Index */
    private $controller;


    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->resultMock = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->webhookRepository = $this->createMock(WebhookRepository::class);
        $this->refundPool = $this->createMock(ProcessorPool::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storePathInfoValidator = $this->createMock(StorePathInfoValidator::class);
        $this->captureService = $this->createMock(Capture::class);

        $resultFactory = $this->createMock(ResultFactory::class);
        $this->controller = new Index(
            $this->request,
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(Http::class),
            $this->config,
            $this->serializer,
            $resultFactory,
            $this->logger,
            $this->webhookRepository,
            $this->storeManager,
            $this->storePathInfoValidator,
            $this->refundPool,
            $this->captureService
        );

        $resultFactory->method('create')->willReturn($this->resultMock);
        $storeMock = $this->createMock(StoreInterface::class);
        $this->storeManager->method('getStore')->willReturn($storeMock);
        $storeMock->method('getId')->willReturn(1);

    }

    public function testReturnsInvalidResponseWhenMerchantIdIsMissing()
    {
        $this->request->method('getParam')->willReturnMap([
            ['merchant_id', false, false],
            ['order_id', false, 'OR01J7BNKYDP40GA334Z0CPY4P46'],
            ['event_type', false, 'PAYMENT_COMPLETED'],
        ]);
        $this->expectsResult(400, ['reason' => 'Merchant id is not present', 'metadata' => []]);

        $response = $this->controller->execute();

        $this->assertSame($this->resultMock, $response);
    }

    public function testReturnsErrorResponseWhenMerchantIdDoesNotMatchConfiguration()
    {
        $this->request->method('getParam')->willReturnMap([
            ['merchant_id', false, 'ME01J7BNM88DQ8Z0FPAXTNQE2X0W'],
            ['order_id', false, 'OR01J7BNKYDP40GA334Z0CPY4P46'],
            ['event_type', false, 'PAYMENT_COMPLETED'],
        ]);
        $this->config->method('getMerchantId')->willReturn('ME01J7BNWNEG9T1JYA59E74HNHPJ');
        $quoteMock = $this->createMock(Quote::class);
        $quoteMock->method('getId')->willReturn(123);
        $quoteMock->method('getStoreId')->willReturn(1);
        $this->captureService->method('getQuoteByRvvupId')->willReturn($quoteMock);


        $this->expectsResult(210, ['reason' => 'Invalid merchant id', 'metadata' => [
            'merchant_id' => 'ME01J7BNM88DQ8Z0FPAXTNQE2X0W',
            'config_merchant_id' => 'ME01J7BNWNEG9T1JYA59E74HNHPJ',
            'rvvup_id' => 'OR01J7BNKYDP40GA334Z0CPY4P46'
        ]]);

        $response = $this->controller->execute();

        $this->assertSame($this->resultMock, $response);
    }

    /**
     * @dataProvider paymentEvents
     */
    public function testPaymentEventsValidatesOrderId($eventType)
    {
        $this->request->method('getParam')->willReturnMap([
            ['merchant_id', false, 'ME01J7BNM88DQ8Z0FPAXTNQE2X0W'],
            ['order_id', false, false],
            ['event_type', false, $eventType],
        ]);
        $this->config->method('getMerchantId')->willReturn('ME01J7BNM88DQ8Z0FPAXTNQE2X0W');

        $this->expectsResult(400, ['reason' => 'Missing parameters required for '.$eventType, 'metadata' => [
            'order_id' => false,
            'merchant_id' => 'ME01J7BNM88DQ8Z0FPAXTNQE2X0W',
            'payment_id' => null,
            'event_type' => $eventType,
            'store_id' => 1,
            'payment_link_id' => false,
            'checkout_id' => false,
            'origin' => 'webhook'
        ]]);

        $response = $this->controller->execute();

        $this->assertSame($this->resultMock, $response);
    }

    public static function paymentEvents()
    {
        return [['PAYMENT_COMPLETED'], ['PAYMENT_AUTHORIZED']];
    }

    public function testNonSupportedEventTypeWillReturnSuccess()
    {
        $this->request->method('getParam')->willReturnMap([
            ['merchant_id', false, 'ME01J7BNM88DQ8Z0FPAXTNQE2X0W'],
            ['order_id', false, false],
            ['event_type', false, 'UNKNOWN_EVENT'],
        ]);
        $this->config->method('getMerchantId')->willReturn('ME01J7BNM88DQ8Z0FPAXTNQE2X0W');

        $this->expectsResult(202);

        $response = $this->controller->execute();

        $this->assertSame($this->resultMock, $response);
    }

    private function expectsResult(int $statusCode, ?array $data = null)
    {
        $this->resultMock->expects($this->atLeastOnce())
            ->method('setHttpResponseCode')
            ->with($statusCode);
        if ($data !== null) {
            $this->resultMock->expects($this->once())
                ->method('setData')
                ->with($data);
        }

    }
}
