<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Rvvup\Payments\Api\Data\HashInterfaceFactory;
use Rvvup\Payments\Api\HashRepositoryInterface;

class Hash
{
    /** @var Payment */
    private $paymentResource;

    /** @var HashRepositoryInterface */
    private $hashRepository;

    /** @var HashInterfaceFactory */
    private $hashFactory;

    /**
     * @param Payment $paymentResource
     * @param HashRepositoryInterface $hashRepository
     * @param HashInterfaceFactory $hashFactory
     */
    public function __construct(
        Payment $paymentResource,
        HashRepositoryInterface $hashRepository,
        HashInterfaceFactory $hashFactory
    ) {
        $this->paymentResource = $paymentResource;
        $this->hashRepository = $hashRepository;
        $this->hashFactory = $hashFactory;
    }

    /**
     * Save current quote state to hash
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function saveQuoteHash(Quote $quote): void
    {
        $payment = $quote->getPayment();
        list($data, $hash) = $this->getHashForData($quote, true);
        $hashItem = $this->hashFactory->create(['data' => ['hash' => $hash, 'raw_data' => $data]]);
        $this->hashRepository->save($hashItem);
        $payment->setAdditionalInformation('rvvup_quote_hash_v2', $hash);
        $this->paymentResource->save($payment);
    }

    /**
     * Create hash for current quote state
     * @param Quote $quote
     * @param bool $sort
     * @return array
     */
    public function getHashForData(Quote $quote, bool $sort = false): array
    {
        $hashedValues = [];
        foreach ($quote->getTotals() as $total) {
            $hashedValues[$total->getCode()] = $total->getValue();
        }
        $items = $quote->getItems() ?: $quote->getItemsCollection()->getItems();
        foreach ($items as $item) {
            $hashedValues[$item->getSku()] = $item->getQty();
        }

        if ($sort) {
            ksort($hashedValues);
        }
        $output = implode(
            ', ',
            array_map(
                function ($v, $k) {
                    return sprintf("%s='%s'", $k, $v);
                },
                $hashedValues,
                array_keys($hashedValues)
            )
        );

        return [$output, hash('sha256', $output)];
    }
}
