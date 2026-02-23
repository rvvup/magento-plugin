#!/bin/bash
set -e
BASE_DIR=$(dirname "$(realpath "$0")")
HOST="local.dev.rvvuptech.com"
CURRENT_DIR_NAME=$(basename "$PWD")
if [ "$1" = "rebuild" ] || ! docker image inspect magento-store:latest > /dev/null 2>&1; then
  # Ideally, this commit is pushed to docker hub and we don't rebuild everytime, but for now we rebuild temporarily.
  docker compose down -v || true
  docker image rm $CURRENT_DIR_NAME-magento:latest || true
  RVVUP_PLUGIN_VERSION='local' docker compose up -d
  $BASE_DIR/helpers/wait-for-server-startup.sh

#  echo "Commiting base image"
#  docker commit $CURRENT_DIR_NAME-magento-1 magento-store:latest
#  echo "Restarting server with volume attached"
else
  echo -e "\033[33mSkipping rebuild of base magento store image, run with \`./scripts/local-run.sh rebuild\` to rebuild base image.\033[0m"
fi
#if docker ps -a --format "{{.Names}}" | grep -q "^$CURRENT_DIR_NAME-magento-1$"; then
#  echo "Deleting container: $CURRENT_DIR_NAME-magento-1"
#  docker rm -f "$CURRENT_DIR_NAME-magento-1"
#  echo "Container $CURRENT_DIR_NAME-magento-1 deleted successfully."
#fi
#RVVUP_PLUGIN_VERSION='local' docker compose -f docker-compose.local.yml up -d
#
#$BASE_DIR/helpers/wait-for-server-startup.sh
#echo -e "\033[32mSuccessfully started up server on http://$HOST\033[0m"
#open http://$HOST
