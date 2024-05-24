import { test } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import PayByBankCheckout from "./Components/PaymentMethods/PayByBankCheckout";
import OrderConfirmation from "./Components/OrderConfirmation";

test('Can place an order using pay by bank', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();
    await new PayByBankCheckout(page).checkout();
    await new OrderConfirmation(page).expectOnOrderConfirmation(true);
});

test('The customer can decline the payment', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await new PayByBankCheckout(page).decline();
});

test.skip('Payment declined if the customer has insufficient funds', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    // TODO: Testing insufficient funds does not work and needs fixing
    await new PayByBankCheckout(page).declineInsufficientFunds();
});

test.skip('Payment fails if the customer exits the modal before completing the transaction on their banking app', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    // TODO: Read the QR code and use that to generate the second page for this test
    await new PayByBankCheckout(page).exitModalBeforeCompletingTransaction();
});
