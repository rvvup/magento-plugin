<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Rvvup\Payments\Model\SdkProxy;

class CanRefund implements ValueHandlerInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        SdkProxy $sdkProxy
    ) {
        $this->sdkProxy = $sdkProxy;
    }

    public function handle(array $subject, $storeId = null)
    {
        try {
            $payment = $subject['payment']->getPayment();
            return $this->sdkProxy->isOrderRefundable($payment->getAdditionalInformation('rvvup_order_id'));
        } catch (\Exception $e) {
            return false;
        }
    }
}
