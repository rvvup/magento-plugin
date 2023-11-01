<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\TrustPayments;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Rvvup\Payments\Model\SdkProxy;

class Confirm implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;

    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param ResultFactory $resultFactory
     * @param SdkProxy $sdkProxy
     * @param RequestInterface $request
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        ResultFactory $resultFactory,
        SdkProxy $sdkProxy,
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->resultFactory = $resultFactory;
        $this->sdkProxy = $sdkProxy;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);

        try {
            $orderId = $this->request->getParam('order_id', false);
            $order = $this->orderRepository->get((int)$orderId);

            if ($order) {
                $rvvupOrderId = (string) $order->getPayment()->getAdditionalInformation('rvvup_order_id');
                $rvvupOrder = $this->sdkProxy->getOrder($rvvupOrderId);
                $rvvupPaymentId = $rvvupOrder['payments'][0]['id'];

                $authorizationResponse = $this->request->getParam('auth', false);
                $threeDSecureResponse = $this->request->getParam('three_d', null);

                $this->sdkProxy->confirmCardAuthorization(
                    $rvvupPaymentId,
                    $rvvupOrderId,
                    $authorizationResponse,
                    $threeDSecureResponse
                );

                $response->setData([
                    'success' => true,
                ]);
            } else {
                $response->setData([
                    'success' => false,
                    'error_message' => 'Order not found during card authorization',
                    'retryable' => false,
                ]);
            }
            $response->setHttpResponseCode(200);
            return $response;
        } catch (\Exception $exception) {

            $data = [
                'success' => false,
                'error_message' => 'Error confirming card authorization'
            ];

            if ($exception->getCode() == 101) {
                $data['retryable'] = true;
            } else {
                $data['retryable'] = false;
            }

            $response->setData($data);
            $response->setHttpResponseCode(200);
            return $response;
        }
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
