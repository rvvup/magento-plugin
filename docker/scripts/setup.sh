echo "Running setup.sh"
/rvvup/scripts/configure-base-store.sh;
/rvvup/scripts/configure-rvvup.sh;
/rvvup/scripts/fix-perms.sh;
/opt/bitnami/scripts/magento/run.sh;
