<?php declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Restriction\Messages;

class CartRestrictionsWarning implements ObserverInterface
{
    /** @var ConfigInterface */
    private $config;
    /** @var Session */
    private $checkoutSession;
    /** @var Messages */
    private $messages;
    /** @var ManagerInterface */
    private $messageManager;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ConfigInterface $config
     * @param SessionManagerInterface $checkoutSession
     * @param Messages $messages
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        SessionManagerInterface $checkoutSession,
        Messages $messages,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->messages = $messages;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            if (!$this->config->isActive()) {
                return;
            }
            $hasRestrictedItems = false;
            $quote = $this->checkoutSession->getQuote();
            foreach ($quote->getAllItems() as $item) {
                if ($item->getProduct()->getRvvupRestricted()) {
                    $hasRestrictedItems = true;
                    break;
                }
            }
            if ($hasRestrictedItems) {
                if (!$this->messageManager->getMessages()->getItems()) {
                    $this->messageManager->addWarningMessage(
                        $this->messages->getCheckoutMessage()
                    );
                }
            }
        } catch (\Exception $e) {
            // Fail gracefully
            $this->logger->error(
                'Error whilst deciding to show cart restriction message: ' . $e->getMessage()
            );
        }
    }
}
