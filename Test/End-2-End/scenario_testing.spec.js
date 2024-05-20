import {expect, test} from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import RvvupMethodCheckout from "./Components/RvvupMethodCheckout";
import Cart from "./Components/Cart";
import PayByBankCheckout from './Components/PayByBankCheckout';

test('Can place an order using different billing and shipping address', async ({ page, browser }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();
    await page.getByLabel('Rvvup Payment Method').click();
    await page.locator('#billing-address-same-as-shipping-rvvup_FAKE_PAYMENT_METHOD').setChecked(false);

    const billingForm = page.locator('#payment_method_rvvup_FAKE_PAYMENT_METHOD');
    if (await page.getByRole('button', {name: 'Edit', exact: true}).isVisible()) {
        await page.getByRole('button', {name: 'Edit', exact: true}).click();
    }
    await billingForm.getByLabel('First Name').fill('Liam');
    await billingForm.getByLabel('Last Name').fill('Fox');
    await billingForm.getByLabel('Street Address: Line 1').fill('123 Small St');
    await billingForm.getByLabel('City').fill('Derby');
    await billingForm.getByLabel('Country').selectOption('United Kingdom');
    await billingForm.getByLabel('ZIP').fill('SW1B 1BB');
    await billingForm.getByLabel('Phone number').fill('+447599999999');
    await page.getByRole('button', { name: 'Update' }).click();
    await billingForm.getByLabel('First Name').isHidden();
    await billingForm.getByText("Liam Fox").isVisible();
    const rvvupMethodCheckout = new RvvupMethodCheckout(page);
    await rvvupMethodCheckout.checkout();
});

test.fail('Cannot spam the place order button to create duplicate orders', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    await page.getByLabel('Rvvup Payment Method').click();
    const button = page.getByRole('button', { name: 'Place order' });

    // TODO: If you programmatically change the z-index of the checkout modal,
    // you can bring the Place Order button into view and click it multiple times.
    await expect(button).toBeEnabled();
    await button.click();
    await expect(button).toBeDisabled();
});

test.describe('multiple tabs', () => {
    test('Changing quote on a different tab for an in progress order makes the payment invalid', async ({ browser }) => {
        const context = await browser.newContext();
        const mainPage = await context.newPage();
        const visitCheckoutPayment = new VisitCheckoutPayment(mainPage);
        await visitCheckoutPayment.visit();
        await mainPage.getByLabel('Rvvup Payment Method').click();
        await mainPage.getByRole('button', {name: 'Place order'}).click();
        const frame = mainPage.frameLocator('#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD');
        await frame.getByRole('button', {name: 'Pay now'}).isVisible();
    
        const duplicatePage = await context.newPage();
        const cart = new Cart(duplicatePage);
        await cart.addStandardItemToCart("Rvvup Crypto Future");
    
        await frame.getByRole('button', {name: 'Pay now'}).click();
    
        await mainPage.waitForURL("**/checkout/cart/");
        await expect(mainPage.getByText("Your cart was modified after making payment request, please place order again.")).toBeVisible();
    });

    test('Cannot complete the same order in multiple tabs simultaneously', async ({ browser }) => {
        const context = await browser.newContext();

        // Start the checkout on the first tab
        const mainPage = await context.newPage();
        const mainCheckout = new VisitCheckoutPayment(mainPage);
        await mainCheckout.visit();

        await mainPage.getByLabel('Rvvup Payment Method').click();
        await mainPage.getByRole('button', { name: 'Place order' }).click();
        const mainFrame = mainPage.frameLocator('#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD');
        
        // Start the checkout on the second tab
        const duplicatePage = await context.newPage();
        await duplicatePage.goto('./checkout');
        await duplicatePage.getByRole('button', { name: 'Next' }).click();

        await duplicatePage.getByLabel('Rvvup Payment Method').click();
        await duplicatePage.getByRole('button', { name: 'Place order' }).click();
        const duplicateFrame = duplicatePage.frameLocator('#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD');

        // Complete order in the first tab, and then in the second tab shortly after
        await mainFrame.getByRole('button', { name: 'Pay now' }).click();
        await duplicateFrame.getByRole('button', { name: 'Pay now' }).click();
    
        await duplicatePage.waitForURL("**/checkout/onepage/success/");
        await expect(duplicatePage.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();

        await expect(mainPage.getByText(/This checkout cannot complete, a new cart was opened in another tab.+/)).toBeVisible();
    });

    test('Cannot place order in one tab and then place the same order again in another tab', async ({ browser }) => {
        const context = await browser.newContext();

        // Start the checkout on the first tab
        const mainPage = await context.newPage();
        const mainCheckout = new VisitCheckoutPayment(mainPage);
        await mainCheckout.visit();
        await mainPage.getByLabel('Rvvup Payment Method').click();

        // Open the checkout page on the second tab
        const duplicatePage = await context.newPage();
        await duplicatePage.goto('./checkout');
        await duplicatePage.getByRole('button', { name: 'Next' }).click();
        await expect(duplicatePage.getByText('Payment Method', { exact: true })).toBeVisible();
        await duplicatePage.getByLabel('Rvvup Payment Method').click();

        // Complete order in the first tab
        await mainPage.getByRole('button', { name: 'Place order' }).click();
        const mainFrame = mainPage.frameLocator('#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD');
        await mainFrame.getByRole('button', { name: 'Pay now' }).click();

        await mainPage.waitForURL("**/checkout/onepage/success/");
        await expect(mainPage.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();

        // Complete order in the second tab
        await duplicatePage.getByRole('button', { name: 'Place order' }).click();
        await expect(duplicatePage.getByText('No such entity with cartId =')).toBeVisible();
    });
})

test.describe('discounts', () => {
    test('Can place an order using discount codes', async ({ page }) => {
        const visitCheckoutPayment = new VisitCheckoutPayment(page);
        await visitCheckoutPayment.visit();

        await page.getByRole('heading', {name: 'Apply discount code'}).click();
        await page.getByPlaceholder('Enter discount code').fill('H20');
        await page.getByRole('button', { name: 'Apply Discount' }).click();

        const payByBankCheckout = new PayByBankCheckout(page);
        await payByBankCheckout.checkout();
    });

    test('Cannot place order when discount is 100% and cart value is £0', async ({ page }) => {
        const visitCheckoutPayment = new VisitCheckoutPayment(page);
        await visitCheckoutPayment.visitWithoutShippingFee();

        await page.getByRole('heading', {name: 'Apply discount code'}).click();
        await page.getByPlaceholder('Enter discount code').fill('100');
        await page.getByRole('button', { name: 'Apply Discount' }).click();
        
        await expect(page.getByText('No Payment Information Required')).toBeVisible();
        await expect(page.getByLabel('Pay by Bank')).not.toBeVisible();
    });
})

test.describe('rounding', () => {
    test.skip('No PayPal rounding errors when paying for 20% VAT products', async ({ page }) => {
        const visitCheckoutPayment = new VisitCheckoutPayment(page);
        await visitCheckoutPayment.visitCheckoutWithMultipleProducts();

        await expect(page.getByRole('row', { name: 'Order Total £' }).locator('span').getByText('£6.00')).toBeVisible();
        await expect(page.getByText('PayPal', { exact: true })).toBeEnabled();
    });

    test.skip('No Clearpay rounding errors when paying for 20% VAT products', async ({ page }) => {
        const visitCheckoutPayment = new VisitCheckoutPayment(page);
        await visitCheckoutPayment.visitCartWithMultipleProducts();

        await expect(page.getByText('or 4 interest-free payments of £1.50 with ⓘ')).toBeVisible();
    });
});