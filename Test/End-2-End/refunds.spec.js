import { expect, test } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import RvvupMethodCheckout from "./Components/PaymentMethods/RvvupMethodCheckout";
import OrderConfirmation from "./Components/OrderConfirmation";
import GoTo from "./Components/GoTo";

test("full refund", async ({ page }) => {
  await new VisitCheckoutPayment(page).visit();

  const rvvupMethodCheckout = new RvvupMethodCheckout(page);
  await rvvupMethodCheckout.checkout();

  const orderId = await new OrderConfirmation(page).expectOnOrderConfirmation();

  await new GoTo(page).admin("e2e-tests-refunds").creditMemoForOrder(orderId);

  await page.getByRole("button", { name: "Refund", exact: true }).click();
  await expect(page.locator(".message-success")).toContainText(
    "You created the credit memo.",
  );
});

test("partial refund", async ({ page }) => {
  await new VisitCheckoutPayment(page).visit();

  const rvvupMethodCheckout = new RvvupMethodCheckout(page);
  await rvvupMethodCheckout.checkout();

  const orderId = await new OrderConfirmation(page).expectOnOrderConfirmation();

  await new GoTo(page)
    .admin("e2e-tests-partial-refunds")
    .creditMemoForOrder(orderId);

  await page.locator("#shipping_amount").fill("0");
  await page.locator("#shipping_amount").press("Enter");
  await expect(
    page.getByRole("button", { name: "Update Totals" }),
  ).toBeEnabled();
  await page.getByRole("button", { name: "Update Totals" }).click();
  await expect(
    page.getByRole("button", { name: "Update Totals" }),
  ).toBeDisabled();
  await page.getByRole("button", { name: "Refund", exact: true }).click();
  await expect(page.locator(".message-success")).toContainText(
    "You created the credit memo.",
  );
});
