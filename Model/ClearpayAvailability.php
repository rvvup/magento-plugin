<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Rvvup\Payments\Api\ClearpayAvailabilityInterface;

class ClearpayAvailability implements ClearpayAvailabilityInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        SdkProxy $sdkProxy
    ) {
        $this->sdkProxy = $sdkProxy;
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        $methods = $this->sdkProxy->getMethods('0', 'GBP');
        foreach ($methods as $method) {
            if ($method['name'] === 'CLEARPAY') {
                return true;
            }
        }
        return false;
    }
}
