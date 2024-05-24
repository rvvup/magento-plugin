import {expect, test} from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import ClearpayCheckout from "./Components/ClearpayCheckout";
import OrderConfirmation from "./Components/OrderConfirmation";
import GoTo from "./Components/GoTo";
import Cart from "./Components/Cart";

test('Can place a Clearpay order', async ({ page, browser }) => {
    await new VisitCheckoutPayment(page).visitAsClearpayUser();

    await new ClearpayCheckout(page).checkout();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test('Renders the Clearpay widget on the product page', async ({ page }) => {
    await new GoTo(page).product.standard();

    await expect(page.locator('.afterpay-modal-overlay')).toBeHidden();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).toBeVisible();

    await page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'}).click();
    await expect(page.locator('.afterpay-modal-overlay')).toBeVisible();
});

test('Renders the Clearpay widget on the cart page', async ({ page }) => {
    await new Cart(page).addStandardItemToCart();
    await new GoTo(page).product.standard();

    await expect(page.locator('.afterpay-modal-overlay')).toBeHidden();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).toBeVisible();

    await page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'}).click();
    await expect(page.locator('.afterpay-modal-overlay')).toBeVisible();
});

// TODO: Add test back in once we add a Clearpay restricted product to test against
test.skip('Clearpay not available for restricted products', async ({ page }) => {
    await new GoTo(page).product.standard();

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});


// TODO: Add test back in once we add a below price threshold product to test against
test.skip('Clearpay not available for products below price threshold', async ({ page }) => {
    await new GoTo(page).product.standard();

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).not.toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});

// TODO: Add test back in once we add a product above price threshold to test against
test.skip('Clearpay not available for products above price threshold', async ({ page }) => {
    await new GoTo(page).product.standard();

    await expect(page.getByText('This item has restrictions so not all payment methods may be available')).not.toBeVisible();

    await expect(page.getByRole('button', { name: 'Clearpay logo - Opens a dialog'})).not.toBeVisible();
});

test('Clearpay shows correct instalment amounts on product page', async ({ page }) => {
    await new GoTo(page).product.standard();
    await expect(page.getByText('or 4 interest-free payments of £1.75 with')).toBeVisible();
});``
