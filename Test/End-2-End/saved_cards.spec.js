import test, { expect } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import OrderConfirmation from "./Components/OrderConfirmation";
import Customer from "./Components/Customer";
import CardCheckout, {
  THREE_DS_FRICTIONLESS_TEST_CARD_NUMBER,
} from "./Components/PaymentMethods/CardCheckout";

// The card is saved with this expiry, My Account shows it as MM/YYYY.
const CARD_EXPIRY = "12/33";
const CARD_EXPIRY_IN_MY_ACCOUNT = "12/2033";
const CARD_LAST_4 = "1471";

const SAVE_CARD_CHECKBOX = ".rvvup-save-card input.checkbox";
const SAVED_CARD = ".rvvup-saved-card";

// Saved cards need the INLINE card flow and a merchant with saved cards enabled. Both live on the
// Rvvup merchant of the configured API key, a test run cannot change them, so the tests skip
// themselves otherwise, as in pay_by_card.spec.js.
async function skipUnlessSavedCardsAreEnabled(page) {
  const readCardSettings = () =>
    page.evaluate(() => {
      try {
        return rvvup_parameters?.settings?.card ?? null;
      } catch (ignored) {
        return null;
      }
    });

  await expect
    .poll(readCardSettings, {
      message:
        "the card method is missing from the checkout, the store cannot reach the Rvvup API",
    })
    .not.toBeNull();

  const settings = await readCardSettings();
  test.skip(
    settings.flow !== "INLINE" || !settings.savedCardsEnabled,
    `merchant account uses the ${settings.flow} card flow with saved cards ${
      settings.savedCardsEnabled ? "enabled" : "disabled"
    }`,
  );
}

// Registers a customer and pays with a new card, saving it.
async function saveCardAsNewCustomer(page) {
  const customer = new Customer(page);
  await customer.register();

  await new VisitCheckoutPayment(page).visitAsCustomer();
  await skipUnlessSavedCardsAreEnabled(page);

  const card = new CardCheckout(page);
  await card.selectCard();
  await page.locator(SAVE_CARD_CHECKBOX).check();
  await card.fillCardDetails(
    THREE_DS_FRICTIONLESS_TEST_CARD_NUMBER,
    CARD_EXPIRY,
  );
  await card.submit();

  await new OrderConfirmation(page).expectOnOrderConfirmationAsCustomer();

  return customer;
}

test.describe("saved cards", () => {
  test.describe.configure({ timeout: 240000 });

  test("a customer saves a card, pays with it and deletes it", async ({
    page,
  }) => {
    const customer = await saveCardAsNewCustomer(page);

    await customer.openStoredPaymentMethods();
    await expect(customer.storedCards()).toHaveCount(1);
    await expect(customer.storedCards().first()).toContainText(CARD_LAST_4);
    await expect(customer.storedCards().first()).toContainText(
      CARD_EXPIRY_IN_MY_ACCOUNT,
    );

    // The next checkout offers the saved card, which is paid without entering card details.
    await new VisitCheckoutPayment(page).visitAsCustomer();
    const savedCard = page.locator(SAVED_CARD);
    await expect(savedCard).toHaveCount(1);
    await expect(savedCard).toContainText(CARD_LAST_4);
    await savedCard.locator("input.radio").check();
    await savedCard.getByRole("button", { name: "Pay", exact: true }).click();
    await new OrderConfirmation(page).expectOnOrderConfirmationAsCustomer();

    // Paying with a saved card must not save the card again.
    await customer.openStoredPaymentMethods();
    await expect(customer.storedCards()).toHaveCount(1);

    await customer.deleteStoredCard();
    await expect(customer.storedCards()).toHaveCount(0);
  });

  test("a customer cannot pay with the saved card of another customer", async ({
    page,
    browser,
  }, testInfo) => {
    await saveCardAsNewCustomer(page);

    // The client only ever sees the public hash of a saved card, never the Rvvup token.
    await new VisitCheckoutPayment(page).visitAsCustomer();
    const publicHash = await page.evaluate(
      () => Object.values(window.checkoutConfig.payment.vault)[0].config.publicHash,
    );
    expect(publicHash).toBeTruthy();

    // A context created here is not traced by Playwright, trace it so a failure can be diagnosed.
    const otherContext = await browser.newContext();
    await otherContext.tracing.start({ screenshots: true, snapshots: true });
    let failed = true;
    try {
      const otherPage = await otherContext.newPage();
      await new Customer(otherPage).register();
      await new VisitCheckoutPayment(otherPage).visitAsCustomer();

      const response = await otherPage.evaluate(
        (hash) =>
          new Promise((resolve) => {
            require([
              "Rvvup_Payments/js/action/checkout/payment/create-payment-session",
            ], (createPaymentSession) => {
              createPaymentSession(
                { addErrorMessage() {} },
                rvvup_parameters.checkout.id,
                { method: "rvvup_CARD", additional_data: { public_hash: hash } },
              )
                .done(() => resolve({ status: 200, body: "" }))
                .fail((xhr) =>
                  resolve({ status: xhr.status, body: xhr.responseText }),
                );
            });
          }),
        publicHash,
      );

      expect(response.status).toBe(400);
      expect(response.body).toContain("This saved card can no longer be used");
      failed = false;
    } finally {
      if (failed) {
        await otherContext.tracing.stop({
          path: testInfo.outputPath("other-customer-trace.zip"),
        });
      } else {
        await otherContext.tracing.stop();
      }
      await otherContext.close();
    }
  });

  test("a guest is not offered to save a card", async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();
    await skipUnlessSavedCardsAreEnabled(page);

    await new CardCheckout(page).selectCard();

    await expect(page.locator(SAVE_CARD_CHECKBOX)).toHaveCount(0);
    await expect(page.locator(SAVED_CARD)).toHaveCount(0);
  });
});
