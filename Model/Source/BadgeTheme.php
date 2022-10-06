<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Source;

class BadgeTheme implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'black-on-mint', 'label' => __('Black on Mint')],
            ['value' => 'black-on-white', 'label' => __('Black on White')],
            ['value' => 'mint-on-black', 'label' => __('Mint on Black')],
            ['value' => 'white-on-black', 'label' => __('White on Black')],
        ];
    }
}
