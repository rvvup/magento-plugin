import { test, expect } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";

test('Can place a Clearpay order', async ({ page, browser }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visitAsClearpayUser();

    await page.getByLabel('Clearpay').click();

    await page.getByRole('button', { name: 'Place order' }).click();

    const clearpayFrame = page.frameLocator('#rvvup_iframe-rvvup_CLEARPAY');
    await frame.getByRole('button', { name: 'Accept All'}).click();

    await clearpayFrame.getByTestId('login-password-input').fill('XHvZsaUWh6K-BPWgXY!NJBwG');
    await clearpayFrame.getByRole('button', { name: 'Continue'}).click();
    await clearpayFrame.getByRole('button', { name: 'Confirm'}).click();

    await page.waitForURL("./default/checkout/onepage/success/");

    await expect(page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
});

test('Renders the Clearpay widget on the product page', async ({ page }) => {
    await page.goto('./joust-duffle-bag.html');

    await expect(page.locator('.afterpay-modal-overlay')).toBeHidden();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).toBeVisible();

    await page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'}).click();
    await expect(page.locator('.afterpay-modal-overlay')).toBeVisible();
});

test('Renders the Clearpay widget on the cart page', async ({ page }) => {
    await page.goto('./joust-duffle-bag.html');
    await page.getByRole("button", {name: "Add to cart"}).click();
    await expect(page.getByText(/You added [A-Za-z0-9 ]+ to your shopping cart/i)).toBeVisible();

    await page.goto('./checkout/cart');

    await expect(page.locator('.afterpay-modal-overlay')).toBeHidden();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).toBeVisible();

    await page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'}).click();
    await expect(page.locator('.afterpay-modal-overlay')).toBeVisible();
});

test('Clearpay not available for restricted products', async ({ page }) => {
    await page.goto('./rvvup-crypto-future.html');

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});


test('Clearpay not available for products below price threshold', async ({ page }) => {
    await page.goto('./demogento-enter-the-metaverse-2.html');
    
    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).not.toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});

test('Clearpay not available for products above price threshold', async ({ page }) => {
    await page.goto('./zing-jump-rope.html');
    
    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).not.toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});