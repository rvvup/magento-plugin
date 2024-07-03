echo "Running configure-base-store.sh"

cd /bitnami/magento
composer config repositories.magento composer https://repo.magento.com/
composer config http-basic.repo.magento.com $MAGENTO_REPO_PUBLIC_KEY $MAGENTO_REPO_PRIVATE_KEY
bin/magento config:set currency/options/allow GBP,USD
bin/magento config:set currency/options/base GBP
bin/magento config:set currency/options/default GBP
bin/magento config:set general/locale/timezone Europe/London
bin/magento config:set general/locale/code en_GB
bin/magento config:set carriers/freeshipping/active 1
bin/magento config:set web/secure/use_in_adminhtml 1

echo "Configuring SMTP settings to point to $MAGENTO_SMTP_HOST:$MAGENTO_SMTP_PORT"
bin/magento config:set system/smtp/disable 0
bin/magento config:set system/smtp/transport smtp
bin/magento config:set system/smtp/host $MAGENTO_SMTP_HOST
bin/magento config:set system/smtp/port $MAGENTO_SMTP_PORT

bin/magento sampledata:deploy
