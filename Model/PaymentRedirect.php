<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentRedirectInterface;

class PaymentRedirect implements PaymentRedirectInterface
{
    /** @var SortOrderBuilder */
    private $sortOrderBuilder;
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;
    /** @var QuoteIdMaskFactory */
    private $quoteIdMaskFactory;
    /** @var CartRepositoryInterface */
    private $cartRepository;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param SortOrderBuilder $sortOrderBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        SortOrderBuilder $sortOrderBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * @param string $customerId
     * @return string
     * @throws LocalizedException
     */
    public function getCustomerRedirectUrl(string $customerId): string
    {
        try {
            $sortOrder = $this->sortOrderBuilder->setDescendingDirection()
                ->setField('created_at')
                ->create();
            $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)
                ->addSortOrder($sortOrder)
                ->addFilter('customer_id', $customerId)
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            $order = reset($orders);
            return $order->getPayment()->getAdditionalInformation()["payment_actions"]["authorization"]["redirect_url"];
        } catch (\Exception $e) {
            $this->logger->error("Error loading redirect URL for logged-in user: {$e->getMessage()}");
            throw new LocalizedException(__('Something went wrong'));
        }
    }

    /**
     * @param string $cartId
     * @return string
     * @throws LocalizedException
     */
    public function getGuestRedirectUrl(string $cartId): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        try {
            $sortOrder = $this->sortOrderBuilder->setDescendingDirection()
                ->setField('created_at')
                ->create();
            $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)
                ->addSortOrder($sortOrder)
                ->addFilter('quote_id', $quoteIdMask->getQuoteId())
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            $order = reset($orders);
            return $order->getPayment()->getAdditionalInformation()["payment_actions"]["authorization"]["redirect_url"];
        } catch (\Exception $e) {
            $this->logger->error("Error loading redirect URL for guest user: {$e->getMessage()}");
            throw new LocalizedException(__('Something went wrong'));
        }
    }
}
