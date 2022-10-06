<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Config\Env;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Base64Json;
use Magento\Framework\Serialize\SerializerInterface;

class Indicator extends Value
{
    /** @var Base64Json  */
    private $serializer;

    /**
     * This is a "pseudo-field" simply used to indicate environment to admin user so does not need saving
     *
     * @var bool
     */
    protected $_dataSaveAllowed = false;

    private const UNKNOWN = 'UNKNOWN';
    private const LIVE = 'PRODUCTION';
    private const TEST = 'SANDBOX';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param SerializerInterface $serializer Base64Json injected via adminhtml/di.xml
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        SerializerInterface $serializer,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return Indicator
     */
    public function afterLoad()
    {
        $this->setPath('payment/rvvup/jwt');
        return parent::afterLoad();
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        $orig = parent::getOldValue();
        $parts = explode('.', $orig);
        if (!isset($parts[1])) {
            return self::UNKNOWN;
        }
        $json = $this->serializer->unserialize($parts[1]);
        if (!isset($json['live'])) {
            return self::UNKNOWN;
        }
        switch ($json['live']) {
            case true:
                return self::LIVE;
            case false:
                return self::TEST;
            default:
                return self::UNKNOWN;
        }
    }
}
