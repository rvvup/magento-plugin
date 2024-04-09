<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;

class CheckoutConfig implements ArgumentInterface
{
    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Psr\Log\LoggerInterface|Logger $logger
     * @return void
     */
    public function __construct(
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @return bool|string
     */
    public function getSerializedConfig()
    {
        $checkoutConfig = [
            'storeCode' => $this->getCurrentStoreCode()
        ];

        foreach ($checkoutConfig as $key => $value) {
            if (!isset($value)) {
                unset($checkoutConfig[$key]);
            }
        }

        return $this->serializer->serialize($checkoutConfig);
    }

    /**
     * @return string
     */
    private function getCurrentStoreCode(): string
    {
        try {
            return $this->storeManager->getStore()->getCode();
        } catch (Exception $ex) {
            // Should not happen on frontend but log error and return `default`.
            $this->logger->error('Exception thrown when fetching current store with message: ' . $ex->getMessage());

            return 'default';
        }
    }
}
