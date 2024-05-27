<?php
declare(strict_types=1);

namespace Rvvup\Payments\Ui\Component\Listing\Column;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Data extends Column
{
    private const RVVUP_PAYMENTS_IMAGES_GREEN_SVG = 'Rvvup_Payments::images/green.svg';
    private const RVVUP_PAYMENTS_IMAGES_GREY_SVG = 'Rvvup_Payments::images/grey.svg';
    private const RVVUP_PAYMENTS_IMAGES_RED_SVG = 'Rvvup_Payments::images/red.svg';

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var CacheInterface */
    private $cache;

    /** @var Repository */
    private $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CacheInterface $cache
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        CacheInterface $cache,
        Repository $assetRepo,
        RequestInterface $request,
        array $components = [],
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->cache = $cache;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['payment_method'])) {
                    $field = $this->getData('name');
                    $id = $item["entity_id"] . '_' . $field;

                    if ($this->cache->load($id) != null) {
                        $value = $this->cache->load($id);
                        $item[$field . '_src'] = $value;
                        continue;
                    }

                    if (strpos($item['payment_method'], 'rvvup_CARD') === 0) {
                        $order  = $this->orderRepository->get($item["entity_id"]);
                        $payment = $order->getPayment();
                        $value = $payment->getAdditionalInformation($field) ?? '';
                        $value = $this->getImagePath($field, $value);
                        $item[$field . '_src'] = $value;
                        $this->cache->save($value, $id);
                    } else {
                        $this->cache->save('', $id);
                    }
                }
            }
        }
        return $dataSource;
    }

    /**
     * @param string $field
     * @param string $value
     * @return string
     */
    private function getImagePath(string $field, string $value): string
    {
        if ($field === 'rvvup_eci') {
            if ($value == '05' || $value == '02') {
                $value = $this->getViewFileUrl(self::RVVUP_PAYMENTS_IMAGES_GREEN_SVG);
            } else {
                $value = $this->getViewFileUrl(self::RVVUP_PAYMENTS_IMAGES_GREY_SVG);
            }
        } else {
            switch ($value) {
                case 2:
                    $value = $this->getViewFileUrl(self::RVVUP_PAYMENTS_IMAGES_GREEN_SVG);
                    break;
                case 4:
                    $value = $this->getViewFileUrl(self::RVVUP_PAYMENTS_IMAGES_RED_SVG);
                    break;
                case 0:
                case 1:
                default:
                    $value = $this->getViewFileUrl(self::RVVUP_PAYMENTS_IMAGES_GREY_SVG);
            }
        }
        return $value;
    }

    /**
     * @param string $fileId
     * @return string
     */
    public function getViewFileUrl(string $fileId): string
    {
        $params = array_merge(['_secure' => $this->request->isSecure()], []);
        return $this->assetRepo->getUrlWithParams($fileId, $params);
    }
}
