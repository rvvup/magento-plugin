<?php declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface ClearpayAvailabilityInterface
{
    /**
     * @return bool
     */
    public function isAvailable(): bool;
}
