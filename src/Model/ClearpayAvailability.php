<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\Api\ClearpayAvailabilityInterface;

class ClearpayAvailability implements ClearpayAvailabilityInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Rvvup\Payments\Model\IsPaymentMethodAvailableInterface
     */
    private $isPaymentMethodAvailable;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Rvvup\Payments\Model\IsPaymentMethodAvailableInterface $isPaymentMethodAvailable
     * @return void
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        IsPaymentMethodAvailableInterface $isPaymentMethodAvailable
    ) {
        $this->storeManager = $storeManager;
        $this->isPaymentMethodAvailable = $isPaymentMethodAvailable;
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        $currentStoreCurrencyCode = $this->getCurrentStoreCurrencyCode();

        if ($currentStoreCurrencyCode === null) {
            return false;
        }

        return $this->isPaymentMethodAvailable->execute('CLEARPAY', '0', $currentStoreCurrencyCode);
    }

    /**
     * @return string|null
     */
    private function getCurrentStoreCurrencyCode(): ?string
    {
        try {
            $currency = $this->storeManager->getStore()->getCurrentCurrency();

            return $currency === null ? null : $currency->getCode();
        } catch (LocalizedException|NoSuchEntityException $ex) {
            // Silent return null
            return null;
        }
    }
}
