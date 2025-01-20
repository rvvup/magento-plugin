echo "Running against local volume"
cd /bitnami/magento/

jq '.extra."merge-plugin"."include" += ["app/code/Rvvup/Payments/composer.json"]' composer.json > composer-temp.json && mv composer-temp.json composer.json
jq '.extra."merge-plugin"."merge-dev" = false' composer.json > composer-temp.json && mv composer-temp.json composer.json
composer update -W
/rvvup/scripts/rebuild-magento.sh
bin/magento config:set payment/rvvup/jwt $RVVUP_API_KEY
bin/magento config:set payment/rvvup/active 1
