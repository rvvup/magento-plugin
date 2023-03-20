<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Exception\QuoteValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\OrderDataBuilder;

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

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Rvvup\Payments\Model\OrderDataBuilder $orderDataBuilder
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        OrderDataBuilder $orderDataBuilder,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->orderDataBuilder = $orderDataBuilder;
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
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();

        // First check if we have an Order Payment model instance and return result if set.
        $result = $this->handleOrderPayment($payment);

        if (is_array($result)) {
            return $result;
        }

        // Otherwise, we should have a Quote Payment model instance and return result if set.
        $result = $this->handleQuotePayment($payment);

        if (is_array($result)) {
            return $result;
        }

        // Log that we reached this stage and throw exception.
        $this->logger->error('There is no Rvvup Standard Payment for Order or Express Payment for Quote');

        throw new QuoteValidationException(__('Invalid Payment method'));
    }

    /**
     * Create the request data for an Order Payment & flag if this is a Rvvup Express Update (on order place)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return array|null
     * @throws \Rvvup\Payments\Exception\QuoteValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function handleOrderPayment(InfoInterface $payment): ?array
    {
        if (!method_exists($payment, 'getOrder')) {
            return null;
        }

        // Get the active quote (allow for exceptions to fall through).
        $cart = $this->cartRepository->get($payment->getOrder()->getQuoteId());

        // Build the Rvvup request data, regardless express or not.
        $orderData = $this->orderDataBuilder->build($cart);

        // If this is an express payment getting completed, set payment type (express) & additional data.
        if ($this->isExpressPayment($payment)) {
            $orderData['id'] = $payment->getAdditionalInformation(Method::ORDER_ID);
        }

        return $orderData;
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
    private function handleQuotePayment(InfoInterface $payment): ?array
    {
        if (!$this->isExpressPayment($payment) || !method_exists($payment, 'getQuote')) {
            return null;
        }

        // Get the active quote (allow for exceptions to fall through).
        $cart = $this->cartRepository->getActive($payment->getQuote()->getId());

        // Build the Rvvup request data for creating an express payment.
        return $this->orderDataBuilder->build($cart, true);
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
