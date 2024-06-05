<?php declare(strict_types=1);

namespace Rvvup\Payments\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class Info extends ConfigurableInfo
{
    /**
     * Label mapping constants.
     */
    public const LABEL_ID = 'Rvvup Order ID';
    public const LABEL_METHOD_TITLE = 'Payment method';

    /**
     * @var string
     */
    protected $_template = 'Rvvup_Payments::info/default.phtml';

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /**
     * @param Context $context
     * @param ConfigInterface $config
     * @param CartRepositoryInterface $cartRepository
     * @param array $data
     */
    public function __construct(
        Context                 $context,
        ConfigInterface         $config,
        CartRepositoryInterface $cartRepository,
        array                   $data = []
    ) {
        $this->cartRepository = $cartRepository;
        parent::__construct(
            $context,
            $config,
            $data
        );
    }

    /**
     * @var string[]
     */
    private $labels = [
        'id' => self::LABEL_ID,
        'method_title' => self::LABEL_METHOD_TITLE
    ];

    /**
     * @param \Magento\Framework\Phrase|string $field
     * @return \Magento\Framework\Phrase|string
     */
    protected function getLabel($field)
    {
        return $this->labels[$field] ?? $field;
    }

    /**
     * @param string $field
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAdditionalInformation(string $field): string
    {
        $payment = $this->getInfo();
        if ($payment->getAdditionalInformation($field)) {
            return $payment->getAdditionalInformation($field) ?: '';
        } elseif ($this->getInfo() instanceof OrderPaymentInterface) {
            $quoteId = $payment->getOrder()->getQuoteId();
            if ($quoteId) {
                $cart = $this->cartRepository->get((int)$quoteId);
                return $cart->getPayment()->getAdditionalInformation($field) ?: '';
            }
        }
        return '';
    }
}
