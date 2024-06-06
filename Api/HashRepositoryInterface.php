<?php declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Sales\Api\Data\OrderPaymentSearchResultInterface;
use Rvvup\Payments\Api\Data\HashInterface;

interface HashRepositoryInterface
{
    /**
     * @param HashInterface $hash
     * @return HashInterface
     */
    public function save(HashInterface $hash): HashInterface;

    /**
     * Lists order payments that match specified search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria The search criteria.
     * @return OrderPaymentSearchResultInterface Order payment search result interface.
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * @param int $id
     * @return HashInterface
     */
    public function getById(int $id): HashInterface;

    /**
     * @param string $data
     * @return HashInterface
     */
    public function getByHash(string $data): HashInterface;

    /**
     * @param HashInterface $hash
     * @return HashInterface
     */
    public function delete(HashInterface $hash): HashInterface;
}
