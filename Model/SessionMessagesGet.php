<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use LogicException;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;
use Magento\Framework\Message\ManagerInterface;
use Rvvup\Payments\Api\SessionMessagesGetInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterfaceFactory;
use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;

class SessionMessagesGet implements SessionMessagesGetInterface
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var InterpretationStrategyInterface
     */
    private $messageInterpretationStrategy;

    /**
     * @var SessionMessageInterfaceFactory
     */
    private $sessionMessageFactory;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param ManagerInterface $messageManager
     * @param InterpretationStrategyInterface $messageInterpretationStrategy
     * @param SessionMessageInterfaceFactory $sessionMessageFactory
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        ManagerInterface $messageManager,
        InterpretationStrategyInterface $messageInterpretationStrategy,
        SessionMessageInterfaceFactory $sessionMessageFactory,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->messageManager = $messageManager;
        $this->messageInterpretationStrategy = $messageInterpretationStrategy;
        $this->sessionMessageFactory = $sessionMessageFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get the Rvvup Payments session messages.
     *
     * @return SessionMessageInterface[]
     */
    public function execute(): array
    {
        $messages = [];

        // If module not active for store, return empty array.
        if (!$this->config->getActiveConfig()) {
            return $messages;
        }

        // Get all messages & clear them from the storage.
        $messageCollection = $this->messageManager->getMessages(true, SessionMessageInterface::MESSAGE_GROUP);

        // If no messages for group, return empty array.
        if (!$messageCollection) {
            return $messages;
        }

        foreach ($messageCollection->getItems() as $message) {
            try {
                /** @var SessionMessageInterface $sessionMessage */
                $sessionMessage = $this->sessionMessageFactory->create();
                $sessionMessage->setType($message->getType());
                $sessionMessage->setText($this->messageInterpretationStrategy->interpret($message));

                $messages[] = $sessionMessage;
            } catch (LogicException $ex) {
                if (!$this->config->isDebugEnabled()) {
                    continue;
                }

                $this->logger->debug('Failed to interpret message with error: ' . $ex->getMessage());
            }
        }

        return $messages;
    }
}
