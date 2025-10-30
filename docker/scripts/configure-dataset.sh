echo "Running configure-dataset.sh"

cd /bitnami/magento
# Test Products updated for Test Suite Fixtures
# Configurable product with different prices
php /rvvup/scripts/php/update-prices.php MH07-XS-Black 45
php /rvvup/scripts/php/update-prices.php MH07-S-Black 100
php /rvvup/scripts/php/update-prices.php MH07-XL-Black 90000

# Standard product: cheap
php /rvvup/scripts/php/update-prices.php 24-UG06 7

# Standard Product 2: medium-priced
php /rvvup/scripts/php/update-prices.php 24-MB02 150
