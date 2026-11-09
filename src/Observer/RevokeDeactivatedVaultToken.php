<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\PaymentToken;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Service\ApiProvider;
use Throwable;

/**
 * Magento deletes a Vault token with a soft delete (is_active false, then save), so no delete event is
 * dispatched. A saved card is therefore revoked on Rvvup when an active rvvup_CARD token is deactivated.
 *
 * When a customer is signed in, the token must be theirs. Without a customer (admin, cron, CLI) the token is
 * still revoked, otherwise it would stay usable on Rvvup after it was removed from the Vault.
 */
class RevokeDeactivatedVaultToken implements ObserverInterface
{
    private const PAYMENT_METHOD_CODE = 'rvvup_CARD';

    /** @var ApiProvider */
    private $apiProvider;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var UserContextInterface */
    private $userContext;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ApiProvider $apiProvider
     * @param StoreManagerInterface $storeManager
     * @param UserContextInterface $userContext
     * @param LoggerInterface $logger Set via di.xml
     */
    public function __construct(
        ApiProvider $apiProvider,
        StoreManagerInterface $storeManager,
        UserContextInterface $userContext,
        LoggerInterface $logger
    ) {
        $this->apiProvider = $apiProvider;
        $this->storeManager = $storeManager;
        $this->userContext = $userContext;
        $this->logger = $logger;
    }

    /**
     * Never throws: a failed revoke must not stop the customer from removing the card locally.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $token = $observer->getEvent()->getData('data_object');

        if (!$token instanceof PaymentToken
            || !$token->getOrigData('is_active')
            || $token->getIsActive()
            || $token->getPaymentMethodCode() !== self::PAYMENT_METHOD_CODE
            || empty($token->getGatewayToken())
        ) {
            return;
        }

        try {
            $signedInCustomerId = $this->getSignedInCustomerId();

            if ($signedInCustomerId !== null && $signedInCustomerId !== (int)$token->getCustomerId()) {
                $this->logger->warning(
                    'Rvvup did not revoke a saved card token: it belongs to another customer than the one signed in',
                    ['vault_token_id' => $token->getEntityId()]
                );
                return;
            }

            $storeId = (string)$this->storeManager->getStore()->getId();

            $revoked = $this->apiProvider->getSdk($storeId)
                ->paymentMethodTokens()
                ->revoke((string)$token->getGatewayToken());

            if (!$revoked) {
                $this->logger->info(
                    'Rvvup saved card token was already gone when deleting the Vault token',
                    ['vault_token_id' => $token->getEntityId()]
                );
            }
        } catch (Throwable $e) {
            // Deliberately no gateway token in the log.
            $this->logger->error(
                'Rvvup failed to revoke the saved card token on delete: ' . $e->getMessage(),
                ['vault_token_id' => $token->getEntityId()]
            );
        }
    }

    /**
     * Customer behind the current request, or null when there is none (admin, cron, CLI, guest).
     *
     * @return int|null
     */
    private function getSignedInCustomerId(): ?int
    {
        $customerId = (int)$this->userContext->getUserId();

        if ($this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER || $customerId <= 0) {
            return null;
        }

        return $customerId;
    }
}
