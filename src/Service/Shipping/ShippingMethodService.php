<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service\Shipping;

use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Rvvup\Payments\Model\Shipping\ShippingMethod;

class ShippingMethodService
{

    /** @var ShipmentEstimationInterface */
    private $shipmentEstimation;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var ShippingInformationInterfaceFactory */
    private $shippingInformationFactory;

    /** @var ShippingInformationManagementInterface */
    private $shippingInformationManagement;

    /**
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param CartRepositoryInterface $quoteRepository
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     */
    public function __construct(
        ShipmentEstimationInterface            $shipmentEstimation,
        CartRepositoryInterface                $quoteRepository,
        ShippingInformationInterfaceFactory    $shippingInformationFactory,
        ShippingInformationManagementInterface $shippingInformationManagement
    ) {
        $this->shipmentEstimation = $shipmentEstimation;
        $this->quoteRepository = $quoteRepository;
        $this->shippingInformationFactory = $shippingInformationFactory;
        $this->shippingInformationManagement = $shippingInformationManagement;
    }

    /**
     * @param Quote $quote
     * @param string|null $methodId
     * @return Quote
     * @throws InputException
     */
    public function updateShippingMethod(
        Quote   $quote,
        ?string $methodId
    ): Quote {
        $shippingAddress = $quote->getShippingAddress();
        $quote = $this->setShippingMethodInQuote($quote, $methodId, $shippingAddress);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $this->quoteRepository->save($quote);

        return $quote;
    }

    /**
     * @param Quote $quote
     * @param Address $shippingAddress
     * @param ShippingMethod[]|null $availableShippingMethods
     * @return Quote
     */
    public function setFirstShippingMethodInQuote(
        Quote         $quote,
        Quote\Address $shippingAddress,
        ?array        $availableShippingMethods = null
    ): Quote {
        return $this->setShippingMethodInQuote($quote, null, $shippingAddress, $availableShippingMethods);
    }

    /**
     * @param Quote $quote
     * @param string|null $methodId
     * @param Address $shippingAddress
     * @param ShippingMethod[]|null $availableShippingMethods
     * @return Quote
     */
    public function setShippingMethodInQuote(
        Quote         $quote,
        ?string       $methodId,
        Quote\Address $shippingAddress,
        ?array        $availableShippingMethods = null
    ): Quote {

        if (empty($methodId)) {
            if ($availableShippingMethods == null) {
                $availableShippingMethods = $this->getAvailableShippingMethods($quote);
            }
            if (empty($availableShippingMethods)) {
                $shippingAddress->setShippingMethod('');
                return $quote;
            }
            $methodId = $availableShippingMethods[0]->getId();
        }
        if (empty($methodId)) {
            $shippingAddress->setShippingMethod('');
            return $quote;
        }

        $shippingAddress->setShippingMethod($methodId)->setCollectShippingRates(true)->collectShippingRates();
        $rate = $shippingAddress->getShippingRateByCode($methodId);
        if ($rate === false) {
            $shippingAddress->setShippingMethod('');
            return $quote;
        }

        $this->shippingInformationManagement->saveAddressInformation(
            $quote->getId(),
            $this->shippingInformationFactory->create()
                ->setShippingAddress($shippingAddress)
                ->setShippingCarrierCode($rate->getCarrier())
                ->setShippingMethodCode($rate->getMethod())
        );

        return $quote;
    }

    /**
     * @param Quote $quote
     * @return ShippingMethod[] $shippingMethods
     */
    public function getAvailableShippingMethods(Quote $quote): array
    {
        $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress(
            $quote->getId(),
            $quote->getShippingAddress()
        );
        if (empty($shippingMethods)) {
            return [];
        }
        $returnedShippingMethods = [];
        foreach ($shippingMethods as $shippingMethod) {
            if ($shippingMethod->getErrorMessage()) {
                continue;
            }

            $returnedShippingMethods[] = new ShippingMethod($shippingMethod, $quote->getQuoteCurrencyCode());
        }
        return $returnedShippingMethods;
    }
}
