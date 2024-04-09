<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Gateway\Method;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Model\IsPaymentMethodAvailableInterface;

class PaymentCreateExpress implements PaymentCreateExpressInterface
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var \Magento\Quote\Api\CartTotalRepositoryInterface
     */
    private $cartTotalRepository;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\Payment
     */
    private $paymentResource;

    /**
     * @var \Rvvup\Payments\Model\IsPaymentMethodAvailableInterface
     */
    private $isPaymentMethodAvailable;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param \Magento\Framework\DataObjectFactory $dataObjectFactory
     * @param \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalRepository
     * @param \Magento\Quote\Model\ResourceModel\Quote\Payment $paymentResource
     * @param \Rvvup\Payments\Model\IsPaymentMethodAvailableInterface $isPaymentMethodAvailable
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        DataObjectFactory $dataObjectFactory,
        CartTotalRepositoryInterface $cartTotalRepository,
        Payment $paymentResource,
        IsPaymentMethodAvailableInterface $isPaymentMethodAvailable,
        LoggerInterface $logger
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->paymentResource = $paymentResource;
        $this->isPaymentMethodAvailable = $isPaymentMethodAvailable;
        $this->logger = $logger;
    }

    /**
     * Instantiate (create) a Rvvup Express Payment through the API
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @param string $methodCode
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    public function execute(CartInterface $quote, string $methodCode): bool
    {
        // Strip the Magento Module's prefix on the payment method.
        $this->validatePaymentMethodAvailableForQuote($quote, $methodCode);

        // Pass Rvvup method code in the expected naming convetion `rvvup_UPPERCASE-METHOD-CODE`
        $payment = $this->getPaymentData($quote, Method::PAYMENT_TITLE_PREFIX . mb_strtoupper($methodCode));

        $quote->setPayment($payment);

        $paymentMethod = $payment->getMethodInstance();

        // This should be Rvvup which always requires initialization (to create the Rvvup Payment), so fail if not.
        if (!$paymentMethod->isInitializeNeeded()) {
            $this->logger->error('Initialization is required for Rvvup Payment methods', [
                'quote_id' => $quote->getId(),
                'is_express' => true,
                'payment_method' => $methodCode
            ]);

            throw new PaymentValidationException(__('Payment method not available'));
        }

        $stateObject = $this->dataObjectFactory->create();
        $paymentMethod->initialize(
            $paymentMethod->getConfigData('payment_action', $quote->getStoreId()),
            $stateObject
        );

        $this->paymentResource->save($payment);

        return true;
    }

    /**
     * Validate the Rvvup payment method is available for the current quote.
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param string $methodCode
     * @return void
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function validatePaymentMethodAvailableForQuote(CartInterface $quote, string $methodCode): void
    {
        $totals = $this->cartTotalRepository->get($quote->getId());

        if (!$this->isPaymentMethodAvailable->execute(
            $methodCode,
            $totals->getGrandTotal() === null ? '0' : (string) $totals->getGrandTotal(),
            $quote->getCurrency() !== null && $quote->getCurrency()->getQuoteCurrencyCode() !== null
                ? $quote->getCurrency()->getQuoteCurrencyCode()
                : ''
        )) {
            throw new PaymentValidationException(__('Payment method not available'));
        }
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param string $methodCode
     * @return \Magento\Quote\Api\Data\PaymentInterface|\Magento\Quote\Model\Quote\Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    private function getPaymentData(CartInterface $quote, string $methodCode): PaymentInterface
    {
        $payment = $quote->getPayment();

        try {
            $payment->importData([
                PaymentInterface::KEY_METHOD => $methodCode,
                // Set a flag that this is an Express Payment. This is saved in additional information by an observer.
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    Method::EXPRESS_PAYMENT_KEY => true
                ],
                'checks' => [
                    MethodInterface::CHECK_USE_CHECKOUT,
                    MethodInterface::CHECK_USE_FOR_CURRENCY,
                    MethodInterface::CHECK_ORDER_TOTAL_MIN_MAX,
                    MethodInterface::CHECK_ZERO_TOTAL,
                ]
            ]);
        } catch (LocalizedException $ex) {
            $this->logger->error(
                'Failed to import Rvvup payment method in Quote Payment with message: ' . $ex->getMessage(),
                [
                    'quote_id' => $quote->getId(),
                    'method_code' => $methodCode
                ]
            );

            throw new PaymentValidationException(__('Payment method not available'));
        }

        return $payment;
    }
}
