<?php

use Magento\Framework\App\Bootstrap;
use Magento\Framework\Exception\LocalizedException;

require "app/bootstrap.php";
if ($argc < 3) {
    echo "Usage: php update-price.php <SKU> <price>\n";
    exit(1);
}

$sku = $argv[1];
$newPrice = (float)$argv[2];

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$appState = $obj->get("Magento\Framework\App\State");
try {
    $appState->setAreaCode('adminhtml');
} catch (LocalizedException $e) {
    // ignore if already set
}
try {
    $productRepository = $obj->get("Magento\Catalog\Api\ProductRepositoryInterface");
    $product = $productRepository->get($sku);
    $product->setPrice($newPrice);
    $productRepository->save($product);
    echo "✅ Updated price for {$product->getSku()} to {$product->getPrice()}\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
