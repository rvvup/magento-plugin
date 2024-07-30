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
/opt/bitnami/scripts/magento/run.sh;
