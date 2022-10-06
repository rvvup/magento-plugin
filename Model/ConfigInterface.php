<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

interface ConfigInterface
{
    public const RVVUP_CONFIG = 'payment/rvvup/';

    /**
     * Validate whether Rvvup module & payment methods are active.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Get the endpoint URL.
     *
     * @return string
     */
    public function getEndpoint(): string;

    /**
     * Get the Merchant ID.
     *
     * @return string
     */
    public function getMerchantId(): string;

    /**
     * Get the Authorization Token.
     *
     * @return string
     */
    public function getAuthToken(): string;

    /**
     * Check whether debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugEnabled(): bool;
}
