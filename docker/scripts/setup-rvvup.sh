echo "Running setup-rvvup.sh"

cd /bitnami/magento
echo "Installing rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION"
composer require rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION
