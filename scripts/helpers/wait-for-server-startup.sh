#!/bin/bash
start=$(date +%s)
attempt=1
spinner="⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏"

print_red() {
    echo -e "\033[31m$1\033[0m" # Red text
}

print_green() {
    echo -e "\033[32m$1\033[0m" # Green text
}

pretty_loader() {
    local spinner_index=$((attempt % ${#spinner}))
    printf "\033[33m%s\033[0m" "${spinner:$spinner_index:1}"
}

while true; do
    http_status=$(curl -o /dev/null -s -w "%{http_code}\n" -I "http://local.dev.rvvuptech.com/magento_version")

    if [ "$http_status" -eq 200 ]; then
        print_green "\r✔ Server Ready. Time taken: $(($(date +%s) - start)) seconds."
        break
    fi

    if [ "$http_status" -gt 299 ]; then
          print_red "\r✖ ERROR! Server responded with $http_status. Time taken: $(($(date +%s) - start)) seconds."
          break;
    else
          echo -ne "\r$(pretty_loader) $(pretty_loader) \033[90mWaiting for server to be up (Might take a couple of minutes). Current Status: $http_status / Time taken: $(($(date +%s) - start)) seconds.\033[0m"
    fi

    attempt=$((attempt + 1))
    sleep 1
done
