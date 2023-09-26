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
     * @return mixed
     */
    public function get(string $orderId, string $type)
    {
        $identifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_' . $type;
        return $this->cache->load($identifier);
    }

    /**
     * @param string $orderId
     * @param string $type
     * @param bool $value
     * @return void
     */
    public function set(string $orderId, string $type, bool $value): void
    {
        $identifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_' . $type;
        $this->cache->save($value, $identifier, [], strtotime('15 mins'));
    }

    public function clear(string $orderId): void
    {
        $refundIdentifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_refund';
        $voidIdentifier = Method::PAYMENT_TITLE_PREFIX . $orderId . '_void';
        $this->cache->remove($refundIdentifier);
        $this->cache->remove($voidIdentifier);
    }
}
