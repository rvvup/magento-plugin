<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessRefund;

interface ProcessorInterface
{
    /**
     * @param array $payload
     * @return void
     */
    public function execute(array $payload): void;
}
