<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

interface PaymentDataGetInterface
{
    /**
     * Get the Rvvup payment data from the API by Rvvup order ID.
     *
     * @param string $rvvupId
     * @param string $storeId
     * @return array
     */
    public function execute(string $rvvupId, string $storeId): array;
}
