<?php declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;

class ConfigSaveObserver implements ObserverInterface
{
    /** @var State */
    private $appState;
    /** @var UrlInterface */
    private $urlBuilder;

    /**
     * @var \Rvvup\Payments\Model\ConfigInterface
     */
    private $config;

    /** @var SdkProxy */
    private $sdkProxy;
    /** @var ManagerInterface */
    private $messageManager;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param State $appState
     * @param UrlInterface $urlBuilder
     * @param \Rvvup\Payments\Model\ConfigInterface $config
     * @param SdkProxy $sdkProxy
     * @param ManagerInterface $messageManager
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        State $appState,
        UrlInterface $urlBuilder,
        ConfigInterface $config,
        SdkProxy $sdkProxy,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->appState = $appState;
        $this->urlBuilder = $urlBuilder;
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * When saving the payment configuration update the Rvvup webhook URL, so we receive payment updates
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();

        // Fail-safe, should not happen.
        if ($event === null || $event->getName() !== 'admin_system_config_changed_section_payment') {
            return;
        }

        $this->sendApiDataOnMethodDisabled($event);

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
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Problem updating Webhook URL: %1', $e->getMessage())
            );
        }
    }

    /**
     * Send API Data when payment method is disabled.
     *
     * Currently, Rvvup is disabled when the active config is set to `No`.
     * If someone tries to remove JWT, this is validated that has a JWT so cannot be removed.
     *
     * @param \Magento\Framework\Event $event
     * @return void
     */
    private function sendApiDataOnMethodDisabled(Event $event): void
    {
        if (!in_array(
            ConfigInterface::RVVUP_CONFIG . ConfigInterface::XML_PATH_ACTIVE,
            $event->getData('changed_paths'),
            true
        )) {
            return;
        }

        $scope = $this->mapScope($event);

        // No action if it was activated.
        if ($this->config->getActiveConfig($scope['scopeType'], $scope['scopeCode']) === true) {
            return;
        }

        // Otherwise, send an API request with the event.
        try {
            $this->sdkProxy->createEvent(
                'MERCHANT_PLUGIN_DEACTIVATED',
                'Magento Payment method deactivated',
                $scope
            );
        } catch (Exception $ex) {
            $this->logger->error('Failed to send create event API request to Rvvup with message: ' . $ex->getMessage());
        }
    }

    /**
     * Get the relevant scope from the event data
     *
     * @param \Magento\Framework\Event $event
     * @return array
     */
    private function mapScope(Event $event): array
    {
        $website = is_string($event->getData('website')) ? $event->getData('website') : '';
        $store = is_string($event->getData('store')) ? $event->getData('store') : '';

        // If no value for either website or store, return default scope config.
        if (empty($website) && empty($store)) {
            return [
                'scopeType' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                'scopeCode' => null
            ];
        }

        // Otherwise, it's either website (if store is empty)
        if (empty($store)) {
            return [
                'scopeType' => ScopeInterface::SCOPE_WEBSITE,
                'scopeCode' => $website
            ];
        }

        // Or store if not empty.
        return [
            'scopeType' => ScopeInterface::SCOPE_STORE,
            'scopeCode' => $store
        ];
    }
}
