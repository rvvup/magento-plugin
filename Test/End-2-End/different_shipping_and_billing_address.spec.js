import { test } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import RvvupMethodCheckout from "./Components/RvvupMethodCheckout";

test('Can place an order using different billing and shipping address', async ({ page, browser }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();
    await page.getByLabel('Rvvup Payment Method').click();
    await page.locator('#billing-address-same-as-shipping-rvvup_FAKE_PAYMENT_METHOD').setChecked(false);

    const billingForm = page.locator('#payment_method_rvvup_FAKE_PAYMENT_METHOD');
    await page.getByRole('button', { name: 'Edit', exact: true }).click();
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

