echo "Running Rebuild Magento"

cd /bitnami/magento/
rm -rf generated/
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
/rvvup/scripts/fix-perms.sh;