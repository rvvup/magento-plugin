<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\RvvupConfigProvider;

class Comment implements ProcessorInterface
{
    public const TYPE = 'comment';

    /** @var ProcessOrderResultInterfaceFactory */
    private $processOrderResultFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /**
     * @param ProcessOrderResultInterfaceFactory $processOrderResultFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProcessOrderResultInterfaceFactory $processOrderResultFactory,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->processOrderResultFactory = $processOrderResultFactory;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface $order
     * @param array $rvvupData
     * @param string $origin
     * @return ProcessOrderResultInterface
     * @throws PaymentValidationException
     */
    public function execute(OrderInterface $order, array $rvvupData, string $origin): ProcessOrderResultInterface
    {
        if (strpos($order->getPayment()->getMethod(), RvvupConfigProvider::CODE) !== 0) {
            throw new PaymentValidationException(__('Order is not paid via Rvvup Payment Link'));
        }

        /** @var ProcessOrderResultInterface $processOrderResult */
        $processOrderResult = $this->processOrderResultFactory->create();

        try {
            $message = 'Rvvup payment link order was updated by webhook with rvvup status : '
                . $rvvupData['payments'][0]['status'] . ', Rvvup payment id: ' . $rvvupData['payments'][0]['id'];
            $order->addCommentToStatusHistory($message);
            $this->orderRepository->save($order);
        } catch (Exception $e) {
            $this->logger->error('Error during processing order comment on status: ' . $e->getMessage(), [
                'order_id' => $order->getEntityId()
            ]);
        }

        return $processOrderResult;
    }
}
