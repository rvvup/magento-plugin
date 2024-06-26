<?php

namespace Rvvup\Payments\Api\Data;

interface LogInterface
{
    /**
     * String constants for property names
     */
    public const ENTITY_ID = "entity_id";
    public const PAYLOAD = "payload";
    public const IS_PROCESSED = "is_processed";

    /**
     * Getter for EntityId.
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Setter for EntityId.
     *
     * @param int|null $entityId
     *
     * @return void
     */
    public function setEntityId(?int $entityId): void;

    /**
     * Getter for Payload.
     *
     * @return string|null
     */
    public function getPayload(): ?string;

    /**
     * Setter for Payload.
     *
     * @param string|null $payload
     *
     * @return void
     */
    public function setPayload(?string $payload): void;

    /**
     * Getter for IsProcessed.
     *
     * @return bool|null
     */
    public function getIsProcessed(): ?bool;

    /**
     * Setter for IsProcessed.
     *
     * @param bool|null $isProcessed
     *
     * @return void
     */
    public function setIsProcessed(?bool $isProcessed): void;
}
