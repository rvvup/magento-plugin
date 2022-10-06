<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Config\Jwt;

use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\Exception\ValidatorException;

class Validator extends Encrypted
{
    /**
     * If value is not obscured and has a value verify the JWT is valid
     *
     * @return void
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $value = (string) $this->getValue();
        if (!preg_match('/^\*+$/', $value) && !empty($value)) {
            $this->validate($value);
            parent::beforeSave();
            return;
        }
        parent::beforeSave();
    }

    /**
     * @param string $jwt
     * @return void
     * @throws ValidatorException
     */
    private function validate(string $jwt): void
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new ValidatorException(__('API key is invalid'));
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $payloadString = base64_decode($parts[1], true);
        if (false === $payloadString) {
            throw new ValidatorException(__('API key is invalid'));
        }

        try {
            json_decode($payloadString, false, 2, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new ValidatorException(__('API key is invalid'));
        }
    }
}
