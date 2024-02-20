<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Sales\Block\Adminhtml\Order\Creditmemo\Create\Items;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Cache;

class CanRefund implements ValueHandlerInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /** @var Cache */
    private $cache;

    /** @var Json */
    private $serializer;

    /** @var Items  */
    private $items;

    /**
     * @param SdkProxy $sdkProxy
     * @param Cache $cache
     * @param Json $serializer
     * @param Items $items
     */
    public function __construct(
        SdkProxy $sdkProxy,
        Cache $cache,
        Json $serializer,
        Items $items
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->items = $items;
    }

    /**
     * @param array $subject
     * @param int|null $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null): bool
    {
        try {
            $payment = $subject['payment']->getPayment();

            $orderId = $payment->getAdditionalInformation(Method::ORDER_ID) ?: $payment->getParentId();
            if ($orderId) {
                $invoiceCollection = $payment->getOrder()->getInvoiceCollection();

                foreach ($invoiceCollection->getItems() as $id => $invoice) {
                    if (!$invoice->getTransactionId()) {
                        if ($this->items->getCreditmemo()->getInvoice()) {
                            $invoice->setTransactionId($orderId);
                            $invoiceCollection->removeItemByKey($id);
                            $invoiceCollection->addItem($invoice);
                            $this->items->getCreditmemo()->setInvoice($invoice);
                            $invoiceCollection->save();
                        }
                    }
                }
            }
            $value = $this->cache->get($orderId, 'refund', $payment->getOrder()->getState());
            if ($value) {
                return $this->serializer->unserialize($value)['available'];
            }
            $value = $this->sdkProxy->isOrderRefundable($orderId);
            $data = $this->serializer->serialize(['available' => $value]);
            $this->cache->set($orderId, 'refund', $data, $payment->getOrder()->getState());

            return $value;
        } catch (Exception $e) {
            return false;
        }
    }
}
