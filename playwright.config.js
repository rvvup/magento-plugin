// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// require('dotenv').config();

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
    timeout: process.env.CI ? 90000 : 120000,
    expect: {
        timeout: 20000,
  },

  testDir: './Test/End-2-End',
  /* Run tests in files in parallel locally; run serially in CI. */
  fullyParallel: !process.env.CI,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry to absorb flakes in CI and locally. */
  retries: process.env.CI ? 2 : 3,
  /* Stop the CI run early once 5 tests have failed (after their retries), leaving
     headroom for known environment failures without hiding the rest of the suite. */
  maxFailures: process.env.CI ? 5 : undefined,
  /* Single worker in CI (no parallel workers); parallel locally. */
  workers: process.env.CI ? 1 : 3,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: [['list', {printSteps: true}], ['html', {open: 'never'}]],
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.TEST_BASE_URL,

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    ignoreHTTPSErrors: true,
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    }]
});

