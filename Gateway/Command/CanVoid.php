<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;

class CanVoid implements ValueHandlerInterface
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
            return $this->sdkProxy->isOrderVoidable($payment->getAdditionalInformation(Method::ORDER_ID));
        } catch (Exception $e) {
            return false;
        }
    }
}
