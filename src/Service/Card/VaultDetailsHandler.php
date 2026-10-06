<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service\Card;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\ApiProvider;
use Throwable;

/**
 * Persists the card token saved by Rvvup for a successful payment to the Magento Vault.
 */
class VaultDetailsHandler
{
    /** Payment statuses after which Rvvup has a saved card token available */
    public const TOKEN_PAYMENT_STATUSES = [Method::STATUS_SUCCEEDED, Method::STATUS_AUTHORIZED];

    private const PAYMENT_METHOD_CODE = 'rvvup_CARD';
    private const FALLBACK_CARD_TYPE = 'OT';

    /** Rvvup cardBrand (normalised) => Magento CC type code */
    private const CARD_TYPES = [
        'visa' => 'VI',
        'mastercard' => 'MC',
        'amex' => 'AE',
        'americanexpress' => 'AE',
        'discover' => 'DI',
        'diners' => 'DN',
        'dinersclub' => 'DN',
        'jcb' => 'JCB',
        'maestro' => 'MI',
        'unionpay' => 'UN',
    ];

    /** @var ApiProvider */
    private $apiProvider;

    /** @var PaymentTokenFactoryInterface */
    private $paymentTokenFactory;

    /** @var PaymentTokenManagementInterface */
    private $paymentTokenManagement;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ApiProvider $apiProvider
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        ApiProvider $apiProvider,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        LoggerInterface $logger
    ) {
        $this->apiProvider = $apiProvider;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->logger = $logger;
    }

    /**
     * Never throws: the payment has already succeeded, so a token failure must not affect order completion.
     *
     * @param OrderInterface $order
     * @param string $paymentSessionId
     * @return void
     */
    public function process(OrderInterface $order, string $paymentSessionId): void
    {
        try {
            $payment = $order->getPayment();
            $customerId = (int)$order->getCustomerId();

            if ($customerId === 0
                || $payment === null
                || (string)$payment->getAdditionalInformation(Method::SAVE_TOKEN_REQUESTED) !== '1'
            ) {
                return;
            }

            $savedToken = $this->apiProvider->getSdk((string)$order->getStoreId())
                ->paymentSessions()
                ->getSavedToken($paymentSessionId);

            if ($savedToken === null) {
                return;
            }

            $gatewayToken = (string)$savedToken->getId();

            if ($this->paymentTokenManagement->getByGatewayToken(
                $gatewayToken,
                self::PAYMENT_METHOD_CODE,
                $customerId
            )) {
                return;
            }

            $month = str_pad((string)$savedToken->getCardExpiryMonth(), 2, '0', STR_PAD_LEFT);
            $year = (string)$savedToken->getCardExpiryYear();

            $token = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
            $token->setGatewayToken($gatewayToken);
            $token->setPaymentMethodCode(self::PAYMENT_METHOD_CODE);
            $token->setCustomerId($customerId);
            $token->setIsActive(true);
            $token->setIsVisible(true);
            $token->setPublicHash(hash('sha256', implode('|', [
                $customerId,
                self::PAYMENT_METHOD_CODE,
                $gatewayToken,
            ])));
            $token->setExpiresAt($this->getExpiresAt($month, $year));
            $token->setTokenDetails((string)json_encode([
                'type' => $this->mapCardType((string)$savedToken->getCardBrand()),
                'maskedCC' => (string)$savedToken->getCardLast4(),
                'expirationDate' => $month . '/' . $year,
            ]));

            $this->paymentTokenManagement->saveTokenWithPaymentLink($token, $payment);
        } catch (Throwable $e) {
            // Deliberately no token data in the log.
            $this->logger->error(
                'Rvvup failed to save the card token to the Vault: ' . $e->getMessage(),
                [Method::ORDER_ID => $paymentSessionId]
            );
        }
    }

    /**
     * @param string $brand
     * @return string
     */
    public function mapCardType(string $brand): string
    {
        $key = preg_replace('/[^a-z]/', '', strtolower($brand));

        return self::CARD_TYPES[$key] ?? self::FALLBACK_CARD_TYPE;
    }

    /**
     * The card is valid through the end of its expiry month, i.e. until the start of the next one (UTC).
     *
     * @param string $month
     * @param string $year
     * @return string
     * @throws \Exception
     */
    public function getExpiresAt(string $month, string $year): string
    {
        return (new DateTimeImmutable($year . '-' . $month . '-01 00:00:00', new DateTimeZone('UTC')))
            ->add(new DateInterval('P1M'))
            ->format('Y-m-d H:i:s');
    }
}
