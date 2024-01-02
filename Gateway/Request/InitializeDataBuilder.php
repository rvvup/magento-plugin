<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\Cart;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Exception\QuoteValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\OrderDataBuilder;
use Rvvup\Payments\Service\Hash;

class InitializeDataBuilder implements BuilderInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Rvvup\Payments\Model\OrderDataBuilder
     */
    private $orderDataBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    private $hash;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param OrderDataBuilder $orderDataBuilder
     * @param Hash $hash
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        OrderDataBuilder $orderDataBuilder,
        Hash $hash,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->orderDataBuilder = $orderDataBuilder;
        $this->hash = $hash;
        $this->logger = $logger;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Rvvup\Payments\Exception\QuoteValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $quote = $buildSubject['quote'];

        // Otherwise, we should have a Quote Payment model instance and return result if set.
        $result = $this->handleQuotePayment($quote);

        if (is_array($result)) {
            return $result;
        }

        // Log that we reached this stage and throw exception.
        $this->logger->error('There is no Rvvup Standard Payment for Order or Express Payment for Quote');

        throw new QuoteValidationException(__('Invalid Payment method'));
    }

    /**
     * Handle initialization if this is a Quote Payment (not placed order yet).
     *
     * Currently, this is supported only for creating express payment orders.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return array|null
     * @throws \Rvvup\Payments\Exception\QuoteValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function handleQuotePayment(CartInterface $cart): ?array
    {
//        if (!$this->isExpressPayment($payment) || !method_exists($payment, 'getQuote')) {
//            return null;
//        }
//
        return $this->orderDataBuilder->build($cart, false);
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return bool
     */
    private function isExpressPayment(InfoInterface $payment): bool
    {
        return $payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) === true;
    }
}
