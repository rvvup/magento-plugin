<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Element\Template;
use Rvvup\Payments\Model\Clearpay\Config;
use Rvvup\Payments\Model\ConfigInterface as RvvupConfig;

class ConfigProvider implements ConfigProviderInterface
{
    /** @var \Rvvup\Payments\Model\ConfigInterface|RvvupConfig */
    private $config;
    /** @var SdkProxy */
    private $sdkProxy;
    /** @var SessionManagerInterface */
    private $checkoutSession;
    /** @var Template */
    private $template;
    /** @var Config */
    private $clearpayConfig;

    /**
     * @param \Rvvup\Payments\Model\ConfigInterface|RvvupConfig $config
     * @param SdkProxy $sdkProxy
     * @param SessionManagerInterface $checkoutSession
     * @param Template $template
     * @param Config $clearpayConfig
     */
    public function __construct(
        RvvupConfig $config,
        SdkProxy $sdkProxy,
        SessionManagerInterface $checkoutSession,
        Template $template,
        Config $clearpayConfig
    ) {
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->checkoutSession = $checkoutSession;
        $this->template = $template;
        $this->clearpayConfig = $clearpayConfig;
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        if (!$this->config->isActive()) {
            return [];
        }
        $quote = $this->checkoutSession->getQuote();
        $grandTotal = $quote->getGrandTotal();
        $currency = $quote->getQuoteCurrencyCode();
        $methods = $this->sdkProxy->getMethods((string) $grandTotal, $currency);
        $items = [];
        foreach ($methods as $method) {
            $items['rvvup_' . $method['name']] = [
                'component' => 'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
                'isBillingAddressRequired' => true,
                'description' => $method['description'],
                'logo' => $this->getLogo($method['name']),
                'summary_url' => $method['summaryUrl'],
                'assets' => $method['assets'],
            ];
        }
        return ['payment' => $items];
    }

    private function getLogo(string $code): string
    {
        $base = 'Rvvup_Payments::images/%s.svg';
        switch ($code) {
            case 'YAPILY':
                $url = sprintf($base, 'yapily');
                break;
            case 'CLEARPAY':
                $theme = $this->clearpayConfig->getTheme();
                $url = sprintf($base, 'clearpay/' . $theme);
                break;
            case 'PAYPAL':
                $url = sprintf($base, 'paypal');
                break;
            case 'FAKE_PAYMENT_METHOD':
            default:
                $url = sprintf($base, 'rvvup');
        }
        return $this->template->getViewFileUrl($url);
    }
}
