<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface as PaymentInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\Hash;
use Throwable;

class PaymentActionsGet implements PaymentActionsGetInterface
{
    /**
     * @var PaymentActionInterfaceFactory
     */
    private $paymentActionInterfaceFactory;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @var SdkProxy
     */
    private $sdkProxy;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /** @var QuoteRepository */
    private $quoteRepository;

    /** @var Hash */
    private $hashService;

    /** @var Payment */
    private $paymentResource;

    /**
     * @param PaymentActionInterfaceFactory $paymentActionInterfaceFactory
     * @param SdkProxy $sdkProxy
     * @param CommandPoolInterface $commandPool
     * @param QuoteRepository $quoteRepository
     * @param Hash $hashService
     * @param Payment $paymentResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        PaymentActionInterfaceFactory $paymentActionInterfaceFactory,
        SdkProxy $sdkProxy,
        CommandPoolInterface $commandPool,
        QuoteRepository $quoteRepository,
        Hash $hashService,
        Payment $paymentResource,
        LoggerInterface $logger
    ) {
        $this->paymentActionInterfaceFactory = $paymentActionInterfaceFactory;
        $this->sdkProxy = $sdkProxy;
        $this->commandPool = $commandPool;
        $this->quoteRepository = $quoteRepository;
        $this->hashService = $hashService;
        $this->paymentResource = $paymentResource;
        $this->logger = $logger;
    }

    /**
     * Get the payment actions for the cart ID & customer ID if provided.
     *
     * @param string $cartId
     * @param string|null $customerId
     * @return PaymentActionInterface[]
     * @throws LocalizedException
     */
    public function execute(string $cartId, ?string $customerId = null): array
    {
        $quote = $this->quoteRepository->get($cartId);
        $this->ensureCustomerEmailExists($quote);
        if (!$quote->getCustomerEmail()) {
            throw new InputException(__('Missing email address'));
        }
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        // create rvvup order
        $this->commandPool->get('initialize')->execute(['quote' => $quote]);

        $this->hashService->saveQuoteHash($quote);

        $payment = $quote->getPayment();
        if ($payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY)) {
            if (!$payment->getAdditionalInformation(Method::CREATE_NEW)) {
                return $this->getExpressOrderPaymentActions($payment);
            }
        }

        // create rvvup payment
        $paymentData = $this->createRvvupPayment($quote);

        $paymentActionsDataArray = [];

        try {
            foreach ($paymentData as $paymentAction) {
                if (!is_array($paymentAction)) {
                    continue;
                }

                $paymentActionData = $this->getPaymentActionDataObject($paymentAction);

                // Validate all all properties have values.
                if ($paymentActionData->getType() !== null
                    && $paymentActionData->getMethod() !== null
                    && $paymentActionData->getValue() !== null
                ) {
                    $paymentActionsDataArray[] = $paymentActionData;
                }
            }
        } catch (Throwable $t) {
            $this->logger->error(
                'Error loading Payment Actions for user. Failed return result with message: ' . $t->getMessage(),
                [
                    'quote_id' => $cartId,
                    'customer_id' => $customerId
                ]
            );

            throw new LocalizedException(__('Something went wrong'));
        }

        if (empty($paymentActionsDataArray)) {
            $this->logger->error('Error loading Payment Actions for user. No payment actions found.', [
                'quote_id' => $cartId,
                'customer_id' => $customerId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $paymentActionsDataArray;
    }

    /**
     * @param CartInterface $quote
     * @return void
     */
    private function ensureCustomerEmailExists(CartInterface &$quote): void
    {
        if (!$quote->getCustomerEmail()) {
            $email = $quote->getBillingAddress()->getEmail();
            if (!$email) {
                $email = $quote->getShippingAddress()->getEmail();
            }
            if ($email) {
                $quote->setCustomerEmail($email);
            }
        }
    }

    /**
     * Get the order payment's paymentActions from its additional information
     *
     * @param PaymentInterface $payment
     * @return array
     */
    private function getExpressOrderPaymentActions(PaymentInterface $payment): array
    {
        $id = $payment->getAdditionalInformation(Method::ORDER_ID);
        $rvvupOrder = $this->sdkProxy->getOrder($id);

        if (!empty($rvvupOrder)) {
            return
                [
                    [
                        "type" => 'authorization',
                        "method" => 'redirect_url',
                        "value" => $rvvupOrder["redirectToCheckoutUrl"],
                    ],
                    [
                        "type" => 'cancel',
                        "method" => 'redirect_url',
                        "value" => $rvvupOrder['redirectToStoreUrl'],
                    ],
                ];
        }
        return [];
    }

    /**
     * @param CartInterface $quote
     * @return array
     * @throws LocalizedException
     * @throws AlreadyExistsException
     * @throws NotFoundException
     * @throws CommandException
     */
    private function createRvvupPayment(CartInterface $quote): array
    {
        $payment = $quote->getPayment();
        $result = $this->commandPool->get('createPayment')->execute([
            'payment' => $payment,
            'storeId' => (string) $quote->getStoreId()
        ]);
        $id = $result['data']['paymentCreate']['id'];
        $payment->setAdditionalInformation(Method::PAYMENT_ID, $id);
        $this->paymentResource->save($payment);
        return $result['data']['paymentCreate']['summary']['paymentActions'];
    }

    /**
     * Create & return a PaymentActionInterface Data object.
     *
     * @param array $paymentAction
     * @return PaymentActionInterface
     */
    private function getPaymentActionDataObject(array $paymentAction): PaymentActionInterface
    {
        /** @var PaymentActionInterface $paymentActionData */
        $paymentActionData = $this->paymentActionInterfaceFactory->create();

        if (isset($paymentAction['type'])) {
            $paymentActionData->setType(mb_strtolower($paymentAction['type']));
        }

        if (isset($paymentAction['method'])) {
            $paymentActionData->setMethod(mb_strtolower($paymentAction['method']));
        }

        if (isset($paymentAction['value'])) {
            // Don't lowercase value.
            $paymentActionData->setValue($paymentAction['value']);
        }

        return $paymentActionData;
    }
}
