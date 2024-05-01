<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Store\Model\App\Emulation;
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

    /** @var Emulation */
    private $emulation;

    /**
     * @param SdkProxy $sdkProxy
     * @param Cache $cache
     * @param Json $serializer
     * @param Emulation $emulation
     */
    public function __construct(
        SdkProxy $sdkProxy,
        Cache $cache,
        Json $serializer,
        Emulation $emulation
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->emulation = $emulation;
    }

    /**
     * @param array $subject
     * @param int|null $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null): bool
    {
        try {
            if ($storeId) {
                $this->emulation->startEnvironmentEmulation($storeId);
            }

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
