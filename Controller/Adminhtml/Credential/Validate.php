<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Credential;

use GuzzleHttp\Client;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Base64Json;
use Magento\Framework\Serialize\SerializerInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdkFactory;

class Validate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Payment::payment';

    /** @var UserAgentBuilder */
    private $userAgentBuilder;
    /** @var GraphQlSdkFactory */
    private $sdkFactory;
    /** @var Base64Json set via di.xml */
    private $serializer;

    /**
     * @param Context $context
     * @param UserAgentBuilder $userAgentBuilder
     * @param GraphQlSdkFactory $sdkFactory
     * @param Base64Json $serializer set via di.xml
     */
    public function __construct(
        Context $context,
        UserAgentBuilder $userAgentBuilder,
        GraphQlSdkFactory $sdkFactory,
        SerializerInterface $serializer
    ) {
        parent::__construct($context);
        $this->userAgentBuilder = $userAgentBuilder;
        $this->sdkFactory = $sdkFactory;
        $this->serializer = $serializer;
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
            if (count($parts) !== 3) {
                throw new LocalizedException(__('API key is not valid'));
            }
            list($head, $body, $crypto) = $parts;
            $jwt = $this->serializer->unserialize($body);
            $this->validateJwtPayload($jwt);
            $connection = $this->sdkFactory->create([
                'endpoint' => $jwt['aud'],
                'merchantId' => $jwt['merchantId'],
                'authToken' => base64_encode($jwt['username'] . ':' . $jwt['password']),
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

    /**
     * @param mixed $array
     * @return void
     * @throws LocalizedException
     */
    private function validateJwtPayload($array): void
    {
        if (!is_array($array)
            || !isset($array['aud'])
            || !isset($array['merchantId'])
            || !isset($array['username'])
            || !isset($array['password'])
        ) {
            throw new LocalizedException(__('API key is not valid'));
        }
    }
}
