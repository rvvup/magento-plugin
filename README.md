# Rvvup Magento Plugin

## End to End Testing
This plugin comes with Playwright tests to ensure it's functionality. 

### Get Started (install dependencies):
```bash
npm i
npx playwright install
```

### To run the tests, use the following command:
```bash
ENV TEST_BASE_URL=https://magento.test npx playwright test --ui # change your base url to point to the right domain
```
