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

test.describe("multiple tabs", () => {
  test("Changing quote on a different tab for an in progress order makes the payment invalid", async ({
    browser,
  }) => {
    const context = await browser.newContext();
    const mainPage = await context.newPage();
    const visitCheckoutPayment = new VisitCheckoutPayment(mainPage);
    await visitCheckoutPayment.visit();
    await mainPage.getByLabel("Rvvup Payment Method").click();
    await mainPage.getByRole("button", { name: "Place order" }).click();
    const frame = mainPage.frameLocator(
      "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
    );
    await frame.getByRole("button", { name: "Pay now" }).isVisible();

    const duplicatePage = await context.newPage();
    const cart = new Cart(duplicatePage);
    await cart.addStandardItemToCart("Rvvup Crypto Future");

    await frame.getByRole("button", { name: "Pay now" }).click();

    await mainPage.waitForURL("**/checkout/cart/");
    await expect(
      mainPage.getByText(
        "Your cart was modified after making payment request, please place order again.",
      ),
    ).toBeVisible();
  });

  test.fixme(
    "Cannot complete the same order in multiple tabs simultaneously",
    async ({ browser }) => {
      const context = await browser.newContext();

      // Start the checkout on the first tab
      const mainPage = await context.newPage();
      await new VisitCheckoutPayment(mainPage).visit();

      await mainPage.getByLabel("Rvvup Payment Method").click();
      await mainPage.getByRole("button", { name: "Place order" }).click();
      const mainFrame = mainPage.frameLocator(
        "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
      );

      // Start the checkout on the second tab
      const duplicatePage = await context.newPage();
      await new GoTo(duplicatePage).checkout();

      await duplicatePage.getByRole("button", { name: "Next" }).click();

      await duplicatePage.getByLabel("Rvvup Payment Method").click();
      await duplicatePage.getByRole("button", { name: "Place order" }).click();
      const duplicateFrame = duplicatePage.frameLocator(
        "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
      );

      // Complete order in the first tab, and then in the second tab shortly after
      await duplicateFrame.getByRole("button", { name: "Pay now" }).click();

      await new OrderConfirmation(duplicatePage).expectOnOrderConfirmation();

      await mainFrame.getByRole("button", { name: "Pay now" }).click();
      await expect(
        mainPage.getByText(/An error has happened during application run.*/),
      ).toBeVisible();
    },
  );

  test("Cannot place order in one tab and then place the same order again in another tab", async ({
    browser,
  }) => {
    const context = await browser.newContext();

    // Start the checkout on the first tab
    const mainPage = await context.newPage();
    await new VisitCheckoutPayment(mainPage).visit();
    await mainPage.getByLabel("Rvvup Payment Method").click();

    // Open the checkout page on the second tab
    const duplicatePage = await context.newPage();
    await new GoTo(duplicatePage).checkout();

    await duplicatePage.getByRole("button", { name: "Next" }).click();
    await expect(
      duplicatePage.getByText("Payment Method", { exact: true }),
    ).toBeVisible();
    await duplicatePage.getByLabel("Rvvup Payment Method").click();

    // Complete order in the first tab
    await mainPage.getByRole("button", { name: "Place order" }).click();
    const mainFrame = mainPage.frameLocator(
      "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
    );
    await mainFrame.getByRole("button", { name: "Pay now" }).click();

    await new OrderConfirmation(mainPage).expectOnOrderConfirmation();

    // Complete order in the second tab
    await duplicatePage.getByRole("button", { name: "Place order" }).click();
    await expect(
      duplicatePage.getByText("No such entity with cartId ="),
    ).toBeVisible();
  });
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
