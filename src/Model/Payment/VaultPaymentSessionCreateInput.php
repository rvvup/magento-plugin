<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Rvvup\Api\Model\PaymentSessionCreateInput;

/**
 * Extends the SDK's PaymentSessionCreateInput to carry the optional saveTokenScope field,
 * which the base OpenAPI-generated model does not define.
 */
class VaultPaymentSessionCreateInput extends PaymentSessionCreateInput
{
    public function __construct(?array $data = null)
    {
        parent::__construct($data);
        $this->container['save_token_scope'] = $data['save_token_scope'] ?? null;
    }

    public static function openAPITypes(): array
    {
        return array_merge(parent::openAPITypes(), ['save_token_scope' => 'string']);
    }

    public static function openAPIFormats(): array
    {
        return array_merge(parent::openAPIFormats(), ['save_token_scope' => null]);
    }

    public static function attributeMap(): array
    {
        return array_merge(parent::attributeMap(), ['save_token_scope' => 'saveTokenScope']);
    }

    public static function setters(): array
    {
        return array_merge(parent::setters(), ['save_token_scope' => 'setSaveTokenScope']);
    }

    public static function getters(): array
    {
        return array_merge(parent::getters(), ['save_token_scope' => 'getSaveTokenScope']);
    }

    public function getSaveTokenScope(): ?string
    {
        return $this->container['save_token_scope'];
    }

    public function setSaveTokenScope(string $saveTokenScope): static
    {
        $this->container['save_token_scope'] = $saveTokenScope;
        return $this;
    }
}
