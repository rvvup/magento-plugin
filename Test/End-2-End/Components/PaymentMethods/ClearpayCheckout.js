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
    await clearpayFrame.getByRole("button", { name: "Accept All" }).click();

    await clearpayFrame
      .getByTestId("login-password-input")
      .fill("XHvZsaUWh6K-BPWgXY!NJBwG");
    await clearpayFrame.getByTestId('login-password-button').click();
    await clearpayFrame.getByTestId('summary-button').click();
  }
}
