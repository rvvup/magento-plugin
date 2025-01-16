<?php

namespace Rvvup\Payments\Traits;

use Rvvup\Payments\Exception\PaymentValidationException;

trait HasRvvupDataTrait
{
    /**
     * The default key for the Rvvup Payment ID.
     *
     * @var string
     */
    private $defaultIdKey = 'id';

    /**
     * The default key for the Rvvup Payment Status.
     *
     * @var string
     */
    private $defaultStatusKey = 'status';

    /**
     * Data must include Rvvup payment ID or use the alternative key where the ID is provided to the array.
     *
     * @param array $data
     * @param string|null $alternativeIdKey
     * @return void
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    protected function validateIdExists(array $data, ?string $alternativeIdKey = null): void
    {
        if (!isset($data[$alternativeIdKey ?? $this->defaultIdKey])) {
            throw new PaymentValidationException(__('Invalid Rvvup ID'));
        }
    }

    /**
     * Data must include Rvvup payment Status or use the alternative key where the Status is provided to the array.
     *
     * @param array $data
     * @param array $allowedStatuses
     * @param string|null $alternativeStatusKey
     * @return void
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    protected function validateStatusAllowed(
        array $data,
        array $allowedStatuses,
        ?string $alternativeStatusKey = null
    ): void {
        $statusKey = $alternativeStatusKey ?? $this->defaultStatusKey;

        if (!isset($data[$statusKey]) || !in_array($data[$statusKey], $allowedStatuses, true)) {
            throw new PaymentValidationException(__('Invalid Rvvup status'));
        }
    }
}
