<?php
declare(strict_types=1);

namespace Rvvup\Payments\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

class Paypal extends Template
{
    /** @var Session */
    private $checkoutSession;

    /** @var QuoteIdToMaskedQuoteIdInterface */
    private $quoteIdToMaskedQuoteId;

    /**
     * @param Session $checkoutSession
     * @param Context $context
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param array $data
     */
    public function __construct(
        Session $checkoutSession,
        Template\Context $context,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        parent::__construct($context,$data);
    }

    /**
     * @return CartInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote(): CartInterface
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getMaskedQuoteId(): ?string
    {
        $quoteId = (int)$this->getQuote()->getId();
        try {
            return $this->quoteIdToMaskedQuoteId->execute($quoteId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
