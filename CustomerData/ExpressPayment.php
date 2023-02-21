<?php

declare(strict_types=1);

namespace Rvvup\Payments\CustomerData;

use Exception;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\Gateway\Method;

class ExpressPayment implements SectionSourceInterface
{
    /**
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Checkout\Model\Session\Proxy
     */
    private $checkoutSession;

    /**
     * Set via di.xml
     *
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Customer\Model\Session\Proxy
     */
    private $customerSession;

    /**
     * @var \Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \Magento\Framework\Session\SessionManagerInterface $checkoutSession
     * @param \Magento\Framework\Session\SessionManagerInterface $customerSession
     * @param \Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->storeManager = $storeManager;
    }

    /**
     * Get Private section data for express payment section.
     *
     * @return array
     */
    public function getSectionData(): array
    {
        return [
            'store_code' => $this->getCurrentStoreCode(),
            'is_logged_in' => $this->customerSession->isLoggedIn(),
            'quote_id' => $this->getSessionQuoteId(),
            'is_express_payment' => $this->isExpressPaymentQuote()
        ];
    }

    /**
     * @return string
     */
    private function getCurrentStoreCode(): string
    {
        try {
            return $this->storeManager->getStore()->getCode();
        } catch (Exception $ex) {
            return '';
        }
    }

    /**
     * @return string|null
     */
    private function getSessionQuoteId(): ?string
    {
        try {
            $quote = $this->getSessionQuote();

            if ($quote === null) {
                return null;
            }

            // If logged-in user, return the current quote ID.
            if (!$this->isGuest()) {
                return (string) $quote->getId();
            }

            return $this->quoteIdToMaskedQuoteId->execute((int) $quote->getId());
        } catch (Exception $ex) {
            return null;
        }
    }

    /**
     * @return bool
     */
    private function isExpressPaymentQuote(): bool
    {
        $quote = $this->getSessionQuote();

        if ($quote === null) {
            return false;
        }

        return $quote->getPayment() !== null
            && $quote->getPayment()->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) === true;
    }

    /**
     * @return bool
     */
    private function isGuest(): bool
    {
        return !$this->customerSession->isLoggedIn();
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote|null
     */
    private function getSessionQuote()
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            if (!$quote->getId()) {
                return null;
            }

            return $quote;
        } catch (Exception $ex) {
            return null;
        }
    }
}
