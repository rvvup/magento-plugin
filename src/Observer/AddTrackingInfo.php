<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Api\Model\ShipmentTrackingCreateInput;
use Rvvup\Api\Model\ShipmentTrackingDetailInput;
use Rvvup\Api\Model\ShipmentTrackingItemInput;
use Rvvup\ApiException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Service\ApiProvider;

class AddTrackingInfo implements ObserverInterface
{
    /** @var LoggerInterface|Logger $logger */
    private $logger;
    /** @var ApiProvider */
    private $apiProvider;

    /**
     * @param LoggerInterface|Logger $logger
     * @param ApiProvider $apiProvider
     */
    public function __construct(
        private LoggerInterface $logger,
        private ApiProvider $apiProvider,
    ) {}

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $track = $observer->getEvent()->getTrack();
        $order = $track->getShipment()->getOrder();
        $payment = $order->getPayment();

        if (strpos($payment->getMethod(), RvvupConfigProvider::CODE) !== 0) {
            return; // Silent return if the payment method is not Rvvup - no need to throw exceptions
        }
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        if (!$rvvupOrderId) {
            $this->logger->error('Rvvup AddTrackingInfo: No Rvvup Order ID found in payment additional information.');
            return;
        }

        try {
            $this->apiProvider
                ->getSdk($order->getStoreId())
                ->shipmentTrackings()
                ->create(
                    $rvvupOrderId,
                    $this->getShipmentTrackingCreateInput($observer->getEvent()->getTrack())
                );
        } catch (ApiException $e) {
            $this->logger->error('Rvvup AddTrackingInfo API error: ' . $e->getMessage());
        }
    }

    private function getShipmentTrackingCreateInput($track): ShipmentTrackingCreateInput
    {
        $items = [];
        foreach ($track->getShipment()->getAllItems() as $item) {
            $items[] = (new ShipmentTrackingItemInput())
                ->setName($item->getName())
                ->setQuantity($item->getQty())
                ->setSku($item->getSku());
        }

        return (new ShipmentTrackingCreateInput())
            ->setItems($items)
            ->setTrackingDetail([
                (new ShipmentTrackingDetailInput())
                    ->setCarrierCode($track->getCarrierCode())
                    ->setTitle($track->getTitle())
                    ->setTrackingNumber($track->getTrackNumber()),
            ]);
    }
}
