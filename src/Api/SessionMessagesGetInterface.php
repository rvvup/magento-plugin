<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface SessionMessagesGetInterface
{
    /**
     * Get the Rvvup Payments session messages.
     *
     * @return \Rvvup\Payments\Api\Data\SessionMessageInterface[]
     */
    public function execute(): array;
}
