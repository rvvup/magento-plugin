<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Observer\DataAssignObserver;

/**
 * @covers \Rvvup\Payments\Observer\DataAssignObserver
 */
class DataAssignObserverTest extends TestCase
{
    public function testStoresPublicHashFromRequest(): void
    {
        $payment = $this->createMock(InfoInterface::class);
        $payment->expects($this->once())->method('unsAdditionalInformation')->with(Method::PUBLIC_HASH);
        $payment->expects($this->once())->method('setAdditionalInformation')->with(Method::PUBLIC_HASH, 'abc');

        (new DataAssignObserver())->execute($this->createObserver($payment, [Method::PUBLIC_HASH => 'abc']));
    }

    public function testClearsPublicHashLeftByAnEarlierRequest(): void
    {
        $payment = $this->createMock(InfoInterface::class);
        $payment->expects($this->once())->method('unsAdditionalInformation')->with(Method::PUBLIC_HASH);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(Method::SAVE_PAYMENT_METHOD, false);

        (new DataAssignObserver())->execute(
            $this->createObserver($payment, [Method::SAVE_PAYMENT_METHOD => false])
        );
    }

    private function createObserver(InfoInterface $payment, array $additionalData): Observer
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('rvvup_CARD');

        return new Observer([
            'event' => new Event([
                AbstractDataAssignObserver::METHOD_CODE => $method,
                AbstractDataAssignObserver::MODEL_CODE => $payment,
                AbstractDataAssignObserver::DATA_CODE => new DataObject([
                    PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
                ]),
            ]),
        ]);
    }
}
