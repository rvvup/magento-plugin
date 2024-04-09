<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Monolog\Logger as BaseLogger;
use Rvvup\Payments\Model\LogModelFactory;
use Rvvup\Payments\Model\ResourceModel\LogResource;

class Logger extends BaseLogger
{
    /** @var LogModelFactory */
    private $modelFactory;

    /** @var LogResource */
    private $resource;

    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * @param string $name
     * @param LogModelFactory $modelFactory
     * @param LogResource $resource
     * @param StoreManagerInterface $storeManager
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        string $name,
        LogModelFactory $modelFactory,
        LogResource     $resource,
        StoreManagerInterface $storeManager,
        array $handlers = [],
        array $processors = []
    ) {
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        parent::__construct($name, $handlers, $processors);
    }


    public function addRvvupError(
        string $message,
        ?string $cause = null,
        ?string $rvvupOrderId = null,
        ?string $rvvupPaymentId = null,
        ?string $magentoOrderId = null
    ) {
        $result = $this->addRecord(static::ERROR, $message, $context);

        try {
            $data = $this->prepareData($message, $cause, $rvvupOrderId, $rvvupPaymentId, $magentoOrderId);

            /** @var LogModel $model */
            $model = $this->modelFactory->create();
            $model->addData($data);
            $model->setHasDataChanges(true);
            $this->resource->save($model);
        } catch (\Exception $e) {
            $this->addRecord(static::ERROR, $e->getMessage());
        }

        return $result;
    }

    /**
     * @param string $message
     * @param array $context
     * @return array
     * @throws NoSuchEntityException
     */
    private function prepareData(
        string $message,
        ?string $cause = null,
        ?string $rvvupOrderId = null,
        ?string $rvvupPaymentId = null,
        ?string $magentoOrderId = null
    ): array {

        $payload = json_encode([
            'message' => $message,
            'timestamp' => time(),
            'metadata' => [
                'rvvupPaymentId' => $rvvupPaymentId,
                'rvvupOrderId' => $rvvupOrderId,
                'magento' => [
                    'storeId' => $this->storeManager->getStore()->getId(),
                    'order_id' => $magentoOrderId
                ]
            ],
            'cause' => $cause
        ]);

        return [
            'payload' => $payload
        ];
    }
}
