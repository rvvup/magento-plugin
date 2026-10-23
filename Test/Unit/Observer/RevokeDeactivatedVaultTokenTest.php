<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Observer;

use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\PaymentToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Observer\RevokeDeactivatedVaultToken;
use Rvvup\Payments\Service\ApiProvider;
use Rvvup\Sdk\Rest\PaymentMethodTokens;
use Rvvup\Sdk\Rest\RvvupClient;

/**
 * @covers \Rvvup\Payments\Observer\RevokeDeactivatedVaultToken
 */
class RevokeDeactivatedVaultTokenTest extends TestCase
{
    /** @var PaymentMethodTokens|MockObject */
    private $paymentMethodTokens;

    /** @var UserContextInterface|MockObject */
    private $userContext;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var RevokeDeactivatedVaultToken */
    private $observer;

    protected function setUp(): void
    {
        $this->paymentMethodTokens = $this->createMock(PaymentMethodTokens::class);
        $client = $this->createMock(RvvupClient::class);
        $client->method('paymentMethodTokens')->willReturn($this->paymentMethodTokens);
        $apiProvider = $this->createMock(ApiProvider::class);
        $apiProvider->method('getSdk')->with('1')->willReturn($client);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->userContext = $this->createMock(UserContextInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observer = new RevokeDeactivatedVaultToken(
            $apiProvider,
            $storeManager,
            $this->userContext,
            $this->logger
        );
    }

    public function testRevokesTheRvvupTokenWhenAnActiveTokenIsDeactivated(): void
    {
        $this->paymentMethodTokens->expects($this->once())->method('revoke')->with('tok_rvvup')->willReturn(true);
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('error');

        $this->execute($this->aToken());
    }

    public function testFalseFromRevokeIsLoggedAtInfoLevelOnly(): void
    {
        $this->paymentMethodTokens->method('revoke')->willReturn(false);
        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->never())->method('error');

        $this->execute($this->aToken());
    }

    public function testAnExceptionIsLoggedAtErrorLevelAndNotThrown(): void
    {
        $this->paymentMethodTokens->method('revoke')->willThrowException(new Exception('API down'));
        $this->logger->expects($this->once())->method('error');

        $this->execute($this->aToken());
    }

    public function testRevokesWhenTheSignedInCustomerOwnsTheToken(): void
    {
        $this->signedInAs(UserContextInterface::USER_TYPE_CUSTOMER, 42);
        $this->paymentMethodTokens->expects($this->once())->method('revoke')->with('tok_rvvup')->willReturn(true);
        $this->logger->expects($this->never())->method('warning');

        $this->execute($this->aToken(['customerId' => 42]));
    }

    public function testDoesNotRevokeATokenOfAnotherCustomer(): void
    {
        $this->signedInAs(UserContextInterface::USER_TYPE_CUSTOMER, 42);
        $this->paymentMethodTokens->expects($this->never())->method('revoke');
        $this->logger->expects($this->once())->method('warning');
        $this->logger->expects($this->never())->method('error');

        $this->execute($this->aToken(['customerId' => 7]));
    }

    /**
     * Without a customer (admin, cron, CLI) the token must still be revoked, or it stays usable on Rvvup.
     *
     * @dataProvider contextWithoutACustomerProvider
     */
    public function testRevokesWhenNoCustomerIsSignedIn(?int $userType, ?int $userId): void
    {
        $this->signedInAs($userType, $userId);
        $this->paymentMethodTokens->expects($this->once())->method('revoke')->with('tok_rvvup')->willReturn(true);
        $this->logger->expects($this->never())->method('warning');

        $this->execute($this->aToken(['customerId' => 7]));
    }

    public function contextWithoutACustomerProvider(): array
    {
        return [
            'admin' => [UserContextInterface::USER_TYPE_ADMIN, 1],
            'integration' => [UserContextInterface::USER_TYPE_INTEGRATION, 3],
            'cron or CLI' => [null, null],
            'guest' => [UserContextInterface::USER_TYPE_GUEST, null],
            'customer context without a customer' => [UserContextInterface::USER_TYPE_CUSTOMER, null],
            'customer context with id 0' => [UserContextInterface::USER_TYPE_CUSTOMER, 0],
        ];
    }

    public function testIgnoresTokensOfAnotherProvider(): void
    {
        $this->paymentMethodTokens->expects($this->never())->method('revoke');

        $this->execute($this->aToken(['code' => 'braintree']));
    }

    public function testIgnoresTokensWithoutAGatewayToken(): void
    {
        $this->paymentMethodTokens->expects($this->never())->method('revoke');

        $this->execute($this->aToken(['gateway' => '']));
    }

    public function testIgnoresTheSaveOfANewActiveToken(): void
    {
        $this->paymentMethodTokens->expects($this->never())->method('revoke');

        $this->execute($this->aToken(['wasActive' => null, 'isActive' => true]));
    }

    public function testIgnoresATokenThatStaysActive(): void
    {
        $this->paymentMethodTokens->expects($this->never())->method('revoke');

        $this->execute($this->aToken(['wasActive' => 1, 'isActive' => true]));
    }

    public function testIgnoresATokenThatWasAlreadyInactive(): void
    {
        $this->paymentMethodTokens->expects($this->never())->method('revoke');

        $this->execute($this->aToken(['wasActive' => 0, 'isActive' => false]));
    }

    public function testIgnoresEventObjectsThatAreNotVaultTokens(): void
    {
        $this->paymentMethodTokens->expects($this->never())->method('revoke');

        $this->execute(new DataObject());
    }

    /**
     * @param array $overrides
     * @return PaymentToken|MockObject
     */
    private function aToken(array $overrides = [])
    {
        $values = $overrides + [
            'code' => 'rvvup_CARD',
            'gateway' => 'tok_rvvup',
            'customerId' => 42,
            'wasActive' => 1,
            'isActive' => false,
        ];

        $token = $this->createMock(PaymentToken::class);
        $token->method('getOrigData')->with('is_active')->willReturn($values['wasActive']);
        $token->method('getIsActive')->willReturn($values['isActive']);
        $token->method('getPaymentMethodCode')->willReturn($values['code']);
        $token->method('getGatewayToken')->willReturn($values['gateway']);
        $token->method('getCustomerId')->willReturn($values['customerId']);
        $token->method('getEntityId')->willReturn(7);

        return $token;
    }

    /**
     * @param int|null $userType
     * @param int|null $userId
     * @return void
     */
    private function signedInAs(?int $userType, ?int $userId): void
    {
        $this->userContext->method('getUserType')->willReturn($userType);
        $this->userContext->method('getUserId')->willReturn($userId);
    }

    /**
     * @param DataObject $dataObject
     * @return void
     */
    private function execute(DataObject $dataObject): void
    {
        $this->observer->execute(new Observer(['event' => new Event(['data_object' => $dataObject])]));
    }
}
