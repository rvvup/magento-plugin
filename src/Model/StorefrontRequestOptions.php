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
     * The parameters are deliberately untyped: values set in di.xml with xsi:type="number" reach the constructor as
     * strings, and a typed float parameter would throw a TypeError wherever the object is built in strict mode.
     *
     * @param float|int|string $timeout Overall request timeout in seconds.
     * @param float|int|string $connectTimeout Connection timeout in seconds.
     */
    public function __construct(
        $timeout = self::DEFAULT_TIMEOUT,
        $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT
    ) {
        $this->timeout = is_numeric($timeout) && (float) $timeout > 0
            ? (float) $timeout
            : self::DEFAULT_TIMEOUT;
        $this->connectTimeout = is_numeric($connectTimeout) && (float) $connectTimeout > 0
            ? (float) $connectTimeout
            : self::DEFAULT_CONNECT_TIMEOUT;
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
