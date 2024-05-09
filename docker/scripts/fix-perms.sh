cd /bitnami/magento
mkdir -p var/cache
mkdir -p var/log
find var vendor pub/static pub/media app/etc generated var/log var/cache \( -type f -or -type d \) -exec chmod u+w {} +;
chown -R daemon:daemon ./
