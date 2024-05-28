import { expect, test } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import OrderConfirmation from "./Components/OrderConfirmation";
import PaypalCheckout from "./Components/PaymentMethods/PaypalCheckout";
import CheckoutPage from "./Components/CheckoutPage";
import GoTo from "./Components/GoTo";

test("Can place a PayPal order on checkout", async ({ page }) => {
  await new VisitCheckoutPayment(page).visit();

  await new CheckoutPage(page).selectPaypal();
  await new PaypalCheckout(page).pressPaypalButton();

  await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test.fixme(
  "Can place a PayPal order on checkout using debit or credit cards",
  async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CheckoutPage(page).selectPaypal();

    await new PaypalCheckout(page).checkoutUsingCard();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  },
);

test("Can place a PayPal express order", async ({ page }) => {
  await new GoTo(page).product.standard();
  await new PaypalCheckout(page).pressPaypalButton();

  // Shipping page
  await page.getByLabel("Phone number").fill("+447500000000");
  await page.getByLabel("Free").click();

  await page.getByRole("button", { name: "Next" }).click();

  // Checkout page
  await expect(page.getByText("Payment Method", { exact: true })).toBeVisible();

  const numOfMethods = await page.locator(".payment-method").count();
  await expect(numOfMethods).toBe(1);
  await page
    .getByText(
      "You are currently paying with PayPal. If you want to cancel this process",
    )
    .isVisible();
  await new CheckoutPage(page).pressPlaceOrder();
  await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test.fixme(
  "Can place a PayPal express order using debit or credit cards",
  async ({ page }) => {
    await new GoTo(page).product.standard();

    await new PaypalCheckout(page).checkoutUsingCard();

    // Continue to shipping and checkout
    await page.getByLabel("Phone number").fill("+441234567890");
    await page.getByRole("button", { name: "Next" }).click();

    await expect(
      page.getByText("Payment Method", { exact: true }),
    ).toBeVisible();

    await new CheckoutPage(page).pressPlaceOrder();

    await new OrderConfirmation(page).expectOnOrderConfirmation();
  },
);

test("PayPal replaces the Place Order button with a PayPal button", async ({
  page,
}) => {
  await new VisitCheckoutPayment(page).visit();

  await new CheckoutPage(page).selectPayByBank();

  await expect(page.getByRole("button", { name: "Place Order" })).toBeVisible();

  await new CheckoutPage(page).selectPaypal();
  await expect(
    page.getByRole("button", { name: "Place Order" }),
  ).not.toBeVisible();

  await expect(
    page
      .frameLocator("[title='PayPal']")
      .first()
      .getByRole("link", { name: "PayPal" }),
  ).toBeVisible();
});
