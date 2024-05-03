echo "Running configure-base-store.sh"

cd /bitnami/magento
composer config repositories.magento composer https://repo.magento.com/
composer config http-basic.repo.magento.com $MAGENTO_REPO_PUBLIC_KEY $MAGENTO_REPO_PRIVATE_KEY
bin/magento config:set currency/options/allow GBP,USD
bin/magento config:set currency/options/base GBP
bin/magento config:set currency/options/default GBP
bin/magento config:set general/locale/timezone Europe/London
bin/magento config:set general/locale/code en_GB
bin/magento sampledata:deploy
