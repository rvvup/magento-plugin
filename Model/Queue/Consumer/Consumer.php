<?php

namespace Rvvup\Payments\Model\Queue\Consumer;

use Closure;
use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Communication\ConfigInterface as CommunicationConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\MessageQueue\CallbackInvokerInterface;
use Magento\Framework\MessageQueue\ConnectionLostException;
use Magento\Framework\MessageQueue\Consumer\ConfigInterface as ConsumerConfig;
use Magento\Framework\MessageQueue\ConsumerConfigurationInterface;
use Magento\Framework\MessageQueue\EnvelopeFactory;
use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\LockInterface;
use Magento\Framework\MessageQueue\MessageController;
use Magento\Framework\MessageQueue\MessageEncoder;
use Magento\Framework\MessageQueue\MessageLockException;
use Magento\Framework\MessageQueue\MessageValidator;
use Magento\Framework\MessageQueue\QueueInterface;
use Magento\Framework\MessageQueue\QueueRepository;
use Magento\Framework\Phrase;
use Magento\MysqlMq\Model\QueueManagement;
use Psr\Log\LoggerInterface;

class Consumer extends \Magento\Framework\MessageQueue\Consumer
{
    /** @var CallbackInvokerInterface */
    private $invoker;

    /** @var MessageEncoder */
    private $messageEncoder;

    /** @var ResourceConnection */
    private $resource;

    /** @var ConsumerConfigurationInterface */
    private $configuration;

    /** @var LoggerInterface|null */
    private $logger;

    /** @var MessageController */
    private $messageController;

    /** @var CommunicationConfig */
    private $communitcationConfig;

    /** @var EnvelopeFactory */
    private $envelopeFactory;

    /** @var QueueRepository */
    private $queueRepository;

    /** @var MessageValidator */
    private $messageValidator;

    /** @var ConsumerConfig */
    private $consumerConfig;

    /**
     * @param CallbackInvokerInterface $invoker
     * @param MessageEncoder $messageEncoder
     * @param ResourceConnection $resource
     * @param ConsumerConfigurationInterface $configuration
     * @param CommunicationConfig $communitcationConfig
     * @param MessageController $messageController
     * @param EnvelopeFactory $envelopeFactory
     * @param MessageValidator $messageValidator
     * @param ConsumerConfig $consumerConfig
     * @param QueueRepository $queueRepository
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        CallbackInvokerInterface $invoker,
        MessageEncoder $messageEncoder,
        ResourceConnection $resource,
        ConsumerConfigurationInterface $configuration,
        CommunicationConfig $communitcationConfig,
        MessageController $messageController,
        EnvelopeFactory $envelopeFactory,
        MessageValidator $messageValidator,
        ConsumerConfig $consumerConfig,
        QueueRepository $queueRepository,
        LoggerInterface $logger = null
    ) {
        $this->invoker = $invoker;
        $this->messageEncoder = $messageEncoder;
        $this->resource = $resource;
        $this->configuration = $configuration;
        $this->communitcationConfig = $communitcationConfig;
        $this->messageController = $messageController;
        $this->envelopeFactory = $envelopeFactory;
        $this->messageValidator = $messageValidator;
        $this->consumerConfig = $consumerConfig;
        $this->queueRepository = $queueRepository;
        $this->logger = $logger;
        parent::__construct($invoker, $messageEncoder, $resource, $configuration, $logger);
    }

    public function process($maxNumberOfMessages = null)
    {
        $queue = $this->configuration->getQueue();
        $maxIdleTime = $this->configuration->getMaxIdleTime();
        $sleep = $this->configuration->getSleep();
        if (!isset($maxNumberOfMessages)) {
            $queue->subscribe($this->getTransactionCallback($queue));
        } else {
            $this->invoker->invoke($queue, $maxNumberOfMessages, $this->getTransactionCallback($queue), $maxIdleTime, $sleep);
        }
    }

    /**
     * Added queue restart for orders not older than 10 mins
     *
     * @param QueueInterface $queue
     * @return Closure
     */
    private function getTransactionCallback(QueueInterface $queue)
    {
        return function (EnvelopeInterface $message) use ($queue) {
            /** @var LockInterface $lock */
            $lock = null;
            try {
                $topicName = $message->getProperties()['topic_name'];
                $topicConfig = $this->communitcationConfig->getTopic($topicName);
                $lock = $this->messageController->lock($message, $this->configuration->getConsumerName());

                if ($topicConfig[CommunicationConfig::TOPIC_IS_SYNCHRONOUS]) {
                    $responseBody = $this->dispatchMessage($message, true);
                    $responseMessage = $this->envelopeFactory->create(['body' => $responseBody, 'properties' => $message->getProperties()]);
                    $this->sendResponse($responseMessage);
                } else {
                    $allowedTopics = $this->configuration->getTopicNames();
                    if (in_array($topicName, $allowedTopics)) {
                        $this->dispatchMessage($message);
                    } else {
                        $queue->reject($message);
                        return;
                    }
                }
                $queue->acknowledge($message);
            } catch (MessageLockException $exception) {
                $queue->acknowledge($message);
            } catch (ConnectionLostException $e) {
                /** Adding queue restart for orders which are not older 10 mins */
                $updateAt = strtotime($message->getProperties()['updated_at']);
                $queue->reject($message);
                if (time() - $updateAt > 600) {
                    if ($lock) {
                        $this->resource->getConnection()->delete($this->resource->getTableName('queue_lock'), ['id = ?' => $lock->getId()]);
                    }
                }
            } catch (NotFoundException $e) {
                $queue->acknowledge($message);
                $this->logger->warning($e->getMessage());
            } catch (Exception $e) {
                $queue->reject($message, false, $e->getMessage());
                if ($lock) {
                    $this->resource->getConnection()->delete($this->resource->getTableName('queue_lock'), ['id = ?' => $lock->getId()]);
                }
            }
        };
    }

    /** @inheirtDoc */
    private function dispatchMessage(EnvelopeInterface $message, $isSync = false)
    {
        $properties = $message->getProperties();
        $topicName = $properties['topic_name'];
        $handlers = $this->configuration->getHandlers($topicName);
        $decodedMessage = $this->messageEncoder->decode($topicName, $message->getBody());

        if (isset($decodedMessage)) {
            $messageSchemaType = $this->configuration->getMessageSchemaType($topicName);
            if ($messageSchemaType == CommunicationConfig::TOPIC_REQUEST_TYPE_METHOD) {
                foreach ($handlers as $callback) {
                    $result = call_user_func_array($callback, $decodedMessage);
                    return $this->processSyncResponse($topicName, $result);
                }
            } else {
                foreach ($handlers as $callback) {
                    $result = call_user_func($callback, $decodedMessage);
                    if ($isSync) {
                        return $this->processSyncResponse($topicName, $result);
                    }
                }
            }
        }
        return null;
    }

    /** @inheirtDoc */
    private function processSyncResponse($topicName, $result)
    {
        if (isset($result)) {
            $this->messageValidator->validate($topicName, $result, false);
            return $this->messageEncoder->encode($topicName, $result, false);
        } else {
            throw new LocalizedException(new Phrase('No reply message resulted in RPC.'));
        }
    }

    /** @inheirtDoc */
    private function sendResponse(EnvelopeInterface $envelope)
    {
        $messageProperties = $envelope->getProperties();
        $connectionName = $this->consumerConfig->getConsumer($this->configuration->getConsumerName())->getConnection();
        $queue = $this->queueRepository->get($connectionName, $messageProperties['reply_to']);
        $queue->push($envelope);
    }
}
