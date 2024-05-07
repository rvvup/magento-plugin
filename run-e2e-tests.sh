#!/bin/bash
start=$(date +%s)
docker compose up -d --build
attempt=1
while true; do
    http_status=$(curl -o /dev/null -s -w "%{http_code}\n" -I "http://localhost/magento_version")

    if [ "$http_status" -eq 200 ]; then
        echo -e "\rServer responded with 200 OK / Time taken: $(($(date +%s) - start)) seconds, continuing..."
        break
    else
        echo -ne "\rAttempt $attempt: Waiting for server to be up (Might take a couple of minutes). Current status code: $http_status / Time taken: $(($(date +%s) - start)) seconds"
        attempt=$((attempt + 1))
        sleep 2
    fi
done

if [ "$1" == "--ui" ]; then
    ENV TEST_BASE_URL=http://localhost npx playwright test --ui
else
    ENV TEST_BASE_URL=http://localhost npx playwright test
fi
