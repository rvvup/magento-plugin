<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Api\CartRepositoryInterface;
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
use Rvvup\Payments\Service\PaymentLink;

class Cancel implements CommandInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var PaymentLink */
    private $paymentLinkService;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /**
     * @param PaymentLink $paymentLinkService
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        PaymentLink $paymentLinkService,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->paymentLinkService = $paymentLinkService;
        $this->cartRepository = $cartRepository;
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
            $orderPayment = $commandSubject['payment']->getPayment();
            $quoteId = $orderPayment->getOrder()->getQuoteId();
            $quote = $this->cartRepository->get($quoteId);
            $payment = $quote->getPayment();

            $paymentLinkId = $payment->getAdditionalInformation(Method::PAYMENT_LINK_ID);
            $storeId = (string) $commandSubject['payment']->getOrder()->getStoreId();
            $this->paymentLinkService->cancelPaymentLink($storeId, $paymentLinkId);
            $message = __('Canceled Rvvup Payment Link online');
            $orderPayment->getOrder()->addCommentToStatusHistory($message, false, false);

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__('Something went wrong when trying to cancel a Rvvup payment'));
        }
    }
}
