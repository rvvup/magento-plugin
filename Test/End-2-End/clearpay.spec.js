import { test, expect } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import ClearpayCheckout from "./Components/ClearpayCheckout";
import OrderConfirmation from "./Components/OrderConfirmation";

test('Can place a Clearpay order', async ({ page, browser }) => {
    await new VisitCheckoutPayment(page).visitAsClearpayUser();

    await new ClearpayCheckout(page).checkout();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
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

// TODO: Add test back in once we add a Clearpay restricted product to test against
test.skip('Clearpay not available for restricted products', async ({ page }) => {
    await page.goto('./rvvup-crypto-future.html');

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});


// TODO: Add test back in once we add a below price threshold product to test against
test.skip('Clearpay not available for products below price threshold', async ({ page }) => {
    await page.goto('./demogento-enter-the-metaverse-2.html');

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).not.toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});

// TODO: Add test back in once we add a product above price threshold to test against
test.skip('Clearpay not available for products above price threshold', async ({ page }) => {
    await page.goto('./zing-jump-rope.html');

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).not.toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});

test('Clearpay shows correct instalment amounts on product page', async ({ page }) => {
    await page.goto('./joust-duffle-bag.html');
    await expect(page.getByText('or 4 interest-free payments of £8.50 with')).toBeVisible();
});``
