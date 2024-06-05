<?php

namespace Rvvup\Payments\Api\Data;

interface HashInterface
{
    /**
     * String constants for property names
     */
    public const HASH_ID = "hash_id";
    public const HASH = "hash";
    public const QUOTE_ID = "quote_id";
    public const CREATED_AT = "created_at";

    /**
     * Getter for HashId.
     *
     * @return int|null
     */
    public function getHashId(): ?int;

    /**
     * Setter for HashId.
     *
     * @param int|null $hashId
     *
     * @return void
     */
    public function setHashId(?int $hashId): void;

    /**
     * Getter for Hash.
     *
     * @return string|null
     */
    public function getHash(): ?string;

    /**
     * Setter for Hash.
     *
     * @param string|null $hash
     *
     * @return void
     */
    public function setHash(?string $hash): void;


    /**
     * Getter for QuoteID.
     *
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * Setter for QuoteID.
     *
     * @param int $id
     * @return void
     */
    public function setQuoteId(int $id): void;

    /**
     * Getter for CREATED_AT.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Setter for CREATED_AT.
     *
     * @param int $id
     * @return void
     */
    public function setCreatedAt(int $id): void;
}
