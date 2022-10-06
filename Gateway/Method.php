<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway;

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Method extends Adapter
{
    /** @var string */
    private $title;
    /** @var array */
    private $limits;
    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    private const LOCAL_CONFIG_LOGIC_FIELDS = [
        'min_order_total' => 'getMinOrderTotal',
        'max_order_total' => 'getMaxOrderTotal',
    ];

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $title
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface|RvvupLog $logger // Set via di.xml
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param array $limits
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $code,
        string $title,
        string $formBlockType,
        string $infoBlockType,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        array $limits = []
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
            $logger
        );

        $this->title = $title;
        $this->limits = $limits;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function getConfigData($field, $storeId = null)
    {
        if (!array_key_exists($field, self::LOCAL_CONFIG_LOGIC_FIELDS)) {
            return parent::getConfigData($field, $storeId);
        }

        $method = self::LOCAL_CONFIG_LOGIC_FIELDS[$field];

        try {
            $currency = $this->storeManager->getStore($storeId)->getCurrentCurrency()->getCode();

            return $this->$method($currency);
        } catch (Throwable $t) {
            // Log error & return default on Throwable.
            $this->logger->error(
                'Error thrown when getting store currency with message: ' . $t->getMessage(),
                [
                    'field' => $field,
                    'store_id' => $storeId,
                    'method' => $method,
                ]
            );

            return parent::getConfigData($field, $storeId);
        }
    }

    /**
     * @param string $currencyCode
     * @return string|null
     */
    public function getMinOrderTotal(string $currencyCode): ?string
    {
        return $this->limits[$currencyCode]['min'] ?? null;
    }

    /**
     * @param string $currencyCode
     * @return string|null
     */
    public function getMaxOrderTotal(string $currencyCode): ?string
    {
        return $this->limits[$currencyCode]['max'] ?? null;
    }
}
