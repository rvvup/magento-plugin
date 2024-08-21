<?php

namespace Rvvup\Payments\Model\Data;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\OrderIncrementIdChecker;
use Rvvup\Payments\Api\Data\ValidationInterface;
use Rvvup\Payments\Api\Data\ValidationInterfaceFactory;
use Rvvup\Payments\Api\HashRepositoryInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\Hash;
use Psr\Log\LoggerInterface;

class Validation extends DataObject implements ValidationInterface
{
    /** Set via di.xml
     * @var LoggerInterface
     */
    private $logger;

    /** @var Hash */
    private $hashService;

    /** @var OrderIncrementIdChecker */
    private $orderIncrementChecker;

    /** @var HashRepositoryInterface */
    private $hashRepository;

    /**
     * @param Hash|null $hashService
     * @param OrderIncrementIdChecker|null $orderIncrementIdChecker
     * @param LoggerInterface|null $logger
     * @param HashRepositoryInterface $hashRepository
     * @param array $data
     */
    public function __construct(
        Hash $hashService,
        OrderIncrementIdChecker $orderIncrementIdChecker,
        LoggerInterface $logger,
        HashRepositoryInterface $hashRepository,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->orderIncrementChecker = $orderIncrementIdChecker;
        $this->hashService = $hashService;
        $this->hashRepository = $hashRepository;
        parent::__construct($data);
    }

    /**
     * @param Quote|null $quote
     * @param string|null $rvvupId
     * @param string|null $paymentStatus
     * @param string|null $origin
     * @return ValidationInterface
     */
    public function validate(
        ?Quote  &$quote,
        string $rvvupId = null,
        string $paymentStatus = null,
        string $origin = null
    ): ValidationInterface {
        $data = $this->getDefaultData();

        if ($quote == null || empty($quote->getId())) {
            $message = __(
                'This checkout cannot complete because another payment is in progress. '
                . $rvvupId
            );
            $this->logger->addRvvupError(
                'Missing quote for Rvvup payment',
                $message,
                $rvvupId,
                null,
                null,
                $origin
            );

            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::REDIRECT_TO_CART] = true;
            $data[ValidationInterface::RESTORE_QUOTE] = true;
            if ($origin !== 'webhook') {
                $data[ValidationInterface::MESSAGE] = $message;
            }
            $this->setValidationData($data);
            return $this;
        }

        $payment = $quote->getPayment();
        $lastTransactionId = (string)$payment->getAdditionalInformation(Method::TRANSACTION_ID);

        // First validate we have a Rvvup Order ID, silently return to basket page.
        // A standard Rvvup return should always include `rvvup-order-id` param.
        if ($rvvupId === null) {
            $this->logger->addRvvupError(
                'No Rvvup Order ID provided',
                null,
                null,
                $lastTransactionId,
                $origin
            );

            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::REDIRECT_TO_CART] = true;
            $data[ValidationInterface::RESTORE_QUOTE] = true;
            $this->setValidationData($data);
            return $this;
        }

        if (!$quote->getReservedOrderId()) {
            $this->logger->addRvvupError(
                'Rvvup Quote missing reserved order id',
                null,
                $rvvupId,
                $lastTransactionId,
                null,
                $origin
            );
            if ($origin !== 'webhook') {
                $data[ValidationInterface::MESSAGE] = __(
                    'An error occurred when trying to process your payment, ' .
                    'please contact us to confirm the status of your order.'
                );
            }

            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::REDIRECT_TO_CHECKOUT_PAYMENT] = true;
            $data[ValidationInterface::RESTORE_QUOTE] = true;
            $this->setValidationData($data);
            return $this;
        }

        /** ID which we will show to customer in case of an error  */
        $errorId = $quote->getReservedOrderId();
        if (!$quote->getIsActive()) {
            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::ALREADY_EXISTS] = true;
            $message = __('Payment was already completed');
            $this->logger->addRvvupError(
                'The quote is not active',
                $message,
                $rvvupId,
                null,
                $quote->getReservedOrderId() ?? null,
                $origin
            );

            $this->setValidationData($data);
            return $this;
        }

        if (!$this->isPaymentStatusValid($paymentStatus)) {
            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::REDIRECT_TO_CHECKOUT_PAYMENT] = true;
            $data[ValidationInterface::RESTORE_QUOTE] = true;
            $this->setValidationData($data);
            return $this;
        }

