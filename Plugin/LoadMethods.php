<?php declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Checkout\Model\Session\Proxy;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Factory;
use Magento\Payment\Model\MethodInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;

class LoadMethods
{
    /** @var ConfigInterface */
    private $config;
    /** @var SdkProxy */
    private $sdkProxy;
    /** @var array|null */
    private $processed = null;
    /** @var array */
    private $instances = [];
    /** @var array */
    private $methods = [];
    /** @var array */
    private $template;
    /** @var Factory */
    private $methodFactory;
    /** @var Proxy */
    private $checkoutSession;

    /**
     * @param ConfigInterface $config
     * @param SdkProxy $sdkProxy
     * @param Factory $methodFactory
     * @param SessionManagerInterface $checkoutSession
     * @return void
     */
    public function __construct(
        ConfigInterface $config,
        SdkProxy $sdkProxy,
        Factory $methodFactory,
        SessionManagerInterface $checkoutSession
    ) {
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->methodFactory = $methodFactory;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param Data $subject
     * @param array $result
     * @return array
     */
    public function afterGetPaymentMethods(Data $subject, array $result): array
    {
        if (isset($result['rvvup'])) {
            if (!$this->config->isActive()) {
                return $result;
            }
            $this->template = $result['rvvup'];
            unset($result['rvvup']);
        }
        if (!$this->methods) {
            $total = $this->checkoutSession->getQuote()->getGrandTotal();
            $currency = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();
            $this->methods = $this->sdkProxy->getMethods((string) $total, $currency ?? '');
        }
        return array_merge(
            $result,
            $this->processMethods($this->methods)
        );
    }

    /**
     * Modify results of getMethodInstance() call to add in details about Klarna payment methods
     *
     * @param \Magento\Payment\Helper\Data $subject
     * @param callable                     $proceed
     * @param string                       $code
     * @return MethodInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function aroundGetMethodInstance(\Magento\Payment\Helper\Data $subject, callable $proceed, $code)
    {
        if (0 === strpos($code, 'rvvup_')) {
            if (isset($this->instances[$code])) {
                return $this->instances[$code];
            }

            if ($this->config->isActive() && !$this->processed) {
                $this->processMethods($this->sdkProxy->getMethods('0', 'GBP'));
            }

            if (isset($this->processed[$code])) {
                $method = $this->processed[$code];
            } else {
                $method = [
                    'code' => $code
                ];
            }

            /** @var \Rvvup\Payments\Gateway\Method $instance */
            $instance = $this->methodFactory->create(
                'RvvupFacade',
                [
                    'code' => $code,
                    'title' => $method['title'] ?? 'Rvvup',
                    'summary_url' => $method['summaryUrl'] ?? '',
                    'logo_url' => $method['logoUrl'] ?? '',
                    'limits' => $method['limits'] ?? null,
                ]
            );
            return $instance;
        }
        return $proceed($code);
    }

    private function processMethods(array $methods): array
    {
        if (!$this->processed) {
            $processed = [];
            foreach ($methods as $method) {
                $code = 'rvvup_' . $method['name'];
                $processed[$code] = $this->template;
                $processed[$code]['title'] = $method['displayName'];
                $processed[$code]['description'] = $method['description'];
                $processed[$code]['isActive'] = true;
                $processed[$code]['summaryUrl'] = $method['summaryUrl'];
                $processed[$code]['logoUrl'] = $method['logoUrl'] ?? '';
                $processed[$code]['limits'] = $this->processLimits($method['limits']['total'] ?? []);
            }
            $this->processed = $processed;
        }
        return $this->processed;
    }

    private function processLimits(array $limits): array
    {
        $processed = [];
        foreach ($limits as $limit) {
            $processed[$limit['currency']] = [
                'min' => $limit['min'],
                'max' => $limit['max'],
            ];
        }
        return $processed;
    }
}
