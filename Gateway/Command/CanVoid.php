<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Cache;

class CanVoid implements ValueHandlerInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /** @var Cache */
    private $cache;

    /**
     * @param SdkProxy $sdkProxy
     * @param Cache $cache
     */
    public function __construct(
        SdkProxy $sdkProxy,
        Cache $cache
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->cache = $cache;
    }

    /**
     * @param array $subject
     * @param $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null): bool
    {
        try {
            $payment = $subject['payment']->getPayment();
            $orderId = $payment->getAdditionalInformation(Method::ORDER_ID);
            if ($value = $this->cache->get($orderId, 'void')) {
                return (bool) $value;
            }
            $value = $this->sdkProxy->isOrderVoidable($orderId);
            $this->cache->set($orderId, 'void', $value);

            return $value;
        } catch (Exception $e) {
            return false;
        }
    }
}
