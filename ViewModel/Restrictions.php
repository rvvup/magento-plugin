<?php declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Restriction\Messages;

class Restrictions implements ArgumentInterface
{
    /** @var ConfigInterface */
    private $config;
    /** @var Messages */
    private $messages;

    /**
     * @param ConfigInterface $config
     * @param Messages $messages
     */
    public function __construct(
        ConfigInterface $config,
        Messages $messages
    ) {
        $this->config = $config;
        $this->messages = $messages;
    }

    /**
     * @param ProductInterface $product
     * @return bool
     */
    public function showRestrictionMessage($product): bool
    {
        return $this->config->isActive() && $product->getRvvupRestricted();
    }

    /**
     * @return Messages
     */
    public function getMessages(): Messages
    {
        return $this->messages;
    }
}
