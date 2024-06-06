import { expect, test } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import RvvupMethodCheckout from "./Components/PaymentMethods/RvvupMethodCheckout";
import Cart from "./Components/Cart";
import OrderConfirmation from "./Components/OrderConfirmation";
import GoTo from "./Components/GoTo";
import CheckoutPage from "./Components/CheckoutPage";

test("Can place an order using different billing and shipping address", async ({
  page,
  browser,
}) => {
  await new VisitCheckoutPayment(page).visit();
  await page.getByLabel("Rvvup Payment Method").click();
  await page
    .locator("#billing-address-same-as-shipping-rvvup_FAKE_PAYMENT_METHOD")
    .setChecked(false);

  const billingForm = page.locator("#payment_method_rvvup_FAKE_PAYMENT_METHOD");
  if (
    await page.getByRole("button", { name: "Edit", exact: true }).isVisible()
  ) {
    await page.getByRole("button", { name: "Edit", exact: true }).click();
  }
  await billingForm.getByLabel("First Name").fill("Liam");
  await billingForm.getByLabel("Last Name").fill("Fox");
  await billingForm.getByLabel("Street Address: Line 1").fill("123 Small St");
  await billingForm.getByLabel("City").fill("Derby");
  await billingForm.getByLabel("Country").selectOption("United Kingdom");
  await billingForm.getByLabel("ZIP").fill("SW1B 1BB");
  await billingForm.getByLabel("Phone number").fill("+447599999999");
  await page.getByRole("button", { name: "Update" }).click();
  await billingForm.getByLabel("First Name").isHidden();
  await billingForm.getByText("Liam Fox").isVisible();
  const rvvupMethodCheckout = new RvvupMethodCheckout(page);
  await rvvupMethodCheckout.checkout();

  await new OrderConfirmation(page).expectOnOrderConfirmation();
});

test.describe("discounts", () => {
  test("Can place an order using discount codes", async ({ page }) => {
    await new VisitCheckoutPayment(page).visit();

    await new CheckoutPage(page).applyDiscountCode("H20");

    await new RvvupMethodCheckout(page).checkout();
    await new OrderConfirmation(page).expectOnOrderConfirmation();
  });

  // TODO: Need to add Free shipping option to test this
  test.skip("Cannot place order when discount is 100% and cart value is £0", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visitWithoutShippingFee();

    await new CheckoutPage(page).applyDiscountCode("100");

    await expect(
      page.getByText("No Payment Information Required"),
    ).toBeVisible();
    await expect(page.getByLabel("Pay by Bank")).not.toBeVisible();
  });
});

test.describe("rounding", () => {
  test.skip("No PayPal rounding errors when paying for 20% VAT products", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visitCheckoutWithMultipleProducts();

    await expect(
      page
        .getByRole("row", { name: "Order Total £" })
        .locator("span")
        .getByText("£6.00"),
    ).toBeVisible();
    await expect(page.getByText("PayPal", { exact: true })).toBeEnabled();
  });

  test.skip("No Clearpay rounding errors when paying for 20% VAT products", async ({
    page,
  }) => {
    await new VisitCheckoutPayment(page).visitCartWithMultipleProducts();

    await expect(
      page.getByText("or 4 interest-free payments of £1.50 with ⓘ"),
    ).toBeVisible();
  });
});
