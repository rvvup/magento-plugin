import CheckoutPage from "../CheckoutPage";

export default class ClearpayCheckout {
  constructor(page) {
    this.page = page;
    this.checkoutPage = new CheckoutPage(page);
  }

  /*
   * On the checkout page, place a pay by bank order and complete it
   */
  async checkout() {
    await this.checkoutPage.selectClearpay();
    await this.checkoutPage.pressPlaceOrder();

    const clearpayFrame = this.page.frameLocator(
      "#rvvup_iframe-rvvup_CLEARPAY",
    );
    const passwordInput = clearpayFrame.getByTestId("login-password-input");
    await passwordInput.waitFor();

    // The consent banner varies by environment: "Accept All" locally, only "Close" on CI.
    // Scoped to the Privacy dialog, as the popup has its own "Close" that cancels the order.
    await clearpayFrame
      .getByRole("dialog", { name: "Privacy" })
      .getByRole("button", { name: /Accept All|Close/ })
      .first()
      .click({ timeout: 5000 })
      .catch(() => {});

    await passwordInput.fill("XHvZsaUWh6K-BPWgXY!NJBwG");
    await clearpayFrame.getByTestId("login-password-button").click();
    await clearpayFrame.getByTestId("summary-button").click();
  }
}
