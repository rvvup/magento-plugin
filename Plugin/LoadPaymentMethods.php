<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Traits\LoadMethods;

class LoadPaymentMethods
{
    use LoadMethods;

    /**
     * @var array
     */
    private array $methods;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var SdkProxy
     */
    private $sdkProxy;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param SessionManagerInterface $checkoutSession
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        SessionManagerInterface $checkoutSession,
        ConfigInterface $config,
        StoreManagerInterface $storeManager,
        SdkProxy $sdkProxy
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->storeManager = $storeManager;
        $this->methods = [];
    }

    /**
     * @param Data $subject
     * @param array $result
     * @return array
     */
    public function afterGetPaymentMethods(Data $subject, array $result): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (LocalizedException $ex) {
            // Silently handle exception.
            $quote = null;
        }

        if (isset($result['rvvup'])) {
            $items = $quote === null || $quote->getItems() === null ? [] : $quote->getItems();

            if (!$this->config->isActive()) {
                return $result;
            }

            $productTypes = $this->config->getValidProductTypes();

            foreach ($items as $item) {
                if (!in_array($item->getProductType(), $productTypes, true)) {
                    return $result;
                }
            }

            $this->template = $result['rvvup'];
            unset($result['rvvup']);
        }

        if (!$this->methods) {
            $currency = $this->storeManager->getStore()->getBaseCurrencyCode();

            if ($quote !== null && $quote->getQuoteCurrencyCode() !== null) {
                $currency = $quote->getQuoteCurrencyCode();
            }

            $this->methods = $this->sdkProxy->getMethods(
                $quote === null ? '0' : (string) $quote->getGrandTotal(),
                $currency
            );
        }

        return array_merge(
            $result,
            $this->processMethods($this->methods)
        );
    }
}
