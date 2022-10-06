<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\SdkProxy;

abstract class AbstractCommand
{
    public const STATE_MAP = [
        'REQUIRES_ACTION' => 'defer',
        'PENDING' => 'defer',
        'SUCCEEDED' => 'success',
        'CANCELLED' => 'decline',
        'DECLINED' => 'decline',
    ];

    /** @var SdkProxy */
    protected $sdkProxy;
    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param SdkProxy $sdkProxy
     * @param LoggerInterface $logger
     */
    public function __construct(
        SdkProxy $sdkProxy,
        LoggerInterface $logger
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
    }

    abstract public function execute(array $commandSubject);
}
