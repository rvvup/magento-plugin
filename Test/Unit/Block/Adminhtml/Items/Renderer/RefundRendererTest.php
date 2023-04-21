<?php

declare(strict_types=1);

namespace Unit\Block\Adminhtml\Items\Renderer;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Rvvup\Payments\Block\Adminhtml\Items\Renderer\RefundRenderer;

class RefundRendererTest extends TestCase
{

    /**
     * @var RefundRenderer
     */
    private $refundRenderer;

    /**
     * @var OrderItemInterface
     */
    private $orderItem;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var Creditmemo
     */
    private $creditMemo;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->serializer = $this->getMockBuilder(Json::class)->getMock();
        $this->creditMemo = $this->createMock(Creditmemo::class);
        $this->orderItem = $this->getMockBuilder(OrderItemInterface::class)
            ->addMethods(['getRvvupPendingRefundData'])->getMockForAbstractClass();

        $this->refundRenderer = $this->getMockBuilder(RefundRenderer::class)
            ->disableOriginalConstructor()->onlyMethods(['getCreditmemo', 'unserialize'])
            ->getMockForAbstractClass();
    }

    public function testGetQtyRvvupPendingRefundReturnsZero()
    {
        $this->orderItem->method('getRvvupPendingRefundData')->willReturn(null);
        $this->refundRenderer->method('unserialize')->willReturn([]);
        $this->refundRenderer->method('getCreditmemo')->willReturn($this->creditMemo);
        $this->assertEquals(0, $this->refundRenderer->getQtyRvvupPendingRefund($this->orderItem));
    }

    public function testGetQtyRvvupPendingRefundWithCreditmemo()
    {
        $this->orderItem->method('getRvvupPendingRefundData')->willReturn("1:{'qty'=>3}");
        $this->creditMemo->method('getId')->willReturn(1);
        $this->refundRenderer->method('getCreditmemo')->willReturn($this->creditMemo);
        $this->refundRenderer->method('unserialize')->willReturn([1 => ['qty' => 3]]);
        $this->assertEquals(3.00, $this->refundRenderer->getQtyRvvupPendingRefund($this->orderItem));
    }
}
