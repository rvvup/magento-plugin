<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Laminas\Http\Request;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Model\ResourceModel\LogModel\LogCollection;
use Rvvup\Payments\Model\ResourceModel\LogModel\LogCollectionFactory;
use Rvvup\Payments\Sdk\Curl;
use Rvvup\Payments\Model\ResourceModel\LogResource;

class Log
{
    /** @var LogCollectionFactory */
    private $logCollectionFactory;

    /** @var Json */
    private $json;

    /** @var PublisherInterface */
    private $publisher;

    /** @var Curl */
    private $curl;

    /** @var Config */
    private $config;

    /**
     * @param LogCollectionFactory $logCollectionFactory
     * @param Json $json
     * @param Curl $curl
     * @param Config $config
     * @param LogResource $resource
     */
    public function __construct(
        LogCollectionFactory $logCollectionFactory,
        Json                 $json,
        Curl                 $curl,
        Config               $config,
        LogResource          $resource
    ) {
        $this->logCollectionFactory = $logCollectionFactory;
        $this->json = $json;
        $this->curl = $curl;
        $this->config = $config;
    }

    /**
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    public function execute(): void
    {
        /** @var LogCollection $collection */
        $collection = $this->logCollectionFactory->create();
        $collection->addFieldToSelect('*')
            ->addFieldToFilter('is_processed', ['eq' => 'false']);
        /** Limit to 50 items per run */
        $collection->clear()->getSelect()->limit(50);

        $this->processLogs($collection);
    }

    /**
     * @param LogCollection $collection
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    private function processLogs(LogCollection $collection): void
    {
        $batch = [];
        foreach ($collection->getItems() as $item) {
            $payload = $item->getData('payload');
            $data = $this->json->unserialize($payload);
            $storeId = $data['metadata']['storeId'];

            if (!isset($batch[$storeId])) {
                $batch[$storeId] = [];
            }

            $batch[$storeId][] = $data;
            $item->setData('is_processed', true);
            $item->save();
        }
        foreach ($batch as $key => $item) {
            $this->notifyRvvup((string) $key, $item);
        }

    }

    /**
     * @param string $storeId
     * @param array $data
     * @return void
     * @throws NoSuchEntityException
     */
    private function notifyRvvup(string $storeId, array $data) {
            $token = $this->config->getJwtConfig(ScopeInterface::SCOPE_STORE, $storeId);
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ];
            $baseUrl = $this->config->getEndpoint(ScopeInterface::SCOPE_STORE, $storeId);
            $url = str_replace('graphql', 'plugin/log', $baseUrl);
            $postData = ['headers' => $headers, 'json' => $data];
            $this->curl->request(Request::METHOD_POST, $url, $postData);
    }
}
