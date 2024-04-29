import test, { expect } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";

test('Can place an order using inline pay by card', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    await page.getByLabel('Pay by Card').click();

    // Credit card form
    await page.frameLocator('.st-card-number-iframe').getByLabel('Card Number').fill('4111 1111 1111 1111');
    await page.frameLocator('.st-expiration-date-iframe').getByLabel('Expiration Date').fill('1233');
    await page.frameLocator('.st-security-code-iframe').getByLabel('Security Code').fill('123');
    await page.getByRole('button', { name: 'Place order' }).click();

    // OTP form
    await page.frameLocator('#Cardinal-CCA-IFrame').getByPlaceholder('Enter Code Here').fill('1234');
    await page.frameLocator('#Cardinal-CCA-IFrame').getByRole('button', { name: 'SUBMIT' }).click();

    await page.waitForURL("./default/checkout/onepage/success/");

    await expect(page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
});

test.skip('Can place an order using modal pay by card', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    await page.getByLabel('Pay by Card').click();

    await page.getByRole('button', { name: 'Place order' }).click();

    // Credit card form
    const frame = page.frameLocator('#rvvup_iframe-rvvup_CARD');
    await frame.frameLocator('.st-card-number-iframe').getByLabel('Card Number').fill('4111 1111 1111 1111');
    await frame.frameLocator('.st-expiration-date-iframe').getByLabel('Expiration Date').fill('1233');
    await frame.frameLocator('.st-security-code-iframe').getByLabel('Security Code').fill('123');
    await frame.getByRole('button', { name: 'Submit'}).click();

    // OTP form
    await frame.frameLocator('#Cardinal-CCA-IFrame').getByPlaceholder('Enter Code Here').fill('1234');
    await frame.frameLocator('#Cardinal-CCA-IFrame').getByPlaceholder('Enter Code Here').press('Enter');

    await page.waitForURL("./default/checkout/onepage/success/");

    await expect(page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
});