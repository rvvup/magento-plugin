echo "Running configure-plugins.sh"

cd /bitnami/magento
bin/magento config:set payment/rvvup/jwt $RVVUP_API_KEY
bin/magento config:set payment/rvvup/active 1
