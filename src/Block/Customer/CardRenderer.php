<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;

/**
 * Renders a saved Rvvup card in My Account -> Stored Payment Methods.
 */
class CardRenderer extends AbstractCardRenderer
{
    private const PAYMENT_METHOD_CODE = 'rvvup_CARD';

    /**
     * @param PaymentTokenInterface $token
     * @return bool
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === self::PAYMENT_METHOD_CODE;
    }

    /**
     * @return string
     */
    public function getNumberLast4Digits(): string
    {
        return (string)($this->getTokenDetails()['maskedCC'] ?? '');
    }

    /**
     * @return string
     */
    public function getExpDate(): string
    {
        return (string)($this->getTokenDetails()['expirationDate'] ?? '');
    }

    /**
     * @return string
     */
    public function getIconUrl(): string
    {
        return (string)$this->getIcon()['url'];
    }

    /**
     * @return int
     */
    public function getIconHeight(): int
    {
        return (int)$this->getIcon()['height'];
    }

    /**
     * @return int
     */
    public function getIconWidth(): int
    {
        return (int)$this->getIcon()['width'];
    }

    /**
     * @return array
     */
    private function getIcon(): array
    {
        return $this->getIconForType((string)($this->getTokenDetails()['type'] ?? ''));
    }
}
