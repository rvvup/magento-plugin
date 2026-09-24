ARG MAGENTO_VERSION=2
FROM docker.io/bitnamilegacy/magento-archived:${MAGENTO_VERSION}
COPY ./docker/scripts /rvvup/scripts
RUN apt-get update && apt-get install -y \
    unzip \
    vim \
    jq \
    git \
    && rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["/rvvup/scripts/entrypoint.sh"]
