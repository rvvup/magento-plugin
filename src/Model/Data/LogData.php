<?php

namespace Rvvup\Payments\Model\Data;

use Magento\Framework\DataObject;
use Rvvup\Payments\Api\Data\LogInterface;

class LogData extends DataObject implements LogInterface
{
    /**
     * Getter for EntityId.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) === null ? null
            : (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * Setter for EntityId.
     *
     * @param int|null $entityId
     *
     * @return void
     */
    public function setEntityId(?int $entityId): void
    {
        $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Getter for Payload.
     *
     * @return string|null
     */
    public function getPayload(): ?string
    {
        return $this->getData(self::PAYLOAD);
    }

    /**
     * Setter for Payload.
     *
     * @param string|null $payload
     *
     * @return void
     */
    public function setPayload(?string $payload): void
    {
        $this->setData(self::PAYLOAD, $payload);
    }

    /**
     * Getter for IsProcessed.
     *
     * @return bool|null
     */
    public function getIsProcessed(): ?bool
    {
        return $this->getData(self::IS_PROCESSED) === null ? null
            : (bool)$this->getData(self::IS_PROCESSED);
    }

    /**
     * Setter for IsProcessed.
     *
     * @param bool|null $isProcessed
     *
     * @return void
     */
    public function setIsProcessed(?bool $isProcessed): void
    {
        $this->setData(self::IS_PROCESSED, $isProcessed);
    }
}
