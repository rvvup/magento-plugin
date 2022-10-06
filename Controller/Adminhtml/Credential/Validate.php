<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Credential;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Rvvup\Payments\Model\SdkProxy;

class Validate extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Payment::payment';

    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param Context $context
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        Context $context,
        SdkProxy $sdkProxy
    ) {
        parent::__construct($context);
        $this->sdkProxy = $sdkProxy;
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        /** @var Json $json */
        $json = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        try {
            $status = $this->sdkProxy->ping();
            $json->setHttpResponseCode(200);
            $message = __('Connection to Rvvup successful.');
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
