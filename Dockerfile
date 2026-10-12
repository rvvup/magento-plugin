ARG MAGENTO_VERSION=2
FROM docker.io/bitnamilegacy/magento-archived:${MAGENTO_VERSION}
COPY ./docker/scripts /rvvup/scripts
# Debian 11 (bullseye) is EOL and its packages have moved to archive.debian.org
RUN if grep -q 'VERSION_CODENAME=bullseye' /etc/os-release; then \
        sed -i 's|deb.debian.org|archive.debian.org|; s|security.debian.org/\?|archive.debian.org/debian-security |' /etc/apt/sources.list; \
    fi \
    && apt-get update && apt-get install -y \
    unzip \
    vim \
    jq \
    && rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["/rvvup/scripts/entrypoint.sh"]
