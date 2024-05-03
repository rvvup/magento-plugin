#!/bin/bash
echo "Running entrypoint.sh"
set -o errexit
set -o nounset
set -o pipefail
# set -o xtrace # Uncomment this line for debugging purposes

# Load Magento environment
. /opt/bitnami/scripts/magento-env.sh

# Load libraries
. /opt/bitnami/scripts/libbitnami.sh
. /opt/bitnami/scripts/liblog.sh
. /opt/bitnami/scripts/libwebserver.sh

print_welcome_page

info "** Starting Magento setup **"
/opt/bitnami/scripts/"$(web_server_type)"/setup.sh
/opt/bitnami/scripts/php/setup.sh
/opt/bitnami/scripts/mysql-client/setup.sh
/opt/bitnami/scripts/magento/setup.sh
/post-init.sh
info "** Magento setup finished! **"

/rvvup/scripts/setup.sh;
