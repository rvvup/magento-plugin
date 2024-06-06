<?php

namespace Rvvup\Payments\Model;

use Magento\Framework\Model\AbstractModel;
use Rvvup\Payments\Api\Data\HashInterface;
use Rvvup\Payments\Model\ResourceModel\HashResource;

class HashModel extends AbstractModel implements HashInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_hash_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(HashResource::class);
    }

    /**
     * Getter for HashId.
     *
     * @return int|null
     */
    public function getHashId(): ?int
    {
        return $this->getData(self::HASH_ID) === null ? null
            : (int)$this->getData(self::HASH_ID);
    }

    /**
     * Setter for HashId.
     *
     * @param int|null $hashId
     *
     * @return void
     */
    public function setHashId(?int $hashId): void
    {
        $this->setData(self::HASH_ID, $hashId);
    }

    /**
     * Getter for Hash.
     *
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->getData(self::HASH);
    }

    /**
     * Setter for Hash.
     *
     * @param string|null $hash
     *
     * @return void
     */
    public function setHash(?string $hash): void
    {
        $this->setData(self::HASH, $hash);
    }

    /**
     * @inheritDoc
     */
    public function getRawData(): string
    {
        return $this->getData(self::RAW_DATA);
    }

    /**
     * @inheritDoc
     */
    public function setRawData(string $data): void
    {
        $this->setData(self::RAW_DATA, $data);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(int $id): void
    {
        $this->setData(self::CREATED_AT, $id);
    }
}
