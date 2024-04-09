<?php

namespace Rvvup\Payments\Controller\Express;

use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Rvvup\Payments\Model\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Rvvup\Payments\Api\Data\PaymentActionInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Rvvup\Payments\Api\ExpressPaymentCreateInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Rvvup\Payments\Exception\PaymentValidationException;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Create implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Path Constant.
     */
    public const PATH = 'rvvup/express/create';

    /**
     * @var \Magento\Framework\App\RequestInterface|\Magento\Framework\App\Request\Http
     */
    private $request;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    private $resultFactory;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    private $formKeyValidator;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Checkout\Model\Session\Proxy
     */
    private $checkoutSession;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Customer\Model\Session\Proxy
     */
    private $customerSession;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Rvvup\Payments\Api\ExpressPaymentCreateInterface
     */
    private $expressPaymentCreate;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * The request's required params
     *
     * @var string[]
     */
    private $requestRequiredParams = ['cart_id', 'method_code'];

    /**
     * The requests payload in the JSON Body.
     *
     * @var array|null
     */
    private $requestBody;

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Controller\ResultFactory $resultFactory
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Framework\Session\SessionManagerInterface $checkoutSession
     * @param \Magento\Framework\Session\SessionManagerInterface $customerSession
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Rvvup\Payments\Api\ExpressPaymentCreateInterface $expressPaymentCreate
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        Validator $formKeyValidator,
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        SerializerInterface $serializer,
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        ExpressPaymentCreateInterface $expressPaymentCreate,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->serializer = $serializer;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->expressPaymentCreate = $expressPaymentCreate;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        // First try catch block to validate request.
        try {
            $this->validateRequest();

            $cartId = $this->getRequestCartId();

            /** @var \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote */
            $quote = $this->cartRepository->getActive(
                is_numeric($cartId)
                    ? $cartId
                    : $this->maskedQuoteIdToQuoteId->execute($cartId)
            );

            $this->validateCustomerQuote($quote, $cartId);
        } catch (NotFoundException|NoSuchEntityException $ex) {
            // No logging for these error types.
            return $this->returnFailedResponse();
        }

        // Then perform the Create Express Payment action
        try {
            $paymentActions = $this->expressPaymentCreate->execute(
                $quote->getId(),
                (string) $this->getRequestBody()['method_code']
            );
        } catch (PaymentValidationException|LocalizedException $ex) {
            $this->logger->addError('Error thrown on creating Express Payment',
                [
                    'cause' => $ex->getMessage(),
                    'magento' => ['order_id' => $cartId]
                ]
            );
            return $this->returnFailedResponse();
        }

        // Now clear existing quote (should be destroyed from the session) & replace it with the new one.
        $this->checkoutSession->clearQuote();
        $this->checkoutSession->replaceQuote($quote);

        $data = [];

        foreach ($paymentActions as $paymentAction) {
            $data[] = [
                PaymentActionInterface::TYPE => $paymentAction->getType(),
                PaymentActionInterface::METHOD => $paymentAction->getMethod(),
                PaymentActionInterface::VALUE => $paymentAction->getValue()
            ];
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode(200);
        $result->setData(['success' => true, 'data' => $data, 'error_message' => '']);

        return $result;
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Magento\Framework\App\Request\InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return bool
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $requestBody = $this->getRequestBody();

        // Set the param from the JSON body, so it is validated by core validator
        $request->setParam('form_key', $requestBody['form_key'] ?? null);

        return $this->formKeyValidator->validate($request);
    }

    /**
     * Validate this is an AJAX POST request & all request params are set.
     *
     * We only support AJAX requests on this route for the time being.
     *
     * @return void
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    private function validateRequest(): void
    {
        $requestBody = $this->getRequestBody();

        foreach ($this->requestRequiredParams as $requestRequiredParam) {
            if (!isset($requestBody[$requestRequiredParam])) {
                $this->throwNotFound();
            }
        }

        if (!$this->request->isPost() || !$this->request->isAjax()) {
            $this->throwNotFound();
        }
    }

    /**
     * Get request body. Load if not set.
     *
     * @return array|null
     */
    private function getRequestBody(): ?array
    {
        if (is_array($this->requestBody)) {
            return $this->requestBody;
        }

        try {
            $this->requestBody = $this->serializer->unserialize($this->request->getContent());
        } catch (InvalidArgumentException $ex) {
            $this->logger->error('Failed to decode JSON request with message: ' . $ex->getMessage());
            $this->requestBody = null;
        }

        return $this->requestBody;
    }

    /**
     * Get the cart ID from the request params.
     *
     * @return string
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    private function getRequestCartId(): string
    {
        $requestBody = $this->getRequestBody();

        if (!isset($requestBody['cart_id'])) {
            $this->throwNotFound();
        }

        return (string) $requestBody['cart_id'];
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param string $cartId
     * @return void
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    private function validateCustomerQuote(CartInterface $quote, string $cartId): void
    {
        // If the cart ID is not a numeric value, it should be a Masked Quote ID which are random, so continue.
        if (!is_numeric($cartId)) {
            return;
        }

        // If the quote belongs to the customer of the session, continue.
        if ($quote->getCustomer()->getId() === $this->customerSession->getCustomerId()) {
            return;
        }

        $this->throwNotFound();
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    private function throwNotFound(): void
    {
        throw new NotFoundException(__('Page not found'));
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function returnFailedResponse(): Json
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode(200);
        $result->setData([
            'success' => false,
            'error_message' => 'There was an error when processing your request'
        ]);

        return $result;
    }
}
