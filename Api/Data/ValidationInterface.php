<?php
declare(strict_types=1);

namespace Rvvup\Payments\Api\Data;

use Magento\Quote\Model\Quote;

interface ValidationInterface
{
    /**
     * Properties used
     */
    public const IS_VALID = "is_valid";
    public const ORDER_ID = "order_id";
    public const REDIRECT_TO_CART = "redirect_to_cart";
    public const REDIRECT_TO_CHECKOUT_PAYMENT = "redirect_to_checkout_payment";
    public const RESTORE_QUOTE = "restore_quote";
    public const MESSAGE = "message";
    public const ALREADY_EXISTS = "already_exists";

    /**
     * @param Quote $quote
     * @param string $lastTransactionId
     * @param string|null $rvvupId
     * @param string|null $paymentStatus
     * @param string|null $origin
     * @return ValidationInterface
     */
    public function validate(
        Quote &$quote,
        string &$lastTransactionId,
        string $rvvupId = null,
        string $paymentStatus = null,
        string $origin = null
    ): ValidationInterface;
}
