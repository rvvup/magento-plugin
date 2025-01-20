<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\DataObject;
use Rvvup\Payments\Api\Data\PaymentActionInterface;

class PaymentAction extends DataObject implements PaymentActionInterface
{
    /**
     * Get the Rvvup Payment Action type
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->getData(self::TYPE);
    }

    /**
     * Set the Rvvup Payment Action type
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void
    {
        $this->setData(self::TYPE, $type);
    }

    /**
     * Get the Rvvup Payment Action method
     *
     * @return string|null
     */
    public function getMethod(): ?string
    {
        return $this->getData(self::METHOD);
    }

    /**
     * Set the Rvvup Payment Action method
     *
     * @param string $method
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->setData(self::METHOD, $method);
    }

    /**
     * Get the Rvvup Payment Action value
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->getData(self::VALUE);
    }

    /**
     * Set the Rvvup Payment Action method
     *
     * @param string $value
     * @return void
     */
    public function setValue(string $value): void
    {
        $this->setData(self::VALUE, $value);
    }
}
