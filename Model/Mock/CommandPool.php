<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Mock;

use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;

class CommandPool implements CommandPoolInterface, CommandInterface
{

    public function get($commandCode)
    {
        return $this;
    }

    public function execute(array $commandSubject)
    {
        return;
    }
}
