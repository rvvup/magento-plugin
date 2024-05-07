import {expect, test} from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import RvvupMethodCheckout from "./Components/RvvupMethodCheckout";
import Cart from "./Components/Cart";

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


test('Changing qoute on a different tab for an in progress order makes the payment invalid', async ({ browser }) => {
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

