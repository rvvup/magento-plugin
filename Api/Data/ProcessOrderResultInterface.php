<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api\Data;

interface ProcessOrderResultInterface
{
    /**
     * Public constants for data attributes.
     */
    public const RESULT_TYPE = 'result_type';
    public const REDIRECT_PATH = 'redirect_path';
    public const CUSTOMER_MESSAGE = 'customer_message';

    /**
     * Public constants for data attribute values.
     * ToDo: Find better constants for this as it could be confusing. error is for cancelled/declined payments
     */
    public const RESULT_TYPE_SUCCESS = 'success';
    public const RESULT_TYPE_ERROR = 'error';

    /**
     * Get the result type.
     *
     * @return string|null
     */
    public function getResultType(): ?string;

    /**
     * Set the result type.
     *
     * @param string $resultType
     * @return void
     */
    public function setResultType(string $resultType): void;

    /**
     * Get the expected Redirect Path.
     *
     * @return string|null
     */
    public function getRedirectPath(): ?string;

    /**
     * Set the expected Redirect Path.
     *
     * @param string $redirectPath
     * @return void
     */
    public function setRedirectPath(string $redirectPath): void;

    /**
     * Get the customer message.
     *
     * @return string|null
     */
    public function getCustomerMessage(): ?string;

    /**
     * Set the customer message.
     *
     * @param string $customerMessage
     * @return void
     */
    public function setCustomerMessage(string $customerMessage): void;
}
