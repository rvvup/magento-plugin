<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Source;

class ModalTheme implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'white', 'label' => __('White')],
            ['value' => 'mint', 'label' => __('Mint')],
        ];
    }
}
