<?php declare(strict_types=1);

namespace Rvvup\Payments\Block;

use Magento\Payment\Block\ConfigurableInfo;

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
}
