<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Rvvup\Payments\Api\HashRepositoryInterface;
use Rvvup\Payments\Api\Data\HashInterface;
use Rvvup\Payments\Api\Data\HashInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Rvvup\Payments\Model\ResourceModel\HashModel\HashCollection;
use Rvvup\Payments\Model\ResourceModel\HashModel\HashCollectionFactory;
use Rvvup\Payments\Model\ResourceModel\HashResource;

class HashRepository implements HashRepositoryInterface
{

    /** @var HashResource */
    private $resource;

    /** @var HashInterfaceFactory */
    private $hashFactory;

    /** @var CollectionProcessorInterface */
    private $collectionProcessor;

    /** @var HashCollectionFactory */
    private $hashCollectionFactory;

    /** @var SearchResultsInterfaceFactory */
    private $searchResultsFactory;

    /**
     * @param HashResource $resource
     * @param HashInterfaceFactory $hashFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param HashCollectionFactory $hashCollectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        HashResource $resource,
        HashInterfaceFactory $hashFactory,
        CollectionProcessorInterface $collectionProcessor,
        HashCollectionFactory $hashCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->hashFactory = $hashFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->hashCollectionFactory = $hashCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /** @inheirtDoc  */
    public function save(HashInterface $hash): HashInterface
    {
        $hash->setHasDataChanges(true);
        $this->resource->save($hash);
        return $hash;
    }

    /** @inheirtDoc  */
    public function getById(int $id): HashInterface
    {
        $hash = $this->hashFactory->create();
        $this->resource->load($hash, $id);
        if (!$hash->getId()) {
            throw new NoSuchEntityException(__('Unable to find hash with ID "%1"', $id));
        }
        return $hash;
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $id): HashInterface
    {
        $hash = $this->hashFactory->create();
        $this->resource->load($hash, $id, 'quote_id');
        if (!$hash->getId()) {
            throw new NoSuchEntityException(__('Unable to find hash with quote id "%1"', $id));
        }
        return $hash;
    }

    /** @inheirtDoc  */
    public function delete(HashInterface $hash): HashInterface
    {
        $this->resource->delete($hash);
        return $hash;
    }

    /** @inheirtDoc  */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var HashCollection $collection */
        $collection = $this->hashCollectionFactory->create();

        /** @var  $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $this->collectionProcessor->process($searchCriteria, $collection);

        if ($searchCriteria->getPageSize()) {
            $searchResults->setTotalCount($collection->getSize());
        } else {
            $searchResults->setTotalCount(count($collection));
        }

        $searchResults->setItems($collection->getItems());

        return $searchResults;
    }
}
