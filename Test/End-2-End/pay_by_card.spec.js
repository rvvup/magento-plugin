import test, { expect } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import OrderConfirmation from "./Components/OrderConfirmation";
import CardCheckout, {
  CARD_FORM_CONTAINER_SELECTOR,
  CARD_SUBMIT_BUTTON_SELECTOR,
  INLINE_DECLINED_TEST_CARD_NUMBER,
  FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER,
  THREE_DS_TEST_CARD_NUMBER,
  THREE_DS_CHALLENGE_IFRAME_SELECTOR,
} from "./Components/PaymentMethods/CardCheckout";
import HostedCardCheckout from "./Components/PaymentMethods/HostedCardCheckout";
import CheckoutPage from "./Components/CheckoutPage";
import GoTo from "./Components/GoTo";

// The merchant's server-side card.flow setting decides which of these two describe blocks can
// run against the sandbox, see Test/End-2-End/CARD_TESTING.md for how to switch it. Each test
// reads the flow from the checkout page and skips itself when the other flow is active.
async function skipUnlessCardFlowIs(page, expectedFlow) {
  // rvvup_parameters is a const in a page script, so it is a lexical global that is
  // not reachable through the window object. The settings it carries arrive with the
  // payment method list, which can land after the payment step renders, so this polls
  // instead of reading once and failing the whole test on a slow response.
  const readCardFlow = () =>
    page.evaluate(() => {
      try {
        return rvvup_parameters?.settings?.card?.flow ?? null;
      } catch (ignored) {
        return null;
      }
    });

  await expect
    .poll(readCardFlow, {
      message:
        "the card method is missing from the checkout, the store cannot reach the Rvvup API",
    })
    .not.toBeNull();

  const flow = await readCardFlow();
  test.skip(
    flow !== expectedFlow,
    `merchant account uses the ${flow} card flow`,
  );
}

test.describe("inline card flow (Basis Theory SDK)", () => {
  test("it renders the inline sdk card fields on the checkout payment step", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    await new CardCheckout(page).selectCard();

    await expect(page.locator(CARD_FORM_CONTAINER_SELECTOR)).toBeVisible();
    await expect(
      page.locator(`${CARD_FORM_CONTAINER_SELECTOR} iframe`).first(),
    ).toBeVisible();
    await expect(page.locator(CARD_SUBMIT_BUTTON_SELECTOR)).toBeVisible();
  });

  test("it completes an inline card payment end to end with the 4111 test card", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    await new CardCheckout(page).checkout();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });

  test("it survives a payment list re-render and creates exactly one payment session", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    const cardCheckout = new CardCheckout(page);
    await cardCheckout.selectCard();
    await expect(
      page.locator(`${CARD_FORM_CONTAINER_SELECTOR} iframe`).first(),
    ).toBeVisible();

    // Applying a discount reloads the payment information, which re-renders
    // the payment method list and remounts the card component.
    await new CheckoutPage(page).applyDiscountCode("H20");

    await cardCheckout.selectCard();
    await expect(
      page.locator(`${CARD_FORM_CONTAINER_SELECTOR} iframe`).first(),
    ).toBeVisible();

    let paymentSessionRequests = 0;
    page.on("request", (request) => {
      if (request.url().includes("create-payment-session")) {
        paymentSessionRequests++;
      }
    });

    await cardCheckout.fillCardDetails(FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER);
    await cardCheckout.submit();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
    expect(paymentSessionRequests).toBe(1);
  });

  test("it keeps the card fields usable after switching payment methods", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    const cardCheckout = new CardCheckout(page);
    await cardCheckout.selectCard();
    await expect(
      page.locator(`${CARD_FORM_CONTAINER_SELECTOR} iframe`).first(),
    ).toBeVisible();

    await page.getByLabel("Check / Money order").click();
    await cardCheckout.selectCard();

    await cardCheckout.fillCardDetails(FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER);
    await cardCheckout.submit();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });

  test("it registers the inline card order as processing in the admin", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    await new CardCheckout(page).checkout();
    const orderId = await new OrderConfirmation(
      page,
    ).expectOnOrderConfirmation();

    await new GoTo(page).admin("e2e-tests-order-status").order(orderId);

    await expect(page.locator("#order_status")).toHaveText("Processing");
  });

  test("it completes an inline card payment end to end through the 3ds challenge", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    await new CardCheckout(page).checkoutUsingThreeDsCard();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });

  test("it registers the 3ds card order as processing in the admin", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    await new CardCheckout(page).checkoutUsingThreeDsCard();
    const orderId = await new OrderConfirmation(
      page,
    ).expectOnOrderConfirmation();

    await new GoTo(page).admin("e2e-tests-order-status").order(orderId);

    await expect(page.locator("#order_status")).toHaveText("Processing");
  });

  test("it keeps a loader on screen from card submit until the 3ds challenge opens", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    const cardCheckout = new CardCheckout(page);
    await cardCheckout.selectCard();
    await cardCheckout.fillCardDetails(THREE_DS_TEST_CARD_NUMBER);
    await cardCheckout.submit();

    expect(await cardCheckout.findLoaderGapsBeforeThreeDsChallenge()).toEqual(
      [],
    );

    // The challenge asks the shopper for input, so the loader steps aside for it and comes
    // back once they have answered and the payment is being authorised.
    await expect(cardCheckout.loaderLocator()).toBeHidden();

    await cardCheckout.completeThreeDsChallenge();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });

  // Cancelling the challenge does not surface a shopper facing message today, the SDK only
  // hands the card form back. This test pins the recovery behaviour, not the messaging.
  test("it hands the card form back when the 3ds challenge is cancelled", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "INLINE");

    const cardCheckout = new CardCheckout(page);
    await cardCheckout.checkoutUsingCard(THREE_DS_TEST_CARD_NUMBER);
    await cardCheckout.cancelThreeDsChallenge();

    await expect(
      page.locator(THREE_DS_CHALLENGE_IFRAME_SELECTOR),
    ).not.toBeVisible();
    await expect(page.locator(CARD_SUBMIT_BUTTON_SELECTOR)).toBeEnabled();
    await expect(cardCheckout.loaderLocator()).toBeHidden();
  });

  test.fixme(
    "it shows a shopper facing message when the inline payment is declined",
    async ({ page }) => {
      const jsErrors = [];
      page.on("pageerror", (error) => jsErrors.push(error));

      await new VisitCheckoutPayment(page).visit();

      const cardCheckout = new CardCheckout(page);
      await cardCheckout.checkoutUsingCard(INLINE_DECLINED_TEST_CARD_NUMBER);

      await cardCheckout.expectDeclineMessageToBeShown();
      expect(jsErrors).toEqual([]);
    },
  );
});

test.describe("hosted card flow (Rvvup modal) regression", () => {
  test("it still completes a hosted card payment through the modal flow", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessCardFlowIs(page, "HOSTED");

    await new HostedCardCheckout(page).checkout();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });
});
