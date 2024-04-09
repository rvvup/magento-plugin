<?php 
declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Rvvup\Payments\Api\Data\LogInterface;

interface LogRepositoryInterface
{
    /**
     * @param array $data
     * @return LogInterface
     */
    public function create(array $data = []): LogInterface;
    
    /**
     * @param LogInterface $webhook
     * @return LogInterface
     */
    public function save(LogInterface $log): LogInterface;
    
    /**
     * @param int $id
     * @return LogInterface
     */
    public function getById(int $id): LogInterface;
    
    /**
     * @param LogInterface $webhook
     * @return LogInterface
     */
    public function delete(LogInterface $log): LogInterface;
}
