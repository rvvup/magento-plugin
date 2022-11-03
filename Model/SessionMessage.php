<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\DataObject;
use Rvvup\Payments\Api\Data\SessionMessageInterface;

class SessionMessage extends DataObject implements SessionMessageInterface
{
    /**
     * Get the Rvvup Payment Session Message type.
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->getData(self::TYPE);
    }

    /**
     * Set the Rvvup Payment Session Message type.
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void
    {
        $this->setData(self::TYPE, $type);
    }

    /**
     * Get the Rvvup Payment Session Message text.
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->getData(self::TEXT);
    }

    /**
     * Set the Rvvup Payment Session Message text.
     *
     * @param string $text
     * @return void
     */
    public function setText(string $text): void
    {
        $this->setData(self::TEXT, $text);
    }
}
