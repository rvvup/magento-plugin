echo "Running configure-rvvup.sh"

cd /bitnami/magento
composer require rvvup/module-magento-payments:$RVVUP_PLUGIN_VERSION

rm -rf generated/
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush

HEADER=$(echo "$RVVUP_API_KEY" | cut -d '.' -f 1)
PAYLOAD=$(echo "$RVVUP_API_KEY" | cut -d '.' -f 2)
SIGNATURE=$(echo "$RVVUP_API_KEY" | cut -d '.' -f 3)
PAYLOAD_LEN=$((${#PAYLOAD} % 4))
    if [ $PAYLOAD_LEN -eq 2 ]; then
        PAYLOAD="${PAYLOAD}=="
    elif [ $PAYLOAD_LEN -eq 3 ]; then
        PAYLOAD="${PAYLOAD}="
    fi
DECODED_PAYLOAD=$(echo "$PAYLOAD" | base64 --decode)

UPDATED_PAYLOAD=$(echo "$DECODED_PAYLOAD" | jq '.aud = "http://wiremock:8080/graphql"')
ENCODED_PAYLOAD=$(echo -n "$UPDATED_PAYLOAD" | base64 | tr -d '=' | tr '/+' '_-' | tr -d '\n')
RVVUP_KEY_TO_USE="${HEADER}.${ENCODED_PAYLOAD}.test"

bin/magento config:set payment/rvvup/jwt $RVVUP_KEY_TO_USE
bin/magento config:set payment/rvvup/active 1
