<?php

namespace Rvvup\Payments\Plugin\Order\Create;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Service\PaymentLink as PaymentLinkService;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Psr\Log\LoggerInterface;

class PaymentLink
{
    /** @var Config */
    private $config;

    /** @var Http */
    private $request;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface
     */
    private $logger;

    /** @var PaymentLinkService */
    private $paymentLinkService;

    /**
     * @param Config $config
     * @param Http $request
     * @param LoggerInterface $logger
     * @param PaymentLinkService $paymentLinkService
     */
    public function __construct(
        Config                             $config,
        Http                               $request,
        LoggerInterface                    $logger,
        PaymentLinkService                 $paymentLinkService
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->logger = $logger;
        $this->paymentLinkService = $paymentLinkService;
    }

    /**
     * @param Create $subject
     * @param Create $result
     * @param array $data
     * @return Create
     * @throws NoSuchEntityException
     */
    public function afterImportPostData(Create $subject, Create $result, array $data): Create
    {
        if ($result->getQuote() && $result->getQuote()->getPayment()->getMethod() == RvvupConfigProvider::CODE) {
            $createPaymentLink = $this->request->getPost('payment');
            $createPaymentLink = isset($createPaymentLink['moto']) ? $createPaymentLink['moto'] : null;

            $payment = $subject->getQuote()->getPayment();
            $payment->setAdditionalInformation('create_rvvup_payment_link', $createPaymentLink);

            if ($createPaymentLink == 'payment_link' && isset($data['comment'])) {
                if (!$payment->getAdditionalInformation('rvvup_payment_link_id')) {
                    $quote = $result->getQuote();
                    $storeId = (string)$quote->getStore()->getId();
                    $amount = (float)$quote->getGrandTotal();
                    $orderId = $quote->reserveOrderId()->getReservedOrderId();
                    $currencyCode = $quote->getQuoteCurrencyCode();
                    $order = $this->request->getPost('order');
                    if (!isset($order['account']) || !isset($order['send_confirmation'])) {
                        return $result;
                    }
                    if ($result->getQuote()->getPayment()->getMethod() == RvvupConfigProvider::CODE) {
                        if ($this->config->isActive(ScopeInterface::SCOPE_STORE, $storeId)) {
                            list($id, $message) =
                                $this->createRvvupPayByLink($storeId, $amount, $orderId, $currencyCode, $subject, $data);
                            if ($id && $message) {
                                $payment = $subject->getQuote()->getPayment();
                                $this->paymentLinkService->savePaymentLink($payment, $id, $message);
                            }
                        }
                    }
                } else {
                    $quote = $subject->getQuote();
                    if ($quote->getPayment()->getMethod() == RvvupConfigProvider::CODE) {
                        $message = $quote->getPayment()->getAdditionalInformation('rvvup_payment_link_message');
                        $quote->addData(['customer_note' => $message, 'customer_note_notify' => true]);
                    }
                }
            }
        }
        return $result;
    }

    /** Send separate confirmation if merchant is not
     * informing customer with order success email
     * @param Create $subject
     * @param Order $result
     * @return Order
     * @throws NoSuchEntityException
     */
    public function afterCreateOrder(Create $subject, Order $result): Order
    {
        if (!(isset($subject['send_confirmation']) && $subject['send_confirmation'])) {
            $payment = $subject->getQuote()->getPayment();
            $createPaymentLink = $payment->getAdditionalInformation('create_rvvup_payment_link');
            if (!$payment->getAdditionalInformation('rvvup_payment_link_id')
                && $createPaymentLink == 'payment_link'
            ) {
                if ($payment->getMethod() == RvvupConfigProvider::CODE) {
                    if ($this->config->isActive(ScopeInterface::SCOPE_STORE, $result->getStoreId())) {
                        list($id, $message) = $this->createRvvupPayByLink(
                            (string)$result->getStoreId(),
                            $result->getGrandTotal(),
                            $result->getId(),
                            $result->getOrderCurrencyCode(),
                            $subject,
                            ['status' => $result->getStatus()]
                        );
                        if ($id && $message) {
                            $payment = $result->getPayment();
                            $this->paymentLinkService->savePaymentLink($payment, $id, $message);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Create Rvvup pay-by-link and save it to comment
     * @param string $storeId
     * @param float $amount
     * @param string $orderId
     * @param string $currencyCode
     * @param Create $subject
     * @param array $data
     * @return array|null
     */
    private function createRvvupPayByLink(
        string $storeId,
        float $amount,
        string $orderId,
        string $currencyCode,
        Create $subject,
        array $data
    ): ?array {
        try {
            $amount = number_format($amount, 2, '.', '');
            if ($amount <= 0) {
                return [null,null];
            }
            $body = $this->paymentLinkService->createPaymentLink($storeId, $amount, $orderId, $currencyCode);
            $message = $this->processApiResponse($body, $amount, $subject, $data, $orderId);
            return [$body['id'], $message];
        } catch (\Exception $e) {
            $this->logger->error('Rvvup payment link creation failed with error: ' . $e->getMessage());
        }
        return [null,null];
    }

    /**
     * @param array $body
     * @param string $amount
     * @param Create $subject
     * @param array $data
     * @param string $orderId
     * @return string|null
     * @throws NoSuchEntityException
     */
    private function processApiResponse(
        array $body,
        string $amount,
        Create $subject,
        array $data,
        string $orderId
    ): ?string {
        if ($body['status'] == 'ACTIVE') {
            if ($amount == $body['amount']['amount']) {
                $message = $this->config->getPayByLinkText(
                    ScopeInterface::SCOPE_STORE,
                    $subject->getQuote()->getStoreId()
                ) . PHP_EOL . $body['url'];

                if (isset($data['send_confirmation']) && $data['send_confirmation']) {
                    if ($data['comment']['customer_note']) {
                        $message .= PHP_EOL . $data['comment']['customer_note'];
                    }
                    $subject->getQuote()->addData(['customer_note' => $message, 'customer_note_notify' => true]);
                } elseif (isset($data['status'])) {
                    $this->paymentLinkService->addCommentToOrder($data['status'], $orderId, $message);
                }
                return $message;
            }
        }
        return null;
    }
}
