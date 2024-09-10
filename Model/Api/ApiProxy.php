<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model\Api;

use Rvvup\Sdk\GraphQlSdk;

class ApiProxy
{
    private GraphQlSdk $sdk;
    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    /**
     * @throws \Exception
     */
    public function getOrder($orderId) {
        return $this->sdk->getOrder($orderId);
    }

}
