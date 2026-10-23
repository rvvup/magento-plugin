<?php declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Rvvup\Payments\Gateway\Method;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @var array
     */
    protected $additionalInformationList = [
        Method::ORDER_ID,
        Method::DASHBOARD_URL,
        Method::EXPRESS_PAYMENT_KEY,
        Method::EXPRESS_PAYMENT_DATA_KEY,
        Method::TRANSACTION_ID,
        Method::SAVE_PAYMENT_METHOD,
        Method::PUBLIC_HASH,
    ];

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        if (false === strpos($method->getCode(), Method::PAYMENT_TITLE_PREFIX)) {
            return;
        }

        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Additional information outlives the request on the quote payment. A saved card must only be used by
        // the request that names it, not by a later payment with a new card.
        $paymentInfo->unsAdditionalInformation(Method::PUBLIC_HASH);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
