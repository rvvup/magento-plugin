import CheckoutPage from "../CheckoutPage";
import { FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER } from "./CardCheckout";

export const HOSTED_CARD_MODAL_IFRAME_SELECTOR = "#rvvup_iframe-rvvup_CARD";

/**
 * Drives the HOSTED card flow, i.e. the existing Rvvup iframe modal
 * (src/view/frontend/web/js/view/payment/method-renderer/rvvup-method.js showRvvupModal/showModal)
 * that loads Rvvup's own hosted card page.
 *
 * The hosted card page's own field selectors are outside this plugin's control. The selectors
 * below assume the hosted page still exposes the pre-SDK SecureTrading-style card iframes;
 * confirm this against a live sandbox run and update accordingly (TODO).
 */
export default class HostedCardCheckout {
  constructor(page) {
    this.page = page;
    this.checkoutPage = new CheckoutPage(page);
  }

  async checkout() {
    await this.checkoutPage.selectCard();
    await this.checkoutPage.pressPlaceOrder();

    const modalFrame = this.page.frameLocator(
      HOSTED_CARD_MODAL_IFRAME_SELECTOR,
    );

    // TODO: confirm the exact hosted card page field selectors against a live sandbox run.
    await modalFrame
      .frameLocator(".st-card-number-iframe")
      .getByLabel("Card Number")
      .fill(FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER);
    await modalFrame
      .frameLocator(".st-expiration-date-iframe")
      .getByLabel("Expiration Date")
      .fill("1233");
    await modalFrame
      .frameLocator(".st-security-code-iframe")
      .getByLabel("Security Code")
      .fill("123");

    await modalFrame.getByRole("button", { name: /pay|submit/i }).click();
  }
}
