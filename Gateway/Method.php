<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway;

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Block\Form;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Method extends Adapter
{
    /**
     * Rvvup payment methods prefix constant.
     */
    public const PAYMENT_TITLE_PREFIX = 'rvvup_';

    /**
     * Constant to be used as a key identifier for Rvvup payments.
     */
    public const ORDER_ID = 'rvvup_order_id';

    public const DASHBOARD_URL = 'dashboard_url';

    public const TRANSACTION_ID = 'transaction_id';

    public const PAYMENT_ID = 'rvvup_payment_id';

    public const CREATE_NEW = 'should_create_new_rvvup_order';

    public const EXPRESS_PAYMENT_KEY = 'is_rvvup_express_payment';
    public const EXPRESS_PAYMENT_DATA_KEY = 'rvvup_express_payment_data';

    /**
     * Curative list of available RVVUP Status constants.
     *
     * CANCELLED: Customer aborted the payment.
     * DECLINED: Customer's issuing bank rejected the payment.
     * EXPIRED: Customer's pending payment time to complete has expired.
     * PENDING: Customer has clicked place order but payment is not fully completed, e.g.
     *  - Customer closed the window halfway through the payment process
     *  - Customer paid with Pay By Bank which remains Pending until fully settled.
     * REQUIRES_ACTION: This needs to do something to finalise the transaction
     * SUCCEEDED: Payment was completed successfully.
     */
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_REQUIRES_ACTION = 'REQUIRES_ACTION';
    public const STATUS_AUTHORIZED = "AUTHORIZED";
    public const STATUS_PAYMENT_AUTHORIZED = "PAYMENT_AUTHORIZED";
    public const STATUS_AUTHORIZATION_EXPIRED = "AUTHORIZATION_EXPIRED";
    public const STATUS_FAILED = "FAILED";
    public const STATUS_SUCCEEDED = 'SUCCEEDED';

    /**
     * min & max order totals constant.
     */
    private const LOCAL_CONFIG_LOGIC_FIELDS = [
        'min_order_total' => 'getMinOrderTotal',
        'max_order_total' => 'getMaxOrderTotal',
    ];

    /** @var string */
    private $title;

    /** @var string  */
    private $captureType;
    /** @var array */
    private $limits;
    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

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
     * @param string $captureType
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
        string $captureType = '',
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
        $this->captureType = $captureType;
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

    /**
     * @return string
     */
    public function getCaptureType(): string
    {
        return $this->captureType;
    }

    /** @inheritDoc */
    public function validate()
    {
        return $this;
    }

    public function getFormBlockType()
    {
        return Form::class;
    }
}
