import { test, expect } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";

test('Can place a PayPal order on checkout', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();
    
    await page.getByLabel('PayPal', { exact: true }).click();

    page.on('popup', async popup => {
        await popup.waitForLoadState();

        await popup.getByPlaceholder('Email address or mobile number').fill('sb-uqeqf29136249@personal.example.com');
        await popup.getByPlaceholder('Password').fill('h5Hc/b8M');

        await popup.getByRole('button', { name: 'Log In' }).click();
        await popup.getByRole('button', { name: 'Complete Purchase' }).click();
    });

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'PayPal' }).click();

    await page.waitForURL("./default/checkout/onepage/success/");

    await expect(page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
});

test('Can place a PayPal order on checkout using debit or credit cards', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    await page.getByLabel('PayPal', { exact: true }).click();

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'Debit or Credit Card' }).click();

    const paypalCardForm = page.frameLocator("[title='paypal_card_form']");
    await paypalCardForm.getByLabel('Card number').fill('4698 4665 2050 8153')
    await paypalCardForm.getByLabel('Expires').fill('1125')
    await paypalCardForm.getByLabel('Security code').fill('141')
    await paypalCardForm.getByLabel('Mobile').fill('1234567890')
    await paypalCardForm.getByRole('button', { name: 'Buy Now' }).click();

    await page.waitForURL("./default/checkout/onepage/success/");

    await expect(page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
});

test('Can place a PayPal express order', async ({ page }) => {
    page.on('popup', async popup => {
        await popup.waitForLoadState();
        await popup.getByPlaceholder('Email address or mobile number').fill('sb-uqeqf29136249@personal.example.com');
        await popup.getByRole('button', { name: 'Next' }).click();

        await popup.getByPlaceholder('Password').fill('h5Hc/b8M');
        await popup.getByRole('button', { name: 'Log In' }).click();

        await popup.getByRole('button', { name: 'Continue to Review Order' }).click();
    });

    // Product page
    page.goto('./demogento-enter-the-metaverse-2.html');

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'PayPal' }).click();

    // Shipping page
    await page.getByLabel('Phone number').fill('+447500000000');
    await page.getByLabel('Free').click();
    await page.getByRole('button', { name: 'Next' }).click();

    // Checkout page
    await expect(page.getByText('Payment Method', { exact: true })).toBeVisible();
    
    // TODO: Check that only PayPal is an option on checkout
    // const children = await page.$$('#payment-methods ol > li');
    // await expect(children.length).toBe(1);

    await page.getByRole('button', { name: 'Place order' }).click();

    await page.waitForURL("./default/checkout/onepage/success/");

    await expect(page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
});

test('Cannot place a PayPal express order if shipping cost is added later', async ({ page }) => {
    page.on('popup', async popup => {
        await popup.waitForLoadState();
        await popup.getByPlaceholder('Email address or mobile number').fill('sb-uqeqf29136249@personal.example.com');
        await popup.getByRole('button', { name: 'Next' }).click();

        await popup.getByPlaceholder('Password').fill('h5Hc/b8M');
        await popup.getByRole('button', { name: 'Log In' }).click();

        await popup.getByRole('button', { name: 'Continue to Review Order' }).click();
    });

    // Product page
    page.goto('./demogento-enter-the-metaverse-2.html');

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'PayPal' }).click();

    // Shipping page
    await page.getByLabel('Phone number').fill('+447500000000');
    await page.getByLabel('Fixed').click();
    await page.getByRole('button', { name: 'Next' }).click();

    // Checkout page
    await expect(page.getByText('Payment Method', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Place order' }).click();

    await expect(page.getByText('Payment Failed')).toBeVisible();
});