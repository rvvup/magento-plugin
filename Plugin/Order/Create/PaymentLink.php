<?php

namespace Rvvup\Payments\Plugin\Order\Create;

use Magento\Backend\Model\Session\Quote;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\PaymentLink as PaymentLinkService;
use Rvvup\Payments\Model\Config;
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

    /** @var Quote */
    private $quoteSession;

    /**
     * @param Config $config
     * @param Http $request
     * @param LoggerInterface $logger
     * @param PaymentLinkService $paymentLinkService
     * @param Quote $quoteSession
     */
    public function __construct(
        Config                             $config,
        Http                               $request,
        LoggerInterface                    $logger,
        PaymentLinkService                 $paymentLinkService,
        Quote                              $quoteSession
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->logger = $logger;
        $this->paymentLinkService = $paymentLinkService;
        $this->quoteSession = $quoteSession;
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
        if ($result->getQuote() && $result->getQuote()->getPayment()->getMethod() == 'rvvup_payment-link') {
            $payment = $subject->getQuote()->getPayment();
            $this->createPaymentLink($payment, $result, $subject, $data);
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
        if (!(isset($subject['send_confirmation']) && $subject['send_confirmation']) ||
            $this->quoteSession->getData('reordered')) {
            $payment = $subject->getQuote()->getPayment();
            if (!$payment->getAdditionalInformation(Method::PAYMENT_LINK_ID)) {
                if ($payment->getMethod() == 'rvvup_payment-link') {
                    if ($this->config->isActive(ScopeInterface::SCOPE_STORE, $result->getStoreId())) {
                        list($id, $message) = $this->createRvvupPayByLink(
                            (int) $result->getStoreId(),
                            $result->getGrandTotal(),
                            $result->getId(),
                            $result->getOrderCurrencyCode(),
                            $subject,
                            ['status' => $result->getStatus()],
                            $result->getIncrementId()
                        );
                        if ($id && $message) {
                            $payment = $this->paymentLinkService->getQuotePaymentByOrder($result);
                            $this->paymentLinkService->savePaymentLink($payment, $id, $message);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param PaymentInterface $payment
     * @param Create $result
     * @param Create $subject
     * @param array $data
     * @return Create|void
     * @throws NoSuchEntityException
     */
    private function createPaymentLink(
        PaymentInterface $payment,
        Create $result,
        Create $subject,
        array $data
    ) {
        if (isset($data['comment'])) {
            if (!$payment->getAdditionalInformation(Method::PAYMENT_LINK_ID)) {
                $quote = $result->getQuote();
                $storeId = (int) $quote->getStore()->getId();
                $amount = (float)$quote->getGrandTotal();
                $orderId = $quote->reserveOrderId()->getReservedOrderId();
                if ($this->quoteSession->getData('reordered')) {
                    return $result;
                }

                $currencyCode = $quote->getQuoteCurrencyCode();
                $order = $this->request->getPost('order');
                if (!isset($order['account']) || !isset($order['send_confirmation'])) {
                    return $result;
                }
                if ($result->getQuote()->getPayment()->getMethod() == 'rvvup_payment-link') {
                    if ($this->config->isActive(ScopeInterface::SCOPE_STORE, (string) $storeId)) {
                        list($id, $message) =
                            $this->createRvvupPayByLink(
                                $storeId,
                                $amount,
                                $orderId,
                                $currencyCode,
                                $subject,
                                $data
                            );
                        if ($id && $message) {
                            $payment = $subject->getQuote()->getPayment();
                            $this->paymentLinkService->savePaymentLink($payment, $id, $message);
                        }
                    }
                }
            } else {
                $quote = $subject->getQuote();
                if ($quote->getPayment()->getMethod() == 'rvvup_payment-link') {
                    $message = $quote->getPayment()->getAdditionalInformation(Method::PAYMENT_LINK_MESSAGE);
                    $quote->addData(['customer_note' => $message, 'customer_note_notify' => true]);
                }
            }
        }
    }

    /**
     * Create Rvvup pay-by-link and save it to comment
     * @param string $storeId
     * @param float $amount
     * @param string $orderId
     * @param string $currencyCode
     * @param Create $subject
     * @param array $data
     * @param string|null $orderIncrementId
     * @return array|null
     */
    private function createRvvupPayByLink(
        int $storeId,
        float $amount,
        string $orderId,
        string $currencyCode,
        Create $subject,
        array $data,
        string $orderIncrementId = null
    ): ?array {
        try {
            $amount = number_format($amount, 2, '.', '');
            if ($amount <= 0) {
                return [null,null];
            }
            $body = $this->paymentLinkService->createPaymentLink(
                $storeId,
                $amount,
                $orderIncrementId ?: $orderId,
                $currencyCode
            );
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
        if (isset($body['status']) && $body['status'] == 'ACTIVE') {
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
