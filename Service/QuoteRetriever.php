<?php

namespace Rvvup\Payments\Service;


use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment\Collection;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use Rvvup\Payments\Api\Data\ValidationInterfaceFactory;

class QuoteRetriever
{
    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CartRepositoryInterface */
    private $cartRepository;


    /**
     * @param CollectionFactory $collectionFactory
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CollectionFactory       $collectionFactory,
        CartRepositoryInterface $cartRepository
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param string $rvvupId Rvvup order id
     * @return Quote|null
     */
    public function getUsingRvvupOrderId(string $rvvupId): ?Quote
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('additional_information', ['like' => "%\"rvvup_order_id\":\"$rvvupId\"%"]);
        $items = $collection->getItems();
        if (count($items) !== 1) {
            return null;
        }
        $quoteId = end($items)->getQuoteId();
        try {
            $quote = $this->cartRepository->get($quoteId);
            if ($quote && $quote->getId()) {
                return $quote;
            }
            return null;
        } catch (NoSuchEntityException $ex) {
            return null;
        }
    }

    /**
     * @param string $rvvupId
     * @param int $storeId
     * @return Quote|null
     */
    public function getUsingRvvupOrderIdForStore(string $rvvupId, int $storeId): ?Quote
    {
        $quote = $this->getUsingRvvupOrderId($rvvupId);
        if ($quote->getStoreId() != $storeId) {
            return null;
        }
        return $quote;
    }

}
