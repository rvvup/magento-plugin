# Rvvup Magento Plugin

## End to End Testing
This plugin comes with Playwright tests to ensure its functionality. To run the tests, use the following command:

```bash
npm ci # Install the required dependencies
ENV TEST_BASE_URL=https://magento.test npx playwright test --ui # change your base url to point to the right domain
```
