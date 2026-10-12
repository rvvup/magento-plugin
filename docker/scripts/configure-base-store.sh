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
# crypto.randomUUID is only exposed in secure contexts (https/localhost), but the store is
# served over plain http on a custom host. Trust Payments' JS calls it, so polyfill it ahead
# of every other script via design/head/includes. Served as a same-origin file because the
# store's CSP blocks inline scripts but allows 'self'. Test/local store only, not part of the plugin.
echo "Adding crypto.randomUUID polyfill"
cat > pub/media/crypto-uuid-mock-polyfill.js <<'JS'
if (window.crypto && !crypto.randomUUID && crypto.getRandomValues) {
    crypto.randomUUID = function () {
        return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, function (c) {
            return (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16);
        });
    };
}
JS
chmod 644 pub/media/crypto-uuid-mock-polyfill.js
# design/head/includes isn't in system.xml, so bin/magento config:set rejects it ("path doesn't exist").
mysql -h "$MAGENTO_DATABASE_HOST" -P "$MAGENTO_DATABASE_PORT_NUMBER" -u "$MAGENTO_DATABASE_USER" "$MAGENTO_DATABASE_NAME" -e "
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES
  ('default', 0, 'design/head/includes', '<script src=\"/media/crypto-uuid-mock-polyfill.js\"></script>')
ON DUPLICATE KEY UPDATE value = VALUES(value);"

echo "Configuring SMTP settings to point to $MAGENTO_SMTP_HOST:$MAGENTO_SMTP_PORT"
bin/magento config:set system/smtp/disable 0
bin/magento config:set system/smtp/transport smtp
bin/magento config:set system/smtp/host $MAGENTO_SMTP_HOST
bin/magento config:set system/smtp/port $MAGENTO_SMTP_PORT
echo "Registering Test Users"
bin/magento admin:user:create --admin-user=e2e-tests-refunds --admin-password=password1 --admin-email=e2etestsrefund@rvvup.com --admin-firstname=E2E --admin-lastname=Refunds
bin/magento admin:user:create --admin-user=e2e-tests-partial-refunds --admin-password=password1 --admin-email=e2etestspartialrefunds@rvvup.com --admin-firstname=E2E --admin-lastname=PartialRefunds

bin/magento sampledata:deploy
