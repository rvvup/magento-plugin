<?php declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface PaymentRedirectInterface
{
    /**
     * @param string $customerId
     * @return string
     */
    public function getCustomerRedirectUrl(string $customerId): string;

    /**
     * @param string $cartId
     * @return string
     */
    public function getGuestRedirectUrl(string $cartId): string;
}
