<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Checks;

use Exception;
use Magento\Bundle\Model\Product\Type;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\Checks\SpecificationInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

/**
 * Payment Method Specification Check for Restricted Product.
 *
 * Resolves a payment method as unavailable if a restricted product exists in the quote.
 * This is currently only applicable to Rvvup ClearPay.
 * It can be expanded to any other Rvvup payment method required.
 */
class HasCartRestrictedProduct implements SpecificationInterface
{
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(ProductRepositoryInterface $productRepository, LoggerInterface $logger)
    {
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * This is a Rvvup Specification for payment methods that have product restrictions.
     *
     * @param \Magento\Payment\Model\MethodInterface $paymentMethod
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool|void
     */
    public function isApplicable(MethodInterface $paymentMethod, Quote $quote)
    {
        if ($paymentMethod->getCode() !== 'rvvup_CLEARPAY' &&
            $paymentMethod->getCode() !== 'rvvup_ZOPA_RETAIL_FINANCE') {
            return true;
        }

        // If cart includes payment method restricted products, disable the payment method.
        if ($this->hasCartRestrictedProducts($quote)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether the quote includes a product marked as restricted.
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return bool
     */
    private function hasCartRestrictedProducts(CartInterface $quote): bool
    {
        foreach ($quote->getItems() as $item) {
            try {
                $cartItemSkus = $this->getProductSkusFromCartItem($item);

                // If empty, log it and return restricted. Any errors should already be logged.
                if (empty($cartItemSkus)) {
                    return true;
                }

                foreach ($cartItemSkus as $cartItemProductsSku) {
                    $product = $this->productRepository->get($cartItemProductsSku, $quote->getStoreId());

                    if ((bool) $product->getData('rvvup_restricted')) {
                        return true;
                    }
                }
            } catch (NoSuchEntityException $ex) {
                // Fail-safe, if no product is found, should not happen for new orders.
                $this->logger->error(
                    'Error thrown when checking if a Product is restricted on Rvvup with message: ' . $ex->getMessage(),
                    [
                        'cart_id' => $quote->getId(),
                        'sku' => $item->getSku(),
                        'product_type' => $item->getProductType(),
                        'cart_item_skus' => $cartItemSkus ?? null,
                        'store_id' => $quote->getStoreId()
                    ],
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Get the SKU values for the products that are part of the cart item.
     *
     * If cart item is a bundle product, get the selected options, otherwise the sku of the cart item.
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $cartItem
     * @return string[]
     */
    private function getProductSkusFromCartItem(CartItemInterface $cartItem): array
    {
        $cartItemProductsSkus = [];

        // If not bundle, return as a single entry array.
        if ($cartItem->getProductType() !== Type::TYPE_CODE) {
            $cartItemProductsSkus[] = $cartItem->getSku();

            return $cartItemProductsSkus;
        }

        try {
            foreach ($cartItem->getChildren() as $child) {
                $cartItemProductsSkus[] = $child->getSku();
            }

            return $cartItemProductsSkus;
        } catch (Exception $ex) {
            $this->logger->error(
                'Error thrown when checking if a Bundle\'s children products is restricted on Rvvup with message: '
                . $ex->getMessage(),
                [
                    'sku' => $cartItem->getSku(),
                    'product_type' => $cartItem->getProductType(),
                    'store_id' => $cartItem->getStoreId()
                ]
            );

            return $cartItemProductsSkus;
        }
    }
}
