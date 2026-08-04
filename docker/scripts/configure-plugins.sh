echo "Running configure-plugins.sh"

cd /bitnami/magento
bin/magento config:set payment/rvvup/jwt $RVVUP_API_KEY
bin/magento config:set payment/rvvup/active 1
# Debug mode makes the SDK write every GraphQL request and response to var/log/rvvup.log,
# which the workflow uploads with the run artifacts.
bin/magento config:set payment/rvvup/debug 1
