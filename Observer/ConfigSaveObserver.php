<?php declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Rvvup\Payments\Model\SdkProxy;

class ConfigSaveObserver implements ObserverInterface
{
    /** @var State */
    private $appState;
    /** @var UrlInterface */
    private $urlBuilder;
    /** @var SdkProxy */
    private $sdkProxy;
    /** @var ManagerInterface */
    private $messageManager;

    /**
     * @param State $appState
     * @param UrlInterface $urlBuilder
     * @param SdkProxy $sdkProxy
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        State $appState,
        UrlInterface $urlBuilder,
        SdkProxy $sdkProxy,
        ManagerInterface $messageManager
    ) {
        $this->appState = $appState;
        $this->urlBuilder = $urlBuilder;
        $this->sdkProxy = $sdkProxy;
        $this->messageManager = $messageManager;
    }

    /**
     * When saving the payment configuration update the Rvvup webhook URL, so we receive payment updates
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if ($this->appState->getMode() === State::MODE_DEVELOPER) {
            $this->messageManager->addWarningMessage(
                'Webhook update bypassed, Magento is in developer mode'
            );
            return;
        }
        $url = $this->urlBuilder->getDirectUrl('rvvup/webhook');

        try {
            $this->sdkProxy->registerWebhook($url);
            $this->messageManager->addSuccessMessage('Webhook URL updated successfully');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Problem updating Webhook URL: %1', $e->getMessage())
            );
        }
    }
}
