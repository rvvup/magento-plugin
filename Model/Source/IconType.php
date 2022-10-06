<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Source;

class IconType implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'badge', 'label' => __('Badge')],
            ['value' => 'lockup', 'label' => __('Lockup')],
        ];
    }
}
