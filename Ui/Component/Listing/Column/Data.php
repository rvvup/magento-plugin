<?php
declare(strict_types=1);

namespace Rvvup\Payments\Ui\Component\Listing\Column;

use Magento\Framework\App\CacheInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Data extends Column
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CacheInterface $cache
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        CacheInterface $cache,
        array $components = [],
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->cache = $cache;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['payment_method'])) {
                    $id = $item["entity_id"] . '_' . $this->getData('name');

                    if ($this->cache->load($id) != null) {
                        $item[$this->getData('name')] = $this->cache->load($id);
                        continue;
                    }

                    if (strpos($item['payment_method'], 'rvvup_YAPILY') == 0) {
                        $order  = $this->orderRepository->get($item["entity_id"]);
                        $payment = $order->getPayment();
                        $value = $payment->getAdditionalInformation($this->getData('name')) ?? '';
                        $item[$this->getData('name')] = $value;
                        $this->cache->save($value, $id);
                    } else {
                        $this->cache->save('', $id);
                    }
                }
            }
        }
        return $dataSource;
    }
}
