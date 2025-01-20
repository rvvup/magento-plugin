<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api\Data;

use Rvvup\Payments\Gateway\Method;

interface SessionMessageInterface
{
    /**
     * Public constants for data attributes.
     */
    public const TYPE = 'type';
    public const TEXT = 'text';

    /**
     * Public constants for the groups where Rvvup messages are stored in the Message Manager.
     */
    public const MESSAGE_GROUP = Method::PAYMENT_TITLE_PREFIX . 'payment_messages';

    /**
     * Get the Rvvup Payment Session Message type.
     *
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * Set the Rvvup Payment Session Message type.
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void;

    /**
     * Get the Rvvup Payment Session Message text.
     *
     * @return string|null
     */
    public function getText(): ?string;

    /**
     * Set the Rvvup Payment Session Message text.
     *
     * @param string $text
     * @return void
     */
    public function setText(string $text): void;
}
