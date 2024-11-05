<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Data;

use Magento\Framework\DataObject;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;

class CreatePaymentSessionResponse extends DataObject implements CreatePaymentSessionResponseInterface
{

    public const PAYMENT_SESSION_ID = 'payment_session_id';
    public const REDIRECT_URL = 'redirect_url';

    public function getPaymentSessionId(): string
    {
        return $this->getData(self::PAYMENT_SESSION_ID);
    }

    public function setPaymentSessionId(string $paymentSessionId): void
    {
        $this->setData(self::PAYMENT_SESSION_ID, $paymentSessionId);
    }

    public function getRedirectUrl(): string
    {
        return $this->getData(self::REDIRECT_URL);
    }

    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->setData(self::REDIRECT_URL, $redirectUrl);
    }
}
