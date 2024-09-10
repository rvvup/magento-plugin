<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model\Api\Rvvup;

use Rvvup\Sdk\GraphQlSdk;

class ApiProxy
{
    /** @var GraphQlSdk */
    private $sdk;

    /**
     * @param GraphQlSdk $sdk
     */
    public function __construct(GraphQlSdk $sdk)
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
