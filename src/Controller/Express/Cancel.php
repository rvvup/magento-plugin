<?php

namespace Rvvup\Payments\Controller\Express;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;

class Cancel implements HttpGetActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;

    /** @var Session */
    private $checkoutSession;

    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param ResultFactory $resultFactory
     * @param Session $checkoutSession
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        ResultFactory $resultFactory,
        Session $checkoutSession,
        SdkProxy $sdkProxy
    ) {
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->sdkProxy = $sdkProxy;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $payment = $this->checkoutSession->getQuote()->getPayment();
        if ($payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY)) {
            $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
            $paymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);

            $this->sdkProxy->cancelPayment($paymentId, $rvvupOrderId);
            $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $result->setData(['success' => true]);
            return $result;
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData(['success' => false]);
        return $result;
    }
}
