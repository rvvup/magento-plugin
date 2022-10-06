<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Clearpay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Rvvup\Payments\Model\ConfigInterface as RvvupConfig;

class Config
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    private const CLEARPAY_CONFIG = RvvupConfig::RVVUP_CONFIG . 'clearpay_messaging/';
    private const ACTIVE = 'active';
    private const BADGE_THEME = 'badge_theme';
    private const ICON_TYPE = 'icon_type';
    private const MODAL_THEME = 'modal_theme';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::CLEARPAY_CONFIG . self::ACTIVE);
    }

    /**
     * @return string
     */
    public function getTheme(): string
    {
        return (string) $this->scopeConfig->getValue(self::CLEARPAY_CONFIG . self::BADGE_THEME);
    }

    /**
     * @return string
     */
    public function getIconType(): string
    {
        return (string) $this->scopeConfig->getValue(self::CLEARPAY_CONFIG . self::ICON_TYPE);
    }

    /**
     * @return string
     */
    public function getModalTheme(): string
    {
        return (string) $this->scopeConfig->getValue(self::CLEARPAY_CONFIG . self::MODAL_THEME);
    }
}
