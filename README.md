# Rvvup Magento Plugin

## Dockerized Setup of Test Store

If you would like to have a quick local installation of the plugin on a magento store (for testing), you can follow these steps:

- Copy .env.sample to .env and update the values as needed.
- Run the following command to start the docker containers:
```
scripts/local-run.sh
```

- The magento store, once it has completed start up, will be available at https://local.dev.rvvuptech.com/

## End to End Testing

This plugin comes with Playwright tests to ensure it's functionality. The tests rely on sample data provided by magento.

### Get Started (install dependencies):
```bash
npm i
npx playwright install
```

### (Recommended), Running the E2E tests against a dockerized store installation

This will spin up a docker container with magento installation + rvvup plugin installed and run the test against this
container.
```bash
./scripts/run-e2e-tests.sh
```

### If you have an existing store, to run the tests, use the following command:

```bash
ENV TEST_BASE_URL=https://magento.test npx playwright test --ui # change your base url to point to the right domain
```
