<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Data;

use Magento\Framework\Model\AbstractModel;
use Rvvup\Payments\Api\Data\LogInterface;
use Rvvup\Payments\Model\ResourceModel\LogResource;

class WebhookData extends AbstractModel implements LogInterface
{
    /** @var string */
    protected $_eventPrefix = 'rvvup_log';

    /**
     * Set resource
     */
    protected function _construct()
    {
        $this->_init(LogResource::class);
    }

    /**
     * Getter for Payload.
     *
     * @return string|null
     */
    public function getPayload(): ?string
    {
        return $this->getData(self::PAYLOAD);
    }

    /**
     * Setter for Payload.
     *
     * @param string|null $payload
     *
     * @return void
     */
    public function setPayload(?string $payload): void
    {
        $this->setData(self::PAYLOAD, $payload);
    }
}
