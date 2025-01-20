<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;

class Index extends Action implements HttpGetActionInterface
{
    /** @var DirectoryList */
    private $directoryList;
    /** @var File */
    private $fileIo;
    public const ADMIN_RESOURCE = 'Magento_Payment::payment';

    /**
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param File $fileIo
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        File $fileIo
    ) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->fileIo = $fileIo;
    }

    /**
     * @return Raw
     */
    public function execute(): Raw
    {
        /** @var Raw $response */
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);
        try {
            $path = $this->directoryList->getPath('log') . '/rvvup.log';
            $data = $this->fileIo->fileGetContents($path);
            $response->setContents($data);
            $response->setHeader('content-type', 'text/plain');
            $response->setHeader("content-disposition", 'attachment; filename="rvvup.log"');
        } catch (\Exception $e) {
            $response->setContents('Unable to locate log file');
            $response->setHttpResponseCode(400);
        }
        return $response;
    }
}
