echo "Running setup-rvvup.sh"

cd /bitnami/magento
#echo "Installing rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION"
#composer require rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION
mkdir -p /bitnami/magento/app/code/Rvvup/
composer require rvvup/sdk:1.2.3 ext-json
rm -rf generated/