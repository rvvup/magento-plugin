<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\App\ScopeInterface;
use Monolog\Logger as BaseLogger;
use Rvvup\Payments\Model\LoggerRepository;

class Logger extends BaseLogger
{

    /**
     * {@inheritDoc}
     * @see \Monolog\Logger::__construct()
     */
    public function __construct(
        $name,
        array $handlers = [],
        array $processors = [],
        LoggerRepository $loggerRepository
        ) {
        $this->loggerRepository = $loggerRepository;
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

       // try {

            $payload = json_encode([
                'message' => $message,
                'time' => time(),
                'metadata' => $context
            ]);

            $data = [
                'payload' => $payload
            ];

            $this->loggerRepository->create($data);
        //$baseUrl = $this->config->getEndpoint(ScopeInterface::SCOPE_STORE, $storeId);
        //$baseUrl = str_replace('graphql', 'plugin/log', $baseUrl);

        //$request = $this->curl->request(Request::METHOD_POST, $baseUrl, $params);


            // add to cron
//         } catch (\Exception $e) {
//             $this->addRecord(static::ERROR, $e->getMessage());
//         }

        return $result;
    }

}
