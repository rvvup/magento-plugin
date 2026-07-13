#!/bin/bash
set -euo pipefail

URL="${1:-http://local.dev.rvvuptech.com/magento_version}"
TIMEOUT="${2:-500}"

start=$(date +%s)
attempt=1
spinner="⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏"

print_red() {
    echo -e "\033[31m$1\033[0m"
}

print_green() {
    echo -e "\033[32m$1\033[0m"
}

pretty_loader() {
    local spinner_index=$((attempt % ${#spinner}))
    printf "\033[33m%s\033[0m" "${spinner:$spinner_index:1}"
}

while true; do
    elapsed=$(( $(date +%s) - start ))

    if [ "$elapsed" -ge "$TIMEOUT" ]; then
        print_red "\r✖ TIMEOUT! Server did not respond within ${TIMEOUT} seconds."
        exit 1
    fi

    http_status=$(curl -o /dev/null -s -w "%{http_code}\n" -I "$URL" || echo "000")

    if [ "$http_status" = "200" ]; then
        print_green "\r✔ Server Ready. Time taken: ${elapsed} seconds."
        break
    elif [ "$http_status" = "000" ]; then
        echo -ne "\r$(pretty_loader) $(pretty_loader) \033[90mWaiting for server to be up (Might take a couple of minutes). Current Status: $http_status / Time taken: ${elapsed} seconds.\033[0m"
    elif [ "$http_status" -gt 299 ]; then
        print_red "\r✖ ERROR! Server responded with $http_status. Time taken: ${elapsed} seconds."
        exit 1
    else
        echo -ne "\r$(pretty_loader) $(pretty_loader) \033[90mWaiting for server to be up (Might take a couple of minutes). Current Status: $http_status / Time taken: ${elapsed} seconds.\033[0m"
    fi

    attempt=$((attempt + 1))
    sleep 1
done
