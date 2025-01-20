<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessRefund;

use Magento\Framework\Exception\LocalizedException;

class ProcessorPool
{
    /** @var array */
    private $processors = [];

    /**
     * @param array $processors
     */
    public function __construct(
        array $processors
    ) {
        $this->processors = $processors;
    }

    /**
     * @param string $status
     * @return ProcessorInterface
     * @throws LocalizedException
     */
    public function getProcessor(string $status): ProcessorInterface
    {
        if (isset($this->processors[$status])) {
            return $this->processors[$status];
        }
        throw new LocalizedException(__('No RefundProcessor for status %1', $status));
    }
}
