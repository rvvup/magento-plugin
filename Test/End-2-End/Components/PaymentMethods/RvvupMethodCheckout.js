import { expect } from "@playwright/test";
import CheckoutPage from "../CheckoutPage";
import { clickDismissingCookies } from "../CookieBanner";

export default class RvvupMethodCheckout {
  constructor(page) {
    this.page = page;
    this.checkoutPage = new CheckoutPage(page);
  }

  async checkout() {
    await this.checkoutPage.selectRvvupTestMethod();
    await this.checkoutPage.pressPlaceOrder();

    const frame = this.page.frameLocator(
      "#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD",
    );
    await clickDismissingCookies(
      frame,
      frame.getByRole("button", { name: "Pay now" }),
    );
  }
}
