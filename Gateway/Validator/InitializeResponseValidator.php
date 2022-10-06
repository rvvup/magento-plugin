<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class InitializeResponseValidator extends AbstractValidator
{
    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);

        $fails = [];

        if (!isset($response['data']['orderCreate']['id'])) {
            $fails[] = 'Rvvup order ID is not set';
        }

        return $this->createResult(empty($fails), $fails);
    }
}
