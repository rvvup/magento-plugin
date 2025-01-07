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

    public function __construct(
        ShipmentEstimationInterface            $shipmentEstimation,
        CartRepositoryInterface                $quoteRepository,
        ShippingInformationInterfaceFactory    $shippingInformationFactory,
        ShippingInformationManagementInterface $shippingInformationManagement,
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
     * @param string|null $methodId
     * @param Address $shippingAddress
     * @return Quote
     */
    public function setShippingMethodInQuote(
        Quote         $quote,
        ?string       $methodId,
        Quote\Address $shippingAddress
    ): Quote {
        $availableMethods = $this->getAvailableShippingMethods($quote);
        $isMethodAvailable = count(array_filter($availableMethods, function ($method) use ($methodId) {
                return $method->getId() === $methodId;
        })) > 0;

        $carrierCodeToMethodCode = empty($methodId) ? [] : explode('_', $methodId);

        if (!$isMethodAvailable || count($carrierCodeToMethodCode) !== 2) {
            $shippingAddress->setShippingMethod('');
        } else {
            $shippingAddress->setShippingMethod($methodId)->setCollectShippingRates(true)->collectShippingRates();

            $this->shippingInformationManagement->saveAddressInformation(
                $quote->getId(),
                $this->shippingInformationFactory->create()
                    ->setShippingAddress($shippingAddress)
                    ->setShippingCarrierCode($carrierCodeToMethodCode[0])
                    ->setShippingMethodCode($carrierCodeToMethodCode[1])
            );
        }
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
