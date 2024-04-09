<?php 
declare(strict_types=1);

namespace Rvvup\Payments\Api\Data;

interface LogInterface
{
    /**
     * String constants for property names
     */
    public const LOG_ID = "log_id";
    public const PAYLOAD = "payload";

    /**
     * Getter for Id.
     *
     * @return int|null
     */
    public function getId();

    /**
     * Setter for Id.
     *
     * @param int|null $id
     *
     * @return void
     */
    public function setId($id);

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
}
