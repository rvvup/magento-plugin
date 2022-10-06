<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use stdClass;

class Config implements ConfigInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \stdClass
     */
    private $jwt;

    private const ACTIVE = 'active';
    private const DEBUG = 'debug';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @return void
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Validate whether Rvvup module & payment methods are active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if (!(bool) $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::ACTIVE)) {
            return false;
        }
        $jwt = $this->scopeConfig->getValue(self::RVVUP_CONFIG . 'jwt');
        if (!is_string($jwt)) {
            return false;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        list($head, $body, $crypto) = $parts;
        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $this->jwt = json_decode(base64_decode($body), false, 2, JSON_THROW_ON_ERROR);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the endpoint URL.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return (string) $this->getJwt()->aud;
    }

    /**
     * Get the Merchant ID.
     *
     * @return string
     */
    public function getMerchantId(): string
    {
        return (string) $this->getJwt()->merchantId;
    }

    /**
     * Get the Authorization Token.
     *
     * @return string
     */
    public function getAuthToken(): string
    {
        $jwt = $this->getJwt();
        return base64_encode($jwt->username . ':' . $jwt->password);
    }

    /**
     * Check whether debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::DEBUG);
    }

    private function getJwt(): stdClass
    {
        if (!$this->jwt) {
            $jwt = $this->scopeConfig->getValue(self::RVVUP_CONFIG . 'jwt');
            $parts = explode('.', $jwt);
            list($head, $body, $crypto) = $parts;
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $this->jwt = json_decode(base64_decode($body));
        }
        return $this->jwt;
    }
}
