import { test } from '@playwright/test';
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import PayByBankCheckout from "./Components/PayByBankCheckout";

test('Can place an order using pay by bank', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    const payByBankCheckout = new PayByBankCheckout(page);
    await payByBankCheckout.checkout();
});

test('The customer can decline the payment', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    const payByBankCheckout = new PayByBankCheckout(page);
    await payByBankCheckout.decline();
});

test.fail('Payment declined if the customer has insufficient funds', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();

    // TODO: Testing insufficient funds does not work and needs fixing
    const payByBankCheckout = new PayByBankCheckout(page);
    await payByBankCheckout.declineInsufficientFunds();
});

test.fail('Payment fails if the customer exits the modal before completing the transaction on their banking app', async ({ page }) => {
    const visitCheckoutPayment = new VisitCheckoutPayment(page);
    await visitCheckoutPayment.visit();
    
    // TODO: Read the QR code and use that to generate the second page for this test
    const payByBankCheckout = new PayByBankCheckout(page);
    await payByBankCheckout.exitModalBeforeCompletingTransaction();
})