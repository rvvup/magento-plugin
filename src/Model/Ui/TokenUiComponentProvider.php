<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Ui;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    public const VAULT_CODE = 'rvvup_CARD_vault';

    private const COMPONENT = 'Rvvup_Payments/js/view/payment/method-renderer/card-vault';

    /** @var TokenUiComponentInterfaceFactory */
    private $componentFactory;

    /** @var Json */
    private $json;

    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param Json $json
     */
    public function __construct(TokenUiComponentInterfaceFactory $componentFactory, Json $json)
    {
        $this->componentFactory = $componentFactory;
        $this->json = $json;
    }

    /**
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $details = $this->json->unserialize($paymentToken->getTokenDetails() ?: '{}');

        return $this->componentFactory->create([
            'config' => [
                'code' => self::VAULT_CODE,
                self::COMPONENT_DETAILS => [
                    'type' => $details['type'] ?? '',
                    'maskedCC' => $details['maskedCC'] ?? '',
                    'expirationDate' => $details['expirationDate'] ?? '',
                ],
                self::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
            ],
            'name' => self::COMPONENT,
        ]);
    }
}
