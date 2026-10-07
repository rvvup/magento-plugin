<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Rvvup\Api\Model\PaymentSessionCreateInput;
use Rvvup\ApiException;
use Rvvup\Payments\Gateway\Method;

class SavedTokenDataBuilder
{
    /**
     * Rvvup limits the session key to 64 characters.
     */
    private const SESSION_KEY_MAX_LENGTH = 64;

    /**
     * Provider code the tokens are stored under (see VaultDetailsHandler).
     */
    private const PAYMENT_METHOD_CODE = 'rvvup_CARD';

    /**
     * What the backend answers with 400 when it cannot use the token.
     */
    private const REJECTION_MESSAGES = [
        'saved token not found',
        'saved token has been revoked',
        'saved token is expired',
    ];

    /** @var PaymentTokenManagementInterface */
    private $paymentTokenManagement;

    /**
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     */
    public function __construct(PaymentTokenManagementInterface $paymentTokenManagement)
    {
        $this->paymentTokenManagement = $paymentTokenManagement;
    }

    /**
     * Points the payment session at the shopper's saved card when the payment carries a Vault public hash.
     * The backend only checks that the token belongs to the merchant, so the token is resolved here for the
     * customer on the quote and anything else is rejected.
     *
     * @param PaymentSessionCreateInput $input
     * @param InfoInterface $payment
     * @param int $customerId Customer id of the server side quote, never a client supplied value.
     * @param string $checkoutId
     * @param string $reservedOrderId
     * @return bool True when a saved card is used for the session.
     * @throws LocalizedException When the saved card is not usable by this customer.
     */
    public function build(
        PaymentSessionCreateInput $input,
        InfoInterface $payment,
        int $customerId,
        string $checkoutId,
        string $reservedOrderId
    ): bool {
        $publicHash = (string)$payment->getAdditionalInformation(Method::PUBLIC_HASH);

        if ($publicHash === '') {
            return false;
        }

        $token = $this->getToken($publicHash, $customerId);

        // A saved card payment never saves a card, so a flag left by an earlier attempt must not survive.
        $payment->unsAdditionalInformation(Method::SAVE_TOKEN_REQUESTED);

        $input->setSavedTokenId((string)$token->getGatewayToken());
        // The key is idempotent on the server, the suffix keeps it apart from a card session on the same quote.
        $input->setSessionKey(substr($checkoutId . '.' . $reservedOrderId . '.saved', 0, self::SESSION_KEY_MAX_LENGTH));

        return true;
    }

    /**
     * Whether the backend refused the session because the saved token is missing, revoked or expired.
     *
     * @param ApiException $e
     * @return bool
     */
    public function isTokenRejection(ApiException $e): bool
    {
        if ($e->getCode() !== 400) {
            return false;
        }

        $body = $e->getResponseBody();
        $text = strtolower($e->getMessage() . ' ' . (is_scalar($body) ? $body : json_encode($body)));

        foreach (self::REJECTION_MESSAGES as $message) {
            if (strpos($text, $message) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $publicHash
     * @param int $customerId
     * @return PaymentTokenInterface
     * @throws LocalizedException
     */
    private function getToken(string $publicHash, int $customerId): PaymentTokenInterface
    {
        // Without a customer the lookup would match tokens that have no customer at all.
        $token = $customerId > 0 ? $this->paymentTokenManagement->getByPublicHash($publicHash, $customerId) : null;

        if ($token === null
            || !$token->getIsActive()
            || $token->getPaymentMethodCode() !== self::PAYMENT_METHOD_CODE
            || (string)$token->getGatewayToken() === ''
            || $this->isExpired($token)
        ) {
            throw new LocalizedException($this->getUnusableMessage());
        }

        return $token;
    }

    /**
     * @param PaymentTokenInterface $token
     * @return bool
     */
    private function isExpired(PaymentTokenInterface $token): bool
    {
        $expiresAt = $token->getExpiresAt();

        return $expiresAt !== null && strtotime($expiresAt . ' UTC') <= time();
    }

    /**
     * @return Phrase
     */
    public function getUnusableMessage(): Phrase
    {
        return __('This saved card can no longer be used. Please use a different card.');
    }
}
