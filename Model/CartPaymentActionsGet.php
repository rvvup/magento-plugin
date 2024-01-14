<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\CartPaymentActionsGetInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory;
use Rvvup\Payments\Gateway\Method;
use Throwable;

class CartPaymentActionsGet implements CartPaymentActionsGetInterface
{
    /**
     * @var \Magento\Quote\Api\PaymentMethodManagementInterface
     */
    private $paymentMethodManagement;

    /**
     * @var \Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory
     */
    private $paymentActionInterfaceFactory;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param PaymentMethodManagementInterface $paymentMethodManagement
     * @param PaymentActionInterfaceFactory $paymentActionInterfaceFactory
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $cartRepository
     * @param CommandPoolInterface $commandPool
     */
    public function __construct(
        PaymentMethodManagementInterface $paymentMethodManagement,
        PaymentActionInterfaceFactory $paymentActionInterfaceFactory,
        LoggerInterface $logger,
        CartRepositoryInterface $cartRepository,
        CommandPoolInterface $commandPool
    ) {
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->paymentActionInterfaceFactory = $paymentActionInterfaceFactory;
        $this->logger = $logger;
        $this->cartRepository = $cartRepository;
        $this->commandPool = $commandPool;
    }

    /**
     * Get the payment actions for the specified cart ID.
     *
     * @param string $cartId
     * @param bool $expressActions
     * @return PaymentActionInterface[]
     * @throws LocalizedException
     */
    public function execute(string $cartId, bool $expressActions = false): array
    {
        $payment = $this->paymentMethodManagement->get($cartId);

        if ($payment === null) {
            return [];
        }
        $cart = $this->cartRepository->get($cartId);

        $paymentActions = $this->getPaymentActions($payment, $cart, $expressActions);

        // Check if payment actions are set as array & not empty
        if (empty($paymentActions) || !is_array($paymentActions)) {
            return [];
        }

        $paymentActionsDataArray = [];

        try {
            foreach ($paymentActions as $paymentAction) {
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
                'Error loading Payment Actions. Failed return result with message: ' . $t->getMessage(),
                [
                    'quote_id' => $cartId,
                ]
            );

            throw new LocalizedException(__('Something went wrong'));
        }

        return $paymentActionsDataArray;
    }

    /**
     * Get the additional information data that hold the payment actions.
     *
     * Get either standard or the ones saved in the express payment data field.
     * @param PaymentInterface $payment
     * @param bool $expressActions
     * @param Quote $cart
     * @return array|mixed|null
     * @throws NotFoundException
     * @throws CommandException
     */
    private function getPaymentActions(PaymentInterface $payment, Quote $cart, bool $expressActions = false)
    {
        if (!$expressActions) {
            return $payment->getAdditionalInformation('paymentActions');
        }
        $this->commandPool->get('initialize')->execute(['quote' => $cart]);
        $data = $this->commandPool->get('createPayment')->execute(['payment' => $payment]);
        return $data['data']['paymentCreate']['summary']['paymentActions'];
    }

    /**
     * Create & return a PaymentActionInterface Data object.
     *
     * @param array $paymentAction
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface
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
