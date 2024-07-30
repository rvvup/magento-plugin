echo "Running configure-firecheckout.sh"

cd /bitnami/magento
echo "Installing Firecheckout $FIRECHECKOUT_VERSION"
composer require swissup/module-marketplace
bin/magento setup:upgrade
printf "$FIRECHECKOUT_KEY\n" | bin/magento marketplace:channel:enable swissuplabs
bin/magento marketplace:package:require swissup/firecheckout:$FIRECHECKOUT_VERSION
printf '0\nfirecheckout-col3-set\nlight\n' | bin/magento marketplace:package:install swissup/firecheckout
