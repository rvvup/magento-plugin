<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Rvvup\Payments\Api\LogRepositoryInterface;
use Rvvup\Payments\Api\Data\LogInterface;
use Rvvup\Payments\Api\Data\LogInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Rvvup\Payments\Model\ResourceModel\LogResource;
class LoggerRepository implements LogRepositoryInterface
{
    /** @var LogResource */
    private $resource;

    /** @var LogInterfaceFactory */
    private $factory;

    /**
     * @param LogResource $resource
     * @param LogInterfaceFactory $factory
     */
    public function __construct(
        LogResource $resource,
        LogInterfaceFactory $factory
    ) {
        $this->resource = $resource;
        $this->factory = $factory;
    }

    /**
     * @param array $data
     * @return LogInterface
     */
    public function create(array $data = []): LogInterface
    {
        $log = $this->factory->create(['data'=> $data]);
        $log->setDataChanges(true);
        return $log;
    }

    /**
     * @param LogInterface $webhook
     * @return LogInterface
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(LogInterface $log): LogInterface
    {
        $this->resource->save($log);
        return $log;
    }

    /**
     * @param int $id
     * @return LogInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): LogInterface
    {
        $log = $this->factory->create();
        $this->resource->load($log, $id);
        if (!$log->getId()) {
            throw new NoSuchEntityException(__('Unable to find log with ID "%1"', $log));
        }
        return $log;
    }

    /**
     * @param LogInterface $webhook
     * @return LogInterface
     * @throws \Exception
     */
    public function delete(LogInterface $log): LogInterface
    {
        $this->resource->delete($log);
        return $log;
    }
}
