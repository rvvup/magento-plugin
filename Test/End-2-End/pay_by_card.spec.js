import test, { expect } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import OrderConfirmation from "./Components/OrderConfirmation";
import CardCheckout, {
  CARD_FORM_CONTAINER_SELECTOR,
  CARD_SUBMIT_BUTTON_SELECTOR,
  BASIS_THEORY_3DS_TEST_CARD_NUMBER,
  INLINE_DECLINED_TEST_CARD_NUMBER,
} from "./Components/PaymentMethods/CardCheckout";
import HostedCardCheckout from "./Components/PaymentMethods/HostedCardCheckout";

// Which of these two describe blocks actually runs against the sandbox depends on the merchant's
// server-side card.flow setting, see Test/End-2-End/CARD_TESTING.md for how to switch it.
test.describe("inline card flow (Basis Theory SDK)", () => {
  test("it renders the inline sdk card fields on the checkout payment step", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CardCheckout(page).selectCard();

    await expect(page.locator(CARD_FORM_CONTAINER_SELECTOR)).toBeVisible();
    await expect(
      page.locator(`${CARD_FORM_CONTAINER_SELECTOR} iframe`),
    ).toBeVisible();
    await expect(page.locator(CARD_SUBMIT_BUTTON_SELECTOR)).toBeVisible();
  });

  test("it completes an inline card payment end to end with the 4111 test card", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CardCheckout(page).checkout();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });

  test("it opens the 3ds journey for an inline 3ds test card and reports the decline to the shopper", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();

    const cardCheckout = new CardCheckout(page);
    await cardCheckout.checkoutUsingCard(BASIS_THEORY_3DS_TEST_CARD_NUMBER);

    await cardCheckout.expectThreeDsChallengeToOpen();
    // The 3DS journey is expected to end in a decline, see CARD_TESTING.md for why.
    await cardCheckout.expectDeclineMessageToBeShown();
  });

  test("it shows a shopper facing message when the inline payment is declined", async ({
    page,
  }) => {
    const jsErrors = [];
    page.on("pageerror", (error) => jsErrors.push(error));

    await new VisitCheckoutPayment(page).visit();

    const cardCheckout = new CardCheckout(page);
    await cardCheckout.checkoutUsingCard(INLINE_DECLINED_TEST_CARD_NUMBER);

    await cardCheckout.expectDeclineMessageToBeShown();
    expect(jsErrors).toEqual([]);
  });
});

test.describe("hosted card flow (Rvvup modal) regression", () => {
  test("it still completes a hosted card payment through the modal flow", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();

    await new HostedCardCheckout(page).checkout();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });
});
