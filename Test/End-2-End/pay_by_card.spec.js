import test, {expect} from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import OrderConfirmation from "./Components/OrderConfirmation";
import CheckoutPage from "./Components/CheckoutPage";
import CardCheckout from "./Components/PaymentMethods/CardCheckout";

test('Can place an inline pay by card order with 3DS challenge', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CardCheckout(page).checkout();
    await new OrderConfirmation(page).expectOnOrderConfirmation();

});

test('Can place an inline pay by card order without 3DS challenge', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CardCheckout(page).checkoutUsingFrictionless3DsCard();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test('Cannot place pay by card order using invalid card details', async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CheckoutPage(page).selectCard();

    // Credit card form
    await new CardCheckout(page).checkoutUsingInvalidCard();

    await expect(page.getByText('3DSecure failed')).toBeVisible();
});
