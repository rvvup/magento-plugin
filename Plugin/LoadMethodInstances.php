<?php declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Checkout\Model\Session\Proxy;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Factory;
use Magento\Payment\Model\MethodInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Traits\LoadMethods;

class LoadMethodInstances
{
    use LoadMethods;

    /** @var ConfigInterface */
    private $config;

    /** @var SdkProxy */
    private $sdkProxy;

    /** @var array */
    private $instances = [];

    /** @var Factory */
    private $methodFactory;

    /**
     * @param ConfigInterface $config
     * @param SdkProxy $sdkProxy
     * @param Factory $methodFactory
     */
    public function __construct(
        ConfigInterface $config,
        SdkProxy        $sdkProxy,
        Factory         $methodFactory
    ) {
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->methodFactory = $methodFactory;
    }

    /**
     * Modify results of getMethodInstance() call to add in details about Klarna payment methods
     *
     * @param Data $subject
     * @param callable $proceed
     * @param string $code
     * @return MethodInterface
     * @throws LocalizedException
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function aroundGetMethodInstance(Data $subject, callable $proceed, $code)
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
                /** @var Method $instance */
                $instance = $this->methodFactory->create(
                    'RvvupFacade',
                    [
                        'code' => $code,
                        'title' => $method['title'] ?? 'Rvvup',
                        'summary_url' => $method['summaryUrl'] ?? '',
                        'logo_url' => $method['logoUrl'] ?? '',
                        'limits' => $method['limits'] ?? null,
                        'captureType' => $method['captureType'] ?? '',
                    ]
                );
                return $instance;
            }
        }

        return $proceed($code);
    }
}
