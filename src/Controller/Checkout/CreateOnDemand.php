<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\Checkout;

use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Api\Model\ApplicationSource;
use Rvvup\Api\Model\CheckoutCreateInput;
use Rvvup\Payments\Service\ApiProvider;

/**
 * Creates a Rvvup checkout on demand (AJAX endpoint for PDP Apple Pay express checkout).
 *
 * Returns { success: true, data: { token, id } } on success.
 */
class CreateOnDemand implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const PATH = 'rvvup/checkout/createOnDemand';

    /** @var RequestInterface */
    private $request;

    /** @var ResultFactory */
    private $resultFactory;

    /** @var Validator */
    private $formKeyValidator;

    /** @var SerializerInterface */
    private $serializer;

    /** @var ApiProvider */
    private $apiProvider;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var array|null */
    private $requestBody;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        Validator $formKeyValidator,
        SerializerInterface $serializer,
        ApiProvider $apiProvider,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->serializer = $serializer;
        $this->apiProvider = $apiProvider;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        if (!$this->request->isPost() || !$this->request->isAjax()) {
            return $this->returnErrorResponse('Invalid request');
        }

        try {
            $storeId = (string) $this->storeManager->getStore()->getId();
            $checkoutInput = (new CheckoutCreateInput())->setSource(ApplicationSource::MAGENTO_CHECKOUT);

            try {
                $checkoutInput->setMetadata([
                    "domain" => $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true)
                ]);
            } catch (Exception $e) {
                $this->logger->error('Ignoring error getting base url: ' . $e->getMessage());
            }

            $result = $this->apiProvider->getSdk($storeId)->checkouts()->create($checkoutInput, null);

            if (!$result->getId() || !$result->getToken()) {
                return $this->returnErrorResponse('Failed to create checkout');
            }

            /** @var Json $response */
            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $response->setHttpResponseCode(200);
            $response->setData([
                'success' => true,
                'data' => [
                    'token' => $result->getToken(),
                    'id' => $result->getId(),
                ]
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error creating on-demand checkout: ' . $e->getMessage());
            return $this->returnErrorResponse('Failed to create checkout');
        }
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $requestBody = $this->getRequestBody();
        $request->setParam('form_key', $requestBody['form_key'] ?? null);

        return $this->formKeyValidator->validate($request);
    }

    /**
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
     * @param string $message
     * @return Json
     */
    private function returnErrorResponse(string $message): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode(200);
        $result->setData([
            'success' => false,
            'error_message' => $message
        ]);

        return $result;
    }
}
