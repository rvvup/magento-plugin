<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Framework\Exception\LocalizedException;

class ProcessorPool
{
    /** @var array */
    private $processors = [];

    /** @var array */
    private $paymentLinkProcessors = [];

    /**
     * @param array $processors
     * @param array $paymentLinkProcessors
     */
    public function __construct(
        array $processors,
        array $paymentLinkProcessors
    ) {
        $this->processors = $processors;
        $this->paymentLinkProcessors = $paymentLinkProcessors;
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
        throw new LocalizedException(__('No OrderProcessor for status %1', $status));
    }

    /**
     * @param string $status
     * @return ProcessorInterface
     * @throws LocalizedException
     */
    public function getPaymentLinkProcessor(string $status): ProcessorInterface
    {
        if (isset($this->paymentLinkProcessors[$status])) {
            return $this->paymentLinkProcessors[$status];
        }
        throw new LocalizedException(__('No Payment Link Processor for status %1', $status));
    }
}
