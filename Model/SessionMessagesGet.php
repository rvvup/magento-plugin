<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use LogicException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterfaceFactory;
use Rvvup\Payments\Api\SessionMessagesGetInterface;

class SessionMessagesGet implements SessionMessagesGetInterface
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    /**
     * @var \Magento\Framework\View\Element\Message\InterpretationStrategyInterface
     */
    private $messageInterpretationStrategy;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Rvvup\Payments\Api\Data\SessionMessageInterfaceFactory
     */
    private $sessionMessageFactory;

    /**
     * @var \Rvvup\Payments\Model\ConfigInterface
     */
    private $config;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\View\Element\Message\InterpretationStrategyInterface $messageInterpretationStrategy
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Rvvup\Payments\Api\Data\SessionMessageInterfaceFactory $sessionMessageFactory
     * @param \Rvvup\Payments\Model\ConfigInterface $config
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        ManagerInterface $messageManager,
        InterpretationStrategyInterface $messageInterpretationStrategy,
        StoreManagerInterface $storeManager,
        SessionMessageInterfaceFactory $sessionMessageFactory,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->messageManager = $messageManager;
        $this->messageInterpretationStrategy = $messageInterpretationStrategy;
        $this->storeManager = $storeManager;
        $this->sessionMessageFactory = $sessionMessageFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get the Rvvup Payments session messages.
     *
     * @return \Rvvup\Payments\Api\Data\SessionMessageInterface[]
     */
    public function execute(): array
    {
        $messages = [];
        $store = $this->getStore();

        // If module not active for store, return empty array.
        if (!$this->config->getActiveConfig(
            ScopeInterface::SCOPE_STORE,
            $store === null ? null : $store->getCode()
        )) {
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
                /** @var \Rvvup\Payments\Api\Data\SessionMessageInterface $sessionMessage */
                $sessionMessage = $this->sessionMessageFactory->create();
                $sessionMessage->setType($message->getType());
                $sessionMessage->setText($this->messageInterpretationStrategy->interpret($message));

                $messages[] = $sessionMessage;
            } catch (LogicException $ex) {
                if (!$this->config->isDebugEnabled(
                    ScopeInterface::SCOPE_STORE,
                    $store === null ? null : $store->getCode()
                )) {
                    continue;
                }

                $this->logger->debug('Failed to interpret message with error: ' . $ex->getMessage());
            }
        }

        return $messages;
    }

    /**
     * Get current store.
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    private function getStore(): ?StoreInterface
    {
        try {
            return $this->storeManager->getStore();
        } catch (LocalizedException $ex) {
            return null;
        }
    }
}
