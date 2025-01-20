echo "Running setup-rvvup.sh"

cd /bitnami/magento
rm -rf generated/
if [ "$RVVUP_PLUGIN_VERSION" == "local" ]; then
    # Run the command for "local"
    echo "Running local version setup..."
    mkdir -p app/code/Rvvup/Payments/
else
    echo "Running setup for version: $RVVUP_PLUGIN_VERSION"
    composer require rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION
fi