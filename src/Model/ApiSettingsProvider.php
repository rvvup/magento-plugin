<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

class ApiSettingsProvider extends \Magento\Framework\DataObject
{
    /** @var SdkProxy */
    private $sdk;

    /** @var array */
    private $apiData = [];

    /** @var StorefrontRequestOptions */
    private $requestOptions;

    /**
     * @param SdkProxy $sdk
     * @param array $data
     * @param StorefrontRequestOptions|null $requestOptions
     */
    public function __construct(
        SdkProxy $sdk,
        array $data = [],
        ?StorefrontRequestOptions $requestOptions = null
    ) {
        $this->sdk = $sdk;
        $this->requestOptions = $requestOptions ?? new StorefrontRequestOptions();
        parent::__construct($data);
    }

    /**
     * @param string $method
     * @param string $path
     * @return array|mixed|null
     */
    public function getByPath(string $method, string $path)
    {
        $this->loadSdkData();
        $this->_data = $this->apiData[$method] ?? [];
        return $this->getDataByPath($path);
    }

    /**
     * @return void
     */
    private function loadSdkData(): void
    {
        if (!$this->apiData) {
            $methods = $this->sdk->getMethods('0', 'GBP', $this->requestOptions->get());
            foreach ($methods as $method) {
                if (isset($method['name'])) {
                    $this->apiData[$method['name']] = $method;
                }
            }
        }
    }
}
