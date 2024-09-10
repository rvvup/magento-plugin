<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

interface PaymentDataGetInterface
{
    /**
     * Get the Rvvup payment data from the API by Rvvup order ID.
     *
     * @param int $storeId
     * @param string $rvvupId
     * @return array
     */
    public function execute(int $storeId, string $rvvupId): array;
}
