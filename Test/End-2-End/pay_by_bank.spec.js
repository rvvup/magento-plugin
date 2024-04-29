import { test } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import PayByBankCheckout from "./Components/PayByBankCheckout";

test('Can place an order using pay by bank', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    const payByBankCheckout = new PayByBankCheckout(page);
    await payByBankCheckout.checkout();
});
