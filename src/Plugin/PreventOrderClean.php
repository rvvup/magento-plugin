<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Sales\Model\CronJob\CleanExpiredOrders;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Rvvup\Payments\Gateway\Method;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoresConfig;

class PreventOrderClean
{
    private const METHOD = 'method';
    private const DELETE_PENDING_AFTER = 'sales/orders/delete_pending_after';

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StoresConfig
     */
    private $storesConfig;

    /**
     * @param CollectionFactory $collectionFactory
     * @param StoresConfig $storesConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory    $collectionFactory,
        StoresConfig         $storesConfig,
        LoggerInterface      $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storesConfig = $storesConfig;
        $this->logger = $logger;
    }

    /**
     * @param CleanExpiredOrders $subject
     * @return array
     */
    public function beforeExecute(CleanExpiredOrders $subject): array
    {
        $ids = [];
        $lifetimes = $this->storesConfig->getStoresConfigByPath(self::DELETE_PENDING_AFTER);
        foreach ($lifetimes as $storeId => $lifetime) {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);
            $collection->addFieldToFilter('store_id', $storeId);

            $time = $lifetime * 60;
            $expression = new \Zend_Db_Expr(
                'TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, `updated_at`)) >= ' . $time
            );

            $collection->getSelect()->where($expression);
            $ids[] = $collection->getAllIds();
        }

        $ids = array_unique(array_merge(...$ids));

        if (empty($ids)) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->join(
            ["sop" => "sales_order_payment"],
            'main_table.entity_id = sop.parent_id',
            [self::METHOD]
        );

        $collection->addFieldToFilter(
            self::METHOD,
            ['like' => Method::PAYMENT_TITLE_PREFIX . '%']
        );

        $collection->addFieldToFilter('entity_id', ['in' => $ids]);

        $connection = $collection->getConnection();

        try {
            $connection->beginTransaction();

            $condition = ["entity_id in (?)" => $collection->getAllIds()];
            $value = ['updated_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP')];

            $connection->update('sales_order', $value, $condition);

            $connection->commit();
        } catch (\Exception $exception) {
            $this->logger->debug(
                sprintf(
                    'Exception caught while preventing order cleaning, %s',
                    $exception->getMessage()
                )
            );
            $connection->rollBack();
        }

        return [];
    }
}
