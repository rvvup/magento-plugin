<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
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
    private $config;

    /**
     * @var Payment
     */
    private $paymentResource;

    /**
     * @param SdkProxy $sdkProxy
     * @param ConfigInterface $config
     * @param Payment $paymentResource
     */
    public function __construct(
        SdkProxy $sdkProxy,
        ConfigInterface $config,
        Payment $paymentResource
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->config = $config;
        $this->paymentResource = $paymentResource;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment'];
        $method = str_replace(Method::PAYMENT_TITLE_PREFIX, '', $payment->getMethod());
        $orderId = $payment->getAdditionalInformation()['rvvup_order_id'];
        $type = 'STANDARD';

        if ($payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY)) {
            $type = 'EXPRESS';
        }
        $idempotencyKey = (string) time();

        $data = [
            'input' => [
            'orderId' => $orderId,
            'method' => $method,
            'type' => $type,
            'captureType' => 'AUTOMATIC_PLUGIN',
            'idempotencyKey' => $idempotencyKey,
            'merchantId' => $this->config->getMerchantId()
            ]
        ];

        if ($captureType = $payment->getMethodInstance()->getCaptureType()) {
            if($captureType != 'AUTOMATIC_PLUGIN' && $captureType != 'AUTOMATIC_CHECKOUT') {
                $data['input']['captureType'] = $captureType;
            }
        }

        $result = $this->sdkProxy->createPayment($data);
        $payment->setAdditionalInformation(Method::PAYMENT_ID, $result['data']['paymentCreate']['id']);
        $this->paymentResource->save($payment);
        return $result;
    }
}
