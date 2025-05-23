echo "Running configure-base-store.sh"

cd /bitnami/magento
composer config repositories.magento composer https://repo.magento.com/
composer config http-basic.repo.magento.com $MAGENTO_REPO_PUBLIC_KEY $MAGENTO_REPO_PRIVATE_KEY
bin/magento config:set currency/options/allow GBP,USD
bin/magento config:set currency/options/base GBP
bin/magento config:set currency/options/default GBP
bin/magento config:set general/country/optional_zip_countries HK
bin/magento config:set general/locale/timezone Europe/London
bin/magento config:set general/country/default GB
bin/magento config:set general/locale/code en_GB
bin/magento config:set carriers/freeshipping/active 1
bin/magento config:set web/secure/use_in_adminhtml 1

if [ "$RVVUP_PLUGIN_VERSION" == "local" ]; then
  bin/magento deploy:mode:set developer
  # Disable opcache
  sed -i 's/^opcache\.enable *= *1/opcache.enable = 0/' /opt/bitnami/php/etc/php.ini
  sed -i 's/^opcache\.enable_cli *= *1/opcache.enable_cli = 0/' /opt/bitnami/php/etc/php.ini

  composer config allow-plugins.wikimedia/composer-merge-plugin true
  composer require wikimedia/composer-merge-plugin
fi
echo "Configuring SMTP settings to point to $MAGENTO_SMTP_HOST:$MAGENTO_SMTP_PORT"
bin/magento config:set system/smtp/disable 0
bin/magento config:set system/smtp/transport smtp
bin/magento config:set system/smtp/host $MAGENTO_SMTP_HOST
bin/magento config:set system/smtp/port $MAGENTO_SMTP_PORT
echo "Registering Test Users"
bin/magento admin:user:create --admin-user=e2e-tests-refunds --admin-password=password1 --admin-email=e2etestsrefund@rvvup.com --admin-firstname=E2E --admin-lastname=Refunds
bin/magento admin:user:create --admin-user=e2e-tests-partial-refunds --admin-password=password1 --admin-email=e2etestspartialrefunds@rvvup.com --admin-firstname=E2E --admin-lastname=PartialRefunds

bin/magento sampledata:deploy
