#!/bin/bash
set -x
BASE_DIR=$(dirname "$(realpath "$0")")

$BASE_DIR/local-cmd.sh "/rvvup/scripts/rebuild-magento.sh"
