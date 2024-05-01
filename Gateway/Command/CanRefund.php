<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\App\Emulation;
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

    /** @var Emulation */
    private $emulation;

    /**
     * @param SdkProxy $sdkProxy
     * @param Cache $cache
     * @param Json $serializer
     * @param Items $items
     * @param Emulation $emulation
     */
    public function __construct(
        SdkProxy $sdkProxy,
        Cache $cache,
        Json $serializer,
        Items $items,
        Emulation $emulation
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->items = $items;
        $this->emulation = $emulation;
    }

    /**
     * @param array $subject
     * @param int|null $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null): bool
    {
        try {
            if ($storeId) {
                $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_ADMINHTML);
            }

            $payment = $subject['payment']->getPayment();

            $disabledMethods = ['rvvup_payment-link','rvvup_virtual-terminal'];

            if (in_array($payment->getMethod(), $disabledMethods)) {
                return false;
            }

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
