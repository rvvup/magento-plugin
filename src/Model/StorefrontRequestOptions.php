<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

/**
 * HTTP options for Rvvup lookups made while rendering storefront pages (head scripts, PayPal and Clearpay widgets).
 *
 * These lookups are optional enhancements to a page, so they must not hold the page up when the API is slow.
 * Never use them for checkout, order placement or any other flow that needs the real answer.
 */
class StorefrontRequestOptions
{
    public const DEFAULT_TIMEOUT = 3.0;

    public const DEFAULT_CONNECT_TIMEOUT = 1.0;

    /**
     * @var float
     */
    private $timeout;

    /**
     * @var float
     */
    private $connectTimeout;

    /**
     * @param float $timeout Overall request timeout in seconds.
     * @param float $connectTimeout Connection timeout in seconds.
     */
    public function __construct(
        float $timeout = self::DEFAULT_TIMEOUT,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT
    ) {
        $this->timeout = $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
        $this->connectTimeout = $connectTimeout > 0 ? $connectTimeout : self::DEFAULT_CONNECT_TIMEOUT;
    }

    /**
     * Options in the format accepted by the SDK's `$inputOptions` (merged into the Guzzle request options).
     *
     * @return array{timeout: float, connect_timeout: float}
     */
    public function get(): array
    {
        return [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
        ];
    }
}
