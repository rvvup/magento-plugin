<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Canceled implements ProcessorInterface
{
    /** @var SessionManagerInterface|\Magento\Checkout\Model\Session\Proxy */
    private $checkoutSession;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var ManagerInterface */
    private $messageManager;

    /**
     * @param SessionManagerInterface $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        SessionManagerInterface $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
    }

    /**
     * @param OrderInterface $order
     * @param array $rvvupData
     * @param Redirect $redirect
     * @return Redirect
     */
    public function execute(OrderInterface $order, array $rvvupData, Redirect $redirect): void
    {
        $this->checkoutSession->restoreQuote();
        $order->cancel();
        $this->orderRepository->save($order);
        $this->messageManager->addErrorMessage(__('Order Canceled'));
        $redirect->setPath('checkout/cart');
    }
}
