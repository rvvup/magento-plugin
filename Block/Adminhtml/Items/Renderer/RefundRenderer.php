<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Adminhtml\Items\Renderer;

use Magento\Backend\Block\Template\Context;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Block\Adminhtml\Items\Renderer\DefaultRenderer;
use Rvvup\Payments\Model\PendingQty;

class RefundRenderer extends DefaultRenderer
{
    /**
     * @var PendingQty
     */
    private PendingQty $pendingQtyService;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @param Context $context
     * @param StockRegistryInterface $stockRegistry
     * @param StockConfigurationInterface $stockConfiguration
     * @param Registry $registry
     * @param Json $serializer
     * @param PendingQty $pendingQtyService
     * @param array $data
     */
    public function __construct(
        Context $context,
        StockRegistryInterface $stockRegistry,
        StockConfigurationInterface $stockConfiguration,
        Registry $registry,
        Json $serializer,
        PendingQty $pendingQtyService,
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->pendingQtyService = $pendingQtyService;
        parent::__construct($context, $stockRegistry, $stockConfiguration, $registry, $data);
    }

    /**
     * Get rvvup qty in pending refund state
     * @param OrderItemInterface|null $item
     * @return float
     */
    public function getQtyRvvupPendingRefund(?OrderItemInterface $item = null): float
    {
        $orderItem = $item ?: $this->getItem()->getOrderItem();

        if (!$orderItem->getRvvupPendingRefundData()) {
            return 0;
        }

        if (!$this->getCreditmemo()) {
            return $this->pendingQtyService->getRvvupPendingQty($orderItem);
        }

        $id = $this->getCreditmemo()->getId();
        $data = $this->unserialize($orderItem->getRvvupPendingRefundData());
        if (isset($data[$id])) {
            return (float)$data[$id]['qty'];
        }

        return 0;
    }

    /**
     * @param DataObject $item
     * @return float
     */
    public function getCreditMemoAvailableQty(DataObject $item): int
    {
        $orderItem = $item->getOrderItem();

        $value = $this->getQtyRvvupPendingRefund($orderItem) ?: $this->pendingQtyService->getRvvupPendingQty(
            $orderItem
        );

        $qty = (int)$orderItem->getQtyInvoiced() - $value;
        if ($item->getQty() < $qty) {
            return (int)$item->getQty();
        }
        return $qty;
    }

    /**
     * @param string $data
     * @return array
     */
    protected function unserialize(string $data): array
    {
        return $this->serializer->unserialize($data);
    }
}
