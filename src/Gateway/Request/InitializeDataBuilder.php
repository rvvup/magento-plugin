<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Request;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\Exception;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Exception\QuoteValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\OrderDataBuilder;

class InitializeDataBuilder implements BuilderInterface
{

    /** @var OrderDataBuilder  */
    private $orderDataBuilder;

    /** @var LoggerInterface  */
    private $logger;

    /**
     * @param OrderDataBuilder $orderDataBuilder

     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderDataBuilder $orderDataBuilder,
        LoggerInterface $logger
    ) {
        $this->orderDataBuilder = $orderDataBuilder;
        $this->logger = $logger;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws QuoteValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $quote = $buildSubject['quote'];
        $validate = $buildSubject['validate'] ?? true;

        // Otherwise, we should have a Quote Payment model instance and return result if set.
        $result = $this->handleQuotePayment($quote, $validate);

        if (is_array($result)) {
            return $result;
        }

        // Log that we reached this stage and throw exception.
        $this->logger->error('There is no Rvvup Standard Payment for Order or Express Payment for Quote');

        throw new QuoteValidationException(__('Invalid Payment method'));
    }

    /**
     * Handle initialization if this is a Quote Payment (not placed order yet).
     * Currently, this is supported only for creating express payment orders.
     *
     * @param CartInterface $cart
     * @param bool $validate
     * @return array|null
     * @throws AlreadyExistsException
     * @throws Exception
     * @throws LocalizedException
     * @throws QuoteValidationException
     */
    private function handleQuotePayment(CartInterface $cart, bool $validate): ?array
    {
        return $this->orderDataBuilder->build($cart, $this->isExpressPayment($cart->getPayment()), $validate);
    }

    /**
     * @param InfoInterface $payment
     * @return bool
     */
    private function isExpressPayment(InfoInterface $payment): bool
    {
        return $payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) === true;
    }
}
