<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Ui;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Ui\TokenUiComponentProvider;

/**
 * @covers \Rvvup\Payments\Model\Ui\TokenUiComponentProvider
 */
class TokenUiComponentProviderTest extends TestCase
{
    public function testBuildsComponentFromTokenDetailsAndPublicHash(): void
    {
        $component = $this->createMock(TokenUiComponentInterface::class);
        $factory = $this->getMockBuilder(TokenUiComponentInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $factory->expects($this->once())
            ->method('create')
            ->with([
                'config' => [
                    'code' => 'rvvup_CARD_vault',
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => [
                        'type' => 'VI',
                        'maskedCC' => '1111',
                        'expirationDate' => '12/2030',
                    ],
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => 'public-hash',
                ],
                'name' => 'Rvvup_Payments/js/view/payment/method-renderer/card-vault',
            ])
            ->willReturn($component);
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getTokenDetails')
            ->willReturn('{"type":"VI","maskedCC":"1111","expirationDate":"12/2030","other":"x"}');
        $token->method('getPublicHash')->willReturn('public-hash');

        $provider = new TokenUiComponentProvider($factory, new Json());

        $this->assertSame($component, $provider->getComponentForToken($token));
    }
}
