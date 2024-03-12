<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Serialize\Serializer\Json;
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

    /** @var Json */
    private $serializer;

    /**
     * @param SdkProxy $sdkProxy
     * @param Cache $cache
     * @param Json $serializer
     */
    public function __construct(
        SdkProxy $sdkProxy,
        Cache $cache,
        Json $serializer
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * @param array $subject
     * @param int|null $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null): bool
    {
        try {
            $payment = $subject['payment']->getPayment();
            $orderId = $payment->getAdditionalInformation(Method::ORDER_ID);

            if (!$orderId) {
                return false;
            }

            $value = $this->cache->get($orderId, 'void', $payment->getOrder()->getState());
            if ($value) {
                return $this->serializer->unserialize($value)['available'];
            }
            $value = $this->sdkProxy->isOrderVoidable($orderId);
            $data = $this->serializer->serialize(['available' => $value]);
            $this->cache->set($orderId, 'void', $data, $payment->getOrder()->getState());

            return $value;
        } catch (Exception $e) {
            return false;
        }
    }
}
