<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Credential;

use GuzzleHttp\Client;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdkFactory;

class Validate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Payment::payment';

    /** @var UserAgentBuilder */
    private $userAgentBuilder;
    /** @var GraphQlSdkFactory */
    private $sdkFactory;

    /**
     * @param Context $context
     * @param UserAgentBuilder $userAgentBuilder
     * @param GraphQlSdkFactory $sdkFactory
     */
    public function __construct(
        Context $context,
        UserAgentBuilder $userAgentBuilder,
        GraphQlSdkFactory $sdkFactory
    ) {
        parent::__construct($context);
        $this->userAgentBuilder = $userAgentBuilder;
        $this->sdkFactory = $sdkFactory;
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        /** @var Json $json */
        $json = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $jwtString = $this->getRequest()->getParam('jwt');
        try {
            $parts = explode('.', $jwtString);
            list($head, $body, $crypto) = $parts;
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $jwt = json_decode(base64_decode($body));
            $connection = $this->sdkFactory->create([
                'endpoint' => $jwt->aud,
                'merchantId' => $jwt->merchantId,
                'authToken' => base64_encode($jwt->username . ':' . $jwt->password),
                'userAgent' => $this->userAgentBuilder->get(),
                'debug' => false,
                'adapter' => (new Client()),
            ]);
            $status = $connection->ping();
            $json->setHttpResponseCode(200);
            $message = __('Connection to Rvvup successful. Don\'t forget to click save!');
        } catch (\Exception $e) {
            $status = false;
            $json->setHttpResponseCode(400);
            if ($e->getCode() === 401) {
                $message = __('Error communicating with Rvvup: Invalid credentials');
            } else {
                $message = __('Error communicating with Rvvup: ' . $e->getMessage());
            }
        }
        $json->setData([
            'status' => $status,
            'message' => $message,
        ]);
        return $json;
    }
}
