import { test, expect } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import OrderConfirmation from "./Components/OrderConfirmation";

test('Can place a PayPal order on checkout', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await page.getByLabel('PayPal', { exact: true }).click();

    page.on('popup', async popup => {
        await popup.waitForLoadState();

        await popup.getByPlaceholder(/Email.* or mobile number/).fill('sb-uqeqf29136249@personal.example.com');
        await popup.getByPlaceholder('Password').fill('h5Hc/b8M');

        await popup.getByRole('button', { name: 'Log In' }).click();
        await popup.getByRole('button', { name: 'Complete Purchase' }).click();
    });

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'PayPal' }).click();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test.fixme('Can place a PayPal order on checkout using debit or credit cards', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await page.getByLabel('PayPal', { exact: true }).click();

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'Debit or Credit Card' }).click();

    const paypalCardForm = page.frameLocator("[title='paypal_card_form']");
    await paypalCardForm.getByLabel('Card number').fill('4698 4665 2050 8153')
    await paypalCardForm.getByLabel('Expires').fill('1125')
    await paypalCardForm.getByLabel('Security code').fill('141')
    await paypalCardForm.getByLabel('Mobile').fill('1234567890')
    await paypalCardForm.getByRole('button', { name: 'Buy Now' }).click();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test('Can place a PayPal express order', async ({ page }) => {
    page.on('popup', async popup => {
        await popup.waitForLoadState();
        await popup.getByPlaceholder(/Email.* or mobile number/).fill('sb-uqeqf29136249@personal.example.com');
        await popup.getByRole('button', { name: 'Next' }).click();

        await popup.getByPlaceholder('Password').fill('h5Hc/b8M');
        await popup.getByRole('button', { name: 'Log In' }).click();

        await popup.getByRole('button', { name: 'Continue to Review Order' }).click();
    });

    // Product page
    page.goto('./joust-duffle-bag.html');

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'PayPal' }).click();

    // Shipping page
    await page.getByLabel('Phone number').fill('+447500000000');
    await page.getByRole('button', { name: 'Next' }).click();

    // Checkout page
    await expect(page.getByText('Payment Method', { exact: true })).toBeVisible();

    // TODO: Check that only PayPal is an option on checkout
    // const children = await page.$$('#payment-methods ol > li');
    // await expect(children.length).toBe(1);

    await page.getByRole('button', { name: 'Place order' }).click();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test.fixme('Can place a PayPal express order using debit or credit cards', async ({ page }) => {
    page.goto('./joust-duffle-bag.html');

    const paypalFrame = page.frameLocator("[title='PayPal']").first();
    await paypalFrame.getByRole('link', { name: 'Debit or Credit Card' }).click();

    // Fill in the form
    const paypalCardForm = paypalFrame.frameLocator("[title='paypal_card_form']");
    await paypalCardForm.getByPlaceholder('Card number').fill('4698 4665 2050 8153')
    await paypalCardForm.getByPlaceholder('Expires').fill('1125')
    await paypalCardForm.getByPlaceholder('Security code').fill('141')

    await paypalCardForm.getByPlaceholder('First name').fill('John');
    await paypalCardForm.getByPlaceholder('Last name').fill('Doe');
    await paypalCardForm.getByPlaceholder('Address line 1').fill('123 Main St');
    await paypalCardForm.getByPlaceholder('Town/City').fill('London');
    await paypalCardForm.getByPlaceholder('Postcode').fill('SW1A 1AA');
    await paypalCardForm.getByPlaceholder('Mobile').fill('1234567890')
    await paypalCardForm.getByPlaceholder('Email').fill('johndoe@example.com')

    await paypalCardForm.getByRole('button', { name: 'Continue' }).click();

    // Continue to shipping and checkout
    await page.getByLabel('Phone number').fill('+441234567890');
    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByText('Payment Method', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Place order' }).click();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test('PayPal replaces the Place Order button with a PayPal button', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await page.getByText('Pay by Bank', { exact: true }).click();
    await expect(page.getByRole('button', { name: 'Place Order' })).toBeVisible();

    await page.getByText('PayPal', { exact: true }).click();
    await expect(page.getByRole('button', { name: 'Place Order' })).not.toBeVisible();

    await expect(
        page.frameLocator("[title='PayPal']").first().getByRole('link', { name: 'PayPal' })
    ).toBeVisible();
})
