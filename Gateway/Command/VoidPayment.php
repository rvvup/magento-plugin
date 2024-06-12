<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Payment;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Cache;

class VoidPayment implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /** @var InvoiceRepository */
    private $invoiceRepository;

    /**
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var Cache */
    private $cache;

    /** @var array */
    private $cancelStatuses;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var OrderConfig */
    private $config;

    /**
     * @param SdkProxy $sdkProxy
     * @param InvoiceRepository $invoiceRepository
     * @param Cache $cache
     * @param array $cancelStatuses
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderConfig $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        SdkProxy $sdkProxy,
        InvoiceRepository $invoiceRepository,
        Cache $cache,
        array $cancelStatuses,
        OrderRepositoryInterface $orderRepository,
        OrderConfig $config,
        LoggerInterface $logger
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->invoiceRepository = $invoiceRepository;
        $this->cache = $cache;
        $this->cancelStatuses = $cancelStatuses;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param array $commandSubject
     * @return void
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        try {
            $payment = $commandSubject['payment']->getPayment();
            $order = $payment->getOrder();

            list($rvvupOrderId, $paymentId) = $this->getRvvupData($payment);
            if ($paymentId) {
                $this->sdkProxy->voidPayment($rvvupOrderId, $paymentId, 'MERCHANT_REQUEST');
            }

            $orderState = $order->getState();
            if ($rvvupOrderId && $orderState) {
                $this->cache->clear($rvvupOrderId, $orderState);
            }

            if ($payment->getMethod() == RvvupConfigProvider::CODE) {
                if (in_array($order->getStatus(), $this->cancelStatuses)) {
                    $order->setState(Order::STATE_CANCELED);
                    $order->setStatus($this->config->getStateDefaultStatus($order->getState()));
                    $this->orderRepository->save($order);
                }
            }

            $this->disableOnlineRefunds($order);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__('Something went wrong when trying to void a Rvvup payment'));
        }
    }

    /**
     * @param Payment $payment
     * @return array
     */
    private function getRvvupData(Payment $payment)
    {
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        if ($rvvupOrderId) {
            $rvvupOrder = $this->sdkProxy->getOrder($rvvupOrderId);
        }
        $paymentId = $rvvupOrder['payments'][0]['id'] ?? null;
        return [$rvvupOrderId, $paymentId];
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    private function disableOnlineRefunds(OrderInterface $order)
    {
        foreach ($order->getInvoiceCollection()->getItems() as $invoice) {
            if ($invoice->getTransactionId()) {
                $invoice->setTransactionId(null);
                $this->invoiceRepository->save($invoice);
            }
        }
    }
}
