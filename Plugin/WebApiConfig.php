<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaCheckout\Model\WebapiConfigProvider;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use Rvvup\Payments\Api\CustomerPaymentActionsGetInterface;
use Rvvup\Payments\Api\GuestPaymentActionsGetInterface;

class WebApiConfig
{
    public const PLACE_ORDER = 'place_order';

    /** @var IsCaptchaEnabledInterface */
    private $isEnabled;

    /** @var ValidationConfigResolverInterface */
    private $configResolver;

    /**
     * @param IsCaptchaEnabledInterface $isEnabled
     * @param ValidationConfigResolverInterface $configResolver
     */
    public function __construct(
        IsCaptchaEnabledInterface $isEnabled,
        ValidationConfigResolverInterface $configResolver
    ) {
        $this->isEnabled = $isEnabled;
        $this->configResolver = $configResolver;
    }

    /**
     * @param WebapiConfigProvider $subject
     * @param ValidationConfigInterface|null $result
     * @param EndpointInterface $endpoint
     * @return ValidationConfigInterface
     * @throws InputException
     */
    public function afterGetConfigFor(
        WebapiConfigProvider       $subject,
        ?ValidationConfigInterface $result,
        EndpointInterface          $endpoint
    ): ?ValidationConfigInterface {
        if ($endpoint->getServiceClass() === GuestPaymentActionsGetInterface::class ||
            $endpoint->getServiceClass() === CustomerPaymentActionsGetInterface::class) {
            if ($endpoint->getServiceMethod() === 'execute') {
                if ($this->isEnabled->isCaptchaEnabledFor(self::PLACE_ORDER)) {
                    return $this->configResolver->get(self::PLACE_ORDER);
                }
            }
        }
        return $result;
    }
}
