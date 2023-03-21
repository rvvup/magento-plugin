<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;

class CreatePayment implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @param SdkProxy $sdkProxy
     * @param ConfigInterface $config
     */
    public function __construct(
        SdkProxy $sdkProxy,
        ConfigInterface $config
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->config = $config;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment'];
        $method = str_replace(Method::PAYMENT_TITLE_PREFIX, '', $payment->getMethod());
        $orderId = $payment->getAdditionalInformation()['rvvup_order_id'];
        $type = 'STANDARD';

        if ($payment->getAdditionalInformation('is_rvvup_express_payment')) {
            $type = 'EXPRESS';
        }
        $idempotencyKey = (string) time();

        $data = [
            'input' => [
            'orderId' => $orderId,
            'method' => $method,
            'type' => $type,
            'idempotencyKey' => $idempotencyKey,
            'merchantId' => $this->config->getMerchantId()
            ]
        ];

        return $this->sdkProxy->createPayment(
            $data
        );
    }
}
