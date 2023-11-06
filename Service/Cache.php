<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Framework\App\CacheInterface;
use Rvvup\Payments\Gateway\Method;

class Cache
{
    /** @var CacheInterface */
    private $cache;

    /**
     * @param CacheInterface $cache
     */
    public function __construct(
        CacheInterface $cache
    ) {
        $this->cache = $cache;
    }

    /**
     * @param string $orderId
     * @param string $type
     * @param string $orderStatus
     * @return mixed
     */
    public function get(string $orderId, string $type, string $orderStatus)
    {
        $identifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_' . $type . '_' . $orderStatus;
        return $this->cache->load($identifier);
    }

    /**
     * @param string $orderId
     * @param string $type
     * @param string $value
     * @param string $orderStatus
     * @return void
     */
    public function set(string $orderId, string $type, string $value, string $orderStatus): void
    {
        $identifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_' . $type . '_' . $orderStatus;
        $this->cache->save($value, $identifier, [], strtotime('15 mins'));
    }

    public function clear(string $orderId, string $orderState): void
    {
        $refundIdentifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_refund_' . $orderState;
        $voidIdentifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_void_'. $orderState;
        $this->cache->remove($refundIdentifier);
        $this->cache->remove($voidIdentifier);
    }
}
