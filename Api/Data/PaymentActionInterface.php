<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api\Data;

interface PaymentActionInterface
{
    /**
     * Public constants for data attributes.
     */
    public const TYPE = 'type';
    public const METHOD = 'method';
    public const VALUE = 'value';

    /**
     * Get the Rvvup Payment Action type
     *
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * Set the Rvvup Payment Action type
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void;

    /**
     * Get the Rvvup Payment Action method
     *
     * @return string|null
     */
    public function getMethod(): ?string;

    /**
     * Set the Rvvup Payment Action method
     *
     * @param string $method
     * @return void
     */
    public function setMethod(string $method): void;

    /**
     * Get the Rvvup Payment Action value
     *
     * @return string|null
     */
    public function getValue(): ?string;

    /**
     * Set the Rvvup Payment Action method
     *
     * @param string $value
     * @return void
     */
    public function setValue(string $value): void;
}
