<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Api\Model\ShipmentTrackingCreateInput;
use Rvvup\ApiException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\ApiProvider;

class AddTrackingInfo implements ObserverInterface
{
    private $track;

    private $shipment;

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
        $this->track = $observer->getEvent()->getTrack();
        $this->shipment = $this->track->getShipment();
        $order = $this->shipment->getOrder();
        $payment = $order->getPayment();

        try {
            $this->apiProvider
                ->getSdk($order->getStoreId())
                ->shipmentTrackings()
                ->create(
                    paymentSessionId: $payment->getAdditionalInformation(Method::ORDER_ID).'s',
                    input: $this->getShipmentTrackingCreateInput()
                );
        } catch (ApiException $e) {
            $this->logger->error('Rvvup AddTrackingInfo API error: ' . $e->getMessage());
        }
    }

    private function getShipmentTrackingCreateInput(): ShipmentTrackingCreateInput
    {
        $items = [];
        foreach ($this->shipment->getAllItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'quantity' => (int) $item->getQty(),
                'sku' => $item->getSku(),
            ];
        }

        return new ShipmentTrackingCreateInput([
            'items' => $items,
            'tracking_detail' => [[
                'carrierCode' => $this->track->getCarrierCode(),
                'title' => $this->track->getTitle(),
                'trackingNumber' => $this->track->getTrackNumber(),
            ]],
        ]);
    }
}
