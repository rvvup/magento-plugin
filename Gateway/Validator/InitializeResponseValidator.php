<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class InitializeResponseValidator extends AbstractValidator
{
    /**
     * Validate the Rvvup ID key exists in the result.
     *
     * The ID should exist either for an orderCreate or an orderExpressUpdate API request.
     * Hence, check if both are missing for validation, regardless the request type.
     * This also validates the relevant orderCreate & orderExpressUpdate keys are arrays.
     *
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);

        $fails = [];

        if (!isset($response['data']['orderCreate']['id']) &&
            !isset($response['data']['orderUpdate']['id'])
        ) {
            if (empty($response)) {
             //@TODO add quote validation

            } else {
                $fails[] = 'Rvvup order ID is not set';
            }
        }

        return $this->createResult(empty($fails), $fails);
    }
}
