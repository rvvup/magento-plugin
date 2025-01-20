<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Adminhtml\Items\Column;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Item;
use Rvvup\Payments\Model\PendingQty;

class Qty extends \Magento\Sales\Block\Adminhtml\Items\Column\Qty
{
    /**
     * @var mixed
     */
    private $pendingQtyService;

    /**
     * @param Context $context
     * @param StockRegistryInterface $stockRegistry
     * @param StockConfigurationInterface $stockConfiguration
     * @param Registry $registry
     * @param OptionFactory $optionFactory
     * @param PendingQty $pendingQtyService
     */
    public function __construct(
        Context $context,
        StockRegistryInterface $stockRegistry,
        StockConfigurationInterface $stockConfiguration,
        Registry $registry,
        OptionFactory $optionFactory,
        PendingQty $pendingQtyService
    ) {
        $this->pendingQtyService = $pendingQtyService;
        parent::__construct($context, $stockRegistry, $stockConfiguration, $registry, $optionFactory);
    }

    public function getQtyRvvupPendingRefund(Item $item): int
    {
        if (!$item->getRvvupPendingRefundData()) {
            return 0;
        }

        return $this->pendingQtyService->getRvvupPendingQty($item);
    }
}
