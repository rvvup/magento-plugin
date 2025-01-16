#!/bin/bash
set -e
BASE_DIR=$(dirname "$(realpath "$0")")
docker compose up -d --build
$BASE_DIR/helpers/wait-for-server-startup.sh

if [ "$1" == "--ui" ]; then
    ENV TEST_BASE_URL=http://local.dev.rvvuptech.com npx playwright test --ui
else
    ENV TEST_BASE_URL=http://local.dev.rvvuptech.com npx playwright test
fi
