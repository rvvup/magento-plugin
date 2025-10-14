echo "Running setup.sh"
/rvvup/scripts/configure-base-store.sh;
/rvvup/scripts/setup-rvvup.sh;
if [ -n "$FIRECHECKOUT_KEY" ]; then
    echo "Firecheckout key is present, setting up firecheckout..."
    /rvvup/scripts/setup-firecheckout.sh;
fi

if [ "$RVVUP_PLUGIN_VERSION" == "local" ]; then
  cd /bitnami/magento
  # Only run in first attempt, then reset
  echo "echo \"Ignored running base store config\"" > /rvvup/scripts/configure-base-store.sh
  echo "echo \"Ignored running firecheckout setup\"" > /rvvup/scripts/setup-firecheckout.sh
  sed -i '1s/^/RVVUP_PLUGIN_VERSION=local \n/' /rvvup/scripts/fix-perms.sh
  echo "/rvvup/scripts/run-on-local-volume.sh" > /rvvup/scripts/setup-rvvup.sh
fi
/rvvup/scripts/rebuild-magento.sh;
/rvvup/scripts/configure-plugins.sh;
/rvvup/scripts/fix-perms.sh;
/rvvup/scripts/configure-dataset.sh;
/opt/bitnami/scripts/magento/run.sh;
