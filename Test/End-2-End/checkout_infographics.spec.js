import {expect, test} from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import CheckoutPage from "./Components/CheckoutPage";

test('Clearpay infographic updates when total changes on checkout page', async ({page}) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    const checkoutPage = new CheckoutPage(page);

    await visitCheckoutPayment.visit();
    await page.getByLabel('Clearpay').click();

    const initialGrandTotal = await checkoutPage.getGrandTotalValue();

    await checkoutPage.applyDiscountCode('100');

    await expect(await checkoutPage.getGrandTotalElement()).not.toHaveText(initialGrandTotal);

    const currentGrandTotal = await checkoutPage.getGrandTotalValue(true);
    let clearpayPayBy4Amount = (Math.round(parseFloat(currentGrandTotal) / 4 * 100) / 100).toString();

    await expect(await page.frameLocator('#placeholder-payment-iframe-rvvup_CLEARPAY')
        .frameLocator('iframe').first()
        .getByRole('heading', {name: '£'})).toContainText(clearpayPayBy4Amount);
});

test('Pay by bank infographic is loaded', async ({page}) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);

    await visitCheckoutPayment.visit();

    await page.getByLabel('Pay by Bank').click();

    await expect(await page.locator('#placeholder-payment-iframe-rvvup_YAPILY'))
        .toHaveAttribute('src', new RegExp('.*/info/pay-by-bank', 'i'));
});

