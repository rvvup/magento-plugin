<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Cache;

class Cancel implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @var Logger
     */
    private $logger;

    /** @var Cache */
    private $cache;

    /**
     * @param SdkProxy $sdkProxy
     * @param Cache $cache
     * @param Logger $logger
     */
    public function __construct(
        SdkProxy        $sdkProxy,
        Cache           $cache,
        Logger $logger
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->cache = $cache;
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
            $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
            if (!$rvvupOrderId) {
                return;
            }
            $rvvupOrder = $this->sdkProxy->getOrder($rvvupOrderId);
            $paymentId = $rvvupOrder['payments'][0]['id'] ?? null;
            if (!$paymentId) {
                return;
            }
            switch ($rvvupOrder['payments'][0]['status']) {
                case 'AUTHORIZED':
                    $this->sdkProxy->voidPayment($rvvupOrderId, $paymentId);
                    break;
                default:
                    // Cancel payment, but if it's not cancellable the API will not
                    // throw an error and just return current state
                    $paymentAfterCancellation = $this->sdkProxy->cancelPayment($paymentId, $rvvupOrderId);
                    if ($paymentAfterCancellation['status'] !== 'CANCELLED' &&
                        $paymentAfterCancellation['status'] !== 'VOIDED' &&
                        $paymentAfterCancellation['status'] !== 'FAILED' &&
                        $paymentAfterCancellation['status'] !== 'DECLINED' &&
                        $paymentAfterCancellation['status'] !== 'EXPIRED' &&
                        $paymentAfterCancellation['status'] !== 'AUTHORIZATION_EXPIRED') {
                        throw new LocalizedException(
                            __(
                                'Payment could not be cancelled as it is in state: %1 in rvvup.',
                                $paymentAfterCancellation['status']
                            )
                        );
                    }
            }

            $orderState = $order->getState();
            if ($orderState) {
                $this->cache->clear($rvvupOrderId, $orderState);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
