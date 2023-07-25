<?php

namespace Rvvup\Payments\Controller\Express;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
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
        if ($payment->getAdditionalInformation('is_rvvup_express_payment')) {
            $rvvupOrderId = $payment->getAdditionalInformation('rvvup_order_id');
            $order = $this->sdkProxy->getOrder($rvvupOrderId);
            if ($order && isset($order['payments'])) {
                $paymentId = $order['payments'][0]['id'];
                $this->sdkProxy->cancelPayment($paymentId, $rvvupOrderId);
                $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $result->setData(['success' => true]);
                return $result;
            }
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData(['success' => false]);
        return $result;
    }
}