//        $hash = $quote->getPayment()->getAdditionalInformation('rvvup_quote_hash_v2');
//        $sort = true;
//        /** Backward compatibility to prevent orders from failing when the plugin is upgrading */
//        if (!$hash) {
//            $sort = true;
//            $hash = $quote->getPayment()->getAdditionalInformation('rvvup_quote_hash');
//        }
//        if (!$hash) {
//            $sort = false;
//            $hash = $quote->getPayment()->getAdditionalInformation('quote_hash');
//        }
//
//        list($hashedData, $savedHash) = $this->hashService->getHashForData($quote, $sort);
//        if ($hash !== $savedHash) {
//            $hashItem = $this->hashRepository->getByHash($hash);
//            $message = 'Payment hash is invalid during Rvvup Checkout: ';
//            $message .= 'Quote hash created at: ' . $hashItem->getCreatedAt();
//            $cause = 'Original value: [' . $hashItem->getRawData() . ']';
//            $cause .= ', is not equal to: [' . $hashedData . ']';
//
//            $this->logger->addRvvupError(
//                $message,
//                $cause,
//                $rvvupId,
//                null,
//                $quote->getReservedOrderId() ?? null,
//                $origin
//            );
//
//            $message = __(
//                'Your cart was modified after making payment request, please place order again. ' . $errorId
//            );
//
//            $data[ValidationInterface::IS_VALID] = false;
//            $data[ValidationInterface::REDIRECT_TO_CART] = true;
//            $data[ValidationInterface::RESTORE_QUOTE] = true;
//            if ($origin !== 'webhook') {
//                $data[ValidationInterface::MESSAGE] = $message;
//            }
//            $this->setValidationData($data);
//            return $this;
//        }
        if ($rvvupId !== $lastTransactionId) {
            $this->logger->addRvvupError(
                'Payment transaction id is invalid during Rvvup Checkout',
                null,
                $rvvupId,
                $lastTransactionId,
                $quote->getReservedOrderId() ?? null,
                $origin
            );
            $message = __(
                'This checkout cannot complete, a new cart was opened in another tab. ' . $errorId
            );
            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::REDIRECT_TO_CART] = true;
            if ($origin !== 'webhook') {
                $data[ValidationInterface::MESSAGE] = $message;
            }
            $this->setValidationData($data);
            return $this;
        }
        if ($this->orderIncrementChecker->isIncrementIdUsed($quote->getReservedOrderId())) {
            $data[ValidationInterface::IS_VALID] = false;
            $data[ValidationInterface::ALREADY_EXISTS] = true;
            $this->setValidationData($data);
            return $this;
        }

        $this->setValidationData($data);
        return $this;
    }

    /**
     * @return array
     */
    private function getDefaultData(): array
    {
        return [
            ValidationInterface::IS_VALID => true,
            ValidationInterface::REDIRECT_TO_CART => false,
            ValidationInterface::RESTORE_QUOTE => false,
            ValidationInterface::MESSAGE => '',
            ValidationInterface::REDIRECT_TO_CHECKOUT_PAYMENT => false,
            ValidationInterface::ALREADY_EXISTS => false
        ];
    }

    /**
     * @param string|null $paymentStatus
     * @return bool
     */
    private function isPaymentStatusValid(?string $paymentStatus): bool
    {
        if ($paymentStatus == Method::STATUS_CANCELLED ||
            $paymentStatus == Method::STATUS_EXPIRED ||
            $paymentStatus == Method::STATUS_DECLINED ||
            $paymentStatus == Method::STATUS_AUTHORIZATION_EXPIRED ||
            $paymentStatus == Method::STATUS_FAILED) {
            return false;
        }
        return true;
    }

    /**
     * @param array $data
     * @return void
     */
    private function setValidationData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->setData($key, $value);
        }
    }
}
