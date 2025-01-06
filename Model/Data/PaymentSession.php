<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Data;

use Magento\Framework\DataObject;
use Rvvup\Payments\Api\Data\PaymentSessionInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;

class PaymentSession extends DataObject implements PaymentSessionInterface
{

    public const PAYMENT_SESSION_ID = 'payment_session_id';
    public const REDIRECT_URL = 'redirect_url';

    /**
     * @return string
     */
    public function getPaymentSessionId(): string
    {
        return $this->getData(self::PAYMENT_SESSION_ID);
    }

    /**
     * @param string $paymentSessionId
     * @return void
     */
    public function setPaymentSessionId(string $paymentSessionId): void
    {
        $this->setData(self::PAYMENT_SESSION_ID, $paymentSessionId);
    }

    /**
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return $this->getData(self::REDIRECT_URL);
    }

    /**
     * @param string $redirectUrl
     * @return void
     */
    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->setData(self::REDIRECT_URL, $redirectUrl);
    }
}
