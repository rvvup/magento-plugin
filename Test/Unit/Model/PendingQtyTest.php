<?php

declare(strict_types=1);

namespace Unit\Model;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderItemInterface;
use Rvvup\Payments\Model\PendingQty;

class PendingQtyTest extends TestCase
{
    /**
     * @var PendingQty
     */
    private $pendingQty;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var OrderItemInterface
     */
    private $item;

    /**
     * @var Order
     */
    private $order;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(Json::class);
        $this->pendingQty = new PendingQty($this->serializer);
        $this->item = $this->getMockBuilder(OrderItemInterface::class)
            ->addMethods(['getRvvupPendingRefundData'])->getMockForAbstractClass();
        $this->order = $this->getMockBuilder(Order::class)
            ->onlyMethods(['getEventPrefix', 'getAllItems'])->disableOriginalConstructor()->getMock();
    }

    public function testGetRvvupPendingQty()
    {
        $this->item->method('getRvvupPendingRefundData')->willReturn(
            '{"1":{"qty":3,"refund_id":"rvvup_refund_id"}},"2":{"qty":2,"refund_id":"rvvup_refund_id_2"}}'
        );

        $this->serializer->method('unserialize')->willReturn([
            1 => [
                'qty' => '3',
                'refund_id' => 'rvvup_refund_id'
            ],
            2 => [
                'qty' => '2',
                'refund_id' => 'rvvup_refund_id_2'
            ]
        ]);

        $this->assertEquals(5, $this->pendingQty->getRvvupPendingQty($this->item));
    }

    public function testIsRefundApplicableReturnsTrue()
    {
        list($item1, $item2) = [$this->item, $this->item];

        $item1->method('getQtyInvoiced')->willReturn(5);
        $item1->method('getQtyRefunded')->willReturn(2);

        $item2->method('getQtyInvoiced')->willReturn(3);
        $item2->method('getQtyRefunded')->willReturn(1);


        $this->order->method('getAllItems')->willReturn([$item1, $item2]);
        $this->order->method('getEventPrefix')->willReturn(PendingQty::SALES_ORDER);
        $this->assertTrue($this->pendingQty->isRefundApplicable($this->order, true));
    }

    public function testIsRefundApplicableReturnsFalse()
    {
        list($item1, $item2) = [$this->item, $this->item];

        $item1->method('getQtyInvoiced')->willReturn(5);
        $item1->method('getQtyRefunded')->willReturn(5);

        $item2->method('getQtyInvoiced')->willReturn(3);
        $item2->method('getQtyRefunded')->willReturn(3);

        $this->order->method('getAllItems')->willReturn([$item1, $item2]);
        $this->order->method('getEventPrefix')->willReturn(PendingQty::SALES_ORDER);
        $this->assertFalse($this->pendingQty->isRefundApplicable($this->order, true));
    }
}
