<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\App\ScopeInterface;
use Monolog\Logger as BaseLogger;
use Rvvup\Payments\Api\Data\LogInterface;
use Rvvup\Payments\Model\LogModelFactory;
use Rvvup\Payments\Model\ResourceModel\LogResource;

class Logger extends BaseLogger
{
    private LogModelFactory $modelFactory;
    private LogResource $resource;

    /**
     * {@inheritDoc}
     * @see \Monolog\Logger::__construct()
     */
    public function __construct(
        $name,
        array $handlers = [],
        array $processors = [],
        LogModelFactory $modelFactory,
        LogResource     $resource
        ) {
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
        parent::__construct($name,$handlers,$processors);
    }


    /**
     * @param $message
     * @param array $context
     * @return bool
     */
    public function addError($message, array $context = array())
    {
        $result = $this->addRecord(static::ERROR, $message, $context);

        try {

            $payload = json_encode([
                'message' => $message,
                'time' => time(),
                'metadata' => $context
            ]);

            $data = [
                'payload' => $payload
            ];

            /** @var LogModel $model */
            $model = $this->modelFactory->create();
            $model->addData($data);
            $model->setHasDataChanges(true);

            if (!$model->getData(LogInterface::LOG_ID)) {
                $model->isObjectNew(true);
            }
            $this->resource->save($model);
            //$baseUrl = $this->config->getEndpoint(ScopeInterface::SCOPE_STORE, $storeId);
            //$baseUrl = str_replace('graphql', 'plugin/log', $baseUrl);
            
            //$request = $this->curl->request(Request::METHOD_POST, $baseUrl, $params);


            // add to cron
        } catch (\Exception $e) {
            $this->addRecord(static::ERROR, $e->getMessage());
        }

        return $result;
    }

}
