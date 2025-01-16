#!/bin/bash
set -x
CURRENT_DIR_NAME=$(basename "$PWD")
docker exec -it -w "/bitnami/magento" "${CURRENT_DIR_NAME}-magento-1" /bin/bash
