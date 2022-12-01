<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Config\Jwt;

use GuzzleHttp\Client;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdkFactory;

class Validator extends Encrypted
{
    /** @var UrlInterface */
    private UrlInterface $urlBuilder;
    /** @var UserAgentBuilder */
    private UserAgentBuilder $userAgentBuilder;
    /** @var GraphQlSdkFactory */
    private GraphQlSdkFactory $sdkFactory;
    /** @var ManagerInterface */
    private ManagerInterface $messageManager;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param EncryptorInterface $encryptor
     * @param UrlInterface $urlBuilder
     * @param UserAgentBuilder $userAgentBuilder
     * @param GraphQlSdkFactory $sdkFactory
     * @param ManagerInterface $messageManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        EncryptorInterface $encryptor,
        UrlInterface $urlBuilder,
        UserAgentBuilder $userAgentBuilder,
        GraphQlSdkFactory $sdkFactory,
        ManagerInterface $messageManager,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->userAgentBuilder = $userAgentBuilder;
        $this->sdkFactory = $sdkFactory;
        $this->messageManager = $messageManager;
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $encryptor,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * If value is not obscured and has a value verify the JWT is valid
     *
     * @return void
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $value = (string) $this->getValue();
        if (!preg_match('/^\*+$/', $value) && !empty($value)) {
            $this->validate($value);
            parent::beforeSave();
            return;
        }
        parent::beforeSave();
    }

    /**
     * @param string $jwt
     * @return void
     * @throws ValidatorException
     */
    private function validate(string $jwt): void
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new ValidatorException(__('API key is invalid'));
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $payloadString = base64_decode($parts[1], true);
        if (false === $payloadString) {
            throw new ValidatorException(__('API key is invalid'));
        }

        try {
            $jwt = json_decode($payloadString, true, 2, JSON_THROW_ON_ERROR);
            $this->updateWebhook($jwt);
        } catch (\Exception $e) {
            throw new ValidatorException(__('API key is invalid, changes not saved'));
        }
    }

    /**
     * @param array $jwt
     * @return void
     * @throws \Exception
     */
    private function updateWebhook(array $jwt): void
    {
        if ($this->_appState->getMode() === State::MODE_DEVELOPER) {
            $this->messageManager->addWarningMessage(
                'Webhook update bypassed, Magento is in developer mode'
            );
            return;
        }
        $url = $this->urlBuilder->getDirectUrl('rvvup/webhook');

        $connection = $this->sdkFactory->create([
            'endpoint' => $jwt['aud'],
            'merchantId' => $jwt['merchantId'],
            'authToken' => base64_encode($jwt['username'] . ':' . $jwt['password']),
            'userAgent' => $this->userAgentBuilder->get(),
            'debug' => false,
            'adapter' => (new Client()),
        ]);
        $connection->registerWebhook($url);
        $this->messageManager->addSuccessMessage('Webhook URL updated successfully');
    }
}
