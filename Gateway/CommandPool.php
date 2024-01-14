<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway;

use Magento\Framework\ObjectManager\TMapFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;

class CommandPool implements CommandPoolInterface, CommandInterface
{
    /**
     * @var CommandInterface[]
     */
    private $commands;

    /**
     * @param TMapFactory $tmapFactory
     * @param array $commands
     */
    public function __construct(
        TMapFactory $tmapFactory,
        array $commands = []
    ) {
        $this->commands = $tmapFactory->create(
            [
                'array' => $commands,
                'type' => CommandInterface::class
            ]
        );
    }

    /** @inheritDoc */
    public function get($commandCode)
    {
        if (!isset($this->commands[$commandCode])) {
            return $this;
        }

        return $this->commands[$commandCode];
    }

    // phpcs:ignore
    /**
     * Mock execute command
     * @param array $commandSubject
     * @return void
     */
    public function execute(array $commandSubject)
    {
    }
}
