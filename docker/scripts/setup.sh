echo "Running setup.sh"
/rvvup/scripts/configure-base-store.sh;
/rvvup/scripts/setup-rvvup.sh;
if [ -n "$FIRECHECKOUT_KEY" ]; then
    echo "Firecheckout key is present, setting up firecheckout..."
    /rvvup/scripts/setup-firecheckout.sh;
fi

/rvvup/scripts/setup-upgrade.sh;
/rvvup/scripts/configure-plugins.sh;
/rvvup/scripts/fix-perms.sh;
mkdir -p /bitnami/magento/app/code/Rvvup/Payments
echo "echo \"Run file\"" > /rvvup/scripts/configure-base-store.sh
echo "echo \"Run file\"" > /rvvup/scripts/configure-plugins.sh
echo "echo \"Run file\"" > /rvvup/scripts/setup-rvvup.sh
echo "echo \"Run file\"" > /rvvup/scripts/setup-firecheckout.sh
cd /bitnami/magento
sed -i 's/^opcache\.enable *= *1/opcache.enable = 0/' /opt/bitnami/php/etc/php.ini
sed -i 's/^opcache\.enable_cli *= *1/opcache.enable_cli = 0/' /opt/bitnami/php/etc/php.ini
rm -rf var/cache/* var/generation/* var/page_cache/* var/view_preprocessed/* var/di/* pub/static/* generated/*
/opt/bitnami/scripts/magento/run.sh;
