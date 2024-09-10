<?php

namespace Rvvup\Payments\Test\Unit\Service;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Payment;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Service\QuoteRetriever;
use Magento\Quote\Model\ResourceModel\Quote\Payment\Collection;

class QuoteRetrieverTest extends TestCase
{
    private $collectionMock;
    private $cartRepositoryMock;
    private $quoteRetriever;


    protected function setUp(): void
    {
        $collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->collectionMock = $this->createMock(Collection::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->quoteRetriever = new QuoteRetriever($collectionFactoryMock, $this->cartRepositoryMock);
        $collectionFactoryMock->method('create')->willReturn($this->collectionMock);

    }

    public function testCollectionIsEmpty()
    {
        $this->collectionMock->method('addFieldToFilter')
            ->with('additional_information', ['like' => "%\"rvvup_order_id\":\"test\"%"])
            ->willReturn($this->collectionMock);

        $this->collectionMock->method('getItems')
            ->willReturn([]);

        $this->assertEquals(null, $this->quoteRetriever->getUsingRvvupOrderId("test"));

    }


}
