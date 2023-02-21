<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Validation;

use Magento\Customer\Model\Address\AbstractAddress;
use Magento\Framework\Validation\ValidationResult;
use Magento\Framework\Validation\ValidationResultFactory;

class IsValidAddress
{
    /**
     * @var \Magento\Framework\Validation\ValidationResultFactory
     */
    private $validationResultFactory;

    /**
     * @param \Magento\Framework\Validation\ValidationResultFactory $validationResultFactory
     * @return void
     */
    public function __construct(ValidationResultFactory $validationResultFactory)
    {
        $this->validationResultFactory = $validationResultFactory;
    }

    /**
     * @param \Magento\Customer\Model\Address\AbstractAddress $address
     * @return \Magento\Framework\Validation\ValidationResult
     */
    public function execute(AbstractAddress $address): ValidationResult
    {
        $validationResult = $address->validate();

        $validationErrors = [];

        if ($validationResult !== true) {
            $validationErrors = [__('Please check the shipping address information.')];
        }

        if (is_array($validationResult)) {
            $validationErrors = array_merge($validationErrors, $validationResult);
        }

        return $this->validationResultFactory->create(['errors' => $validationErrors]);
    }
}
