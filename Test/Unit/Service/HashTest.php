<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Api\Data\HashInterfaceFactory;
use Rvvup\Payments\Api\HashRepositoryInterface;
use Rvvup\Payments\Service\Hash;

class HashTest extends TestCase
{
    /** @var Hash */
    private $hash;

    protected function setUp(): void
    {
        $this->hash = new Hash(
            $this->createMock(Payment::class),
            $this->createMock(HashRepositoryInterface::class),
            $this->createMock(HashInterfaceFactory::class)
        );
    }

    /**
     * Reproduces SUPPORT-105. The hash is created on the storefront (where a third-party module contributes
     * an empty `adjustment` total) and re-verified inside the webhook queue consumer (where that total
     * collector is not active). The cart is unchanged: every SKU, quantity and money value is identical.
     * The hash must stay the same across both contexts; today it does not, so the order is silently
     * refused and DivideBuy auto-refunds.
     */
    public function testItProducesTheSameHashWhenOnlyAnEmptyAdjustmentTotalDiffers(): void
    {
        $storefrontHash = $this->hash->getHashForData($this->quoteAtCheckout(), true)[1];
        $webhookHash = $this->hash->getHashForData($this->quoteInConsumer(), true)[1];

        $this->assertSame($storefrontHash, $webhookHash);
    }

    private function quoteAtCheckout(): Quote
    {
        return $this->quoteWithTotals([
            ['code' => 'subtotal', 'value' => '599.96'],
            ['code' => 'tax', 'value' => '89.99'],
            ['code' => 'shipping', 'value' => '0'],
            ['code' => 'adjustment', 'value' => ''], // This is the issue, an empty total
            ['code' => 'grand_total', 'value' => '539.96'],
        ]);
    }

    private function quoteInConsumer(): Quote
    {
        return $this->quoteWithTotals([
            ['code' => 'subtotal', 'value' => '599.96'],
            ['code' => 'tax', 'value' => '89.99'],
            ['code' => 'shipping', 'value' => '0'],
            ['code' => 'grand_total', 'value' => '539.96'],
        ]);
    }

    private function quoteWithTotals(array $totals): Quote
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getTotals')->willReturn(array_map(
            static function (array $total): DataObject {
                return new DataObject($total);
            },
            $totals
        ));
        $quote->method('getItems')->willReturn([
            new DataObject(['sku' => 'LASCO-STL-J1156-TAU-SLV', 'qty' => '4.0000']),
        ]);

        return $quote;
    }
}
