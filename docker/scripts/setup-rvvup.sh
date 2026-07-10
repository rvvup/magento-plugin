echo "Running setup-rvvup.sh"

cd /bitnami/magento
rm -rf generated/
if [ "$RVVUP_PLUGIN_VERSION" == "local" ]; then
    # Run the command for "local"
    echo "Running local version setup..."
    mkdir -p app/code/Rvvup/Payments/
elif [ "$RVVUP_PLUGIN_VERSION" == "checkout" ]; then
    # Install the plugin source that is mounted from the checked out repository.
    # Zip extraction of the plugin dependencies fails deterministically on the
    # 2.4.6/2.4.7 images, so fall back to git based source installs when it does.
    echo "Installing the plugin from the checked out source..."
    # Exclude the plugin from packagist so the dependency version audit plugin finds no
    # public counterpart to flag as dependency confusion, and install the plugin
    # dependencies from source because zip extraction fails on the 2.4.6/2.4.7 images.
    composer config repositories.packagist.org '{"type": "composer", "url": "https://repo.packagist.org", "exclude": ["rvvup/module-magento-payments"]}'
    composer config preferred-install.rvvup/sdk source
    composer config preferred-install.rvvup/rvvup-php-openapi source
    composer config repositories.rvvup-checkout '{"type": "path", "url": "/rvvup/local-plugin", "options": {"symlink": false, "versions": {"rvvup/module-magento-payments": "dev-checkout"}}}'
    if ! composer require rvvup/module-magento-payments:dev-checkout; then
        echo "Composer install of the plugin failed, retrying with source installs..."
        composer require rvvup/module-magento-payments:dev-checkout --prefer-source
    fi
else
    echo "Running setup for version: $RVVUP_PLUGIN_VERSION"
    composer require rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION
fi