import { expect, test } from "@playwright/test";
import VisitCheckoutPayment from "./Pages/VisitCheckoutPayment";
import Cart from "./Components/Cart";
import OrderConfirmation from "./Components/OrderConfirmation";
import GoTo from "./Components/GoTo";

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

test("Cannot complete order in first tab, if the customer starts a new order in second tab", async ({
  browser,
}) => {
  const context = await browser.newContext();

  // Start the checkout on the first tab
  const firstTab = await context.newPage();
  await new VisitCheckoutPayment(firstTab).visit();

  await firstTab.getByLabel("Rvvup Payment Method").click();
  await firstTab.getByRole("button", { name: "Place order" }).click();
  const firstTabFrame = firstTab.frameLocator(
    "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
  );

  // Start the checkout on the second tab
  const secondTab = await context.newPage();
  await new GoTo(secondTab).checkout();

  await secondTab.getByRole("button", { name: "Next" }).click();

  await secondTab.getByLabel("Rvvup Payment Method").click();
  await secondTab.getByRole("button", { name: "Place order" }).click();
  const secondTabFrame = secondTab.frameLocator(
    "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
  );
  await expect(
    secondTabFrame.getByRole("button", { name: "Pay now" }),
  ).toBeVisible();

  await firstTabFrame.getByRole("button", { name: "Pay now" }).click();
  await expect(
    firstTab.getByText(
      "This checkout cannot complete because another payment is in progress.",
    ),
  ).toBeVisible();
});

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
