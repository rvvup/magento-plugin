import { expect } from "@playwright/test";
import CheckoutPage from "../CheckoutPage";

export const CARD_FORM_CONTAINER_SELECTOR = "#rvvup-card-form-container";
export const CARD_SUBMIT_BUTTON_SELECTOR = "#submit-card";
export const CARD_ERROR_MESSAGE_SELECTOR = '[data-ui-id="message-error"]';

// Acquirer's frictionless test card, authorised without a 3DS challenge.
// See Test/End-2-End/CARD_TESTING.md.
export const FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER = "4111 1111 1111 1111";

// Acquirer's challenge test card, runs the full 3DS journey and then authorises.
export const THREE_DS_TEST_CARD_NUMBER = "4761 3699 8032 0253";

// The Ravelin sandbox ACS accepts this passcode, it is shown as the placeholder of the
// challenge input ("hint: 1234").
export const THREE_DS_CHALLENGE_PASSCODE = "1234";

// The SDK mounts the Basis Theory challenge page in this iframe, which in turn embeds the
// acquirer's own ACS page (acs.ravelin.com) holding the passcode form.
export const THREE_DS_CHALLENGE_IFRAME_SELECTOR = "#challengeIframe";

// Magento's full screen checkout loader, which the card component holds up for every phase the
// shopper is waiting on the payment.
export const FULL_SCREEN_LOADER_SELECTOR =
  'body > .loading-mask[data-role="loader"]';

// TODO: confirm a card number that is declined without triggering a 3DS challenge, so the
// "declined" scenario can be asserted independently from the 3DS journey scenario above.
export const INLINE_DECLINED_TEST_CARD_NUMBER = "4000 0000 0000 0119";

/**
 * Drives the INLINE card flow rendered by
 * src/view/frontend/web/js/view/payment/method-renderer/card.js, i.e. the Basis Theory SDK
 * fields mounted into #rvvup-card-form-container, submitted via #submit-card.
 *
 * Basis Theory mounts one iframe per field into the container, each holding a single input.
 * The iframes are identified by their accessible titles, e.g.
 * "Basis Theory CardNumberElement Safe Data Frame <uuid>".
 */
export default class CardCheckout {
  constructor(page) {
    this.page = page;
    this.checkoutPage = new CheckoutPage(page);
  }

  async selectCard() {
    await this.checkoutPage.selectCard();
    await expect(this.page.locator(CARD_FORM_CONTAINER_SELECTOR)).toBeVisible();
  }

  cardFieldFrame(elementName) {
    return this.page.frameLocator(
      `${CARD_FORM_CONTAINER_SELECTOR} iframe[title*="${elementName}"]`,
    );
  }

  async fillCardDetails(cardNumber, expiry = "12/33", cvc = "123") {
    await this.cardFieldFrame("CardNumberElement")
      .getByRole("textbox")
      .fill(cardNumber);
    await this.cardFieldFrame("CardExpirationDateElement")
      .getByRole("textbox")
      .fill(expiry);
    await this.cardFieldFrame("CardVerificationCodeElement")
      .getByRole("textbox")
      .fill(cvc);
  }

  async submit() {
    await this.page.locator(CARD_SUBMIT_BUTTON_SELECTOR).click();
  }

  async checkoutUsingCard(cardNumber, expiry = "12/33", cvc = "123") {
    await this.selectCard();
    await this.fillCardDetails(cardNumber, expiry, cvc);
    await this.submit();
  }

  async checkout() {
    await this.checkoutUsingCard(FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER);
  }

  async checkoutUsingThreeDsCard() {
    await this.checkoutUsingCard(THREE_DS_TEST_CARD_NUMBER);
    await this.completeThreeDsChallenge();
  }

  challengeFrame() {
    return this.page
      .frameLocator(THREE_DS_CHALLENGE_IFRAME_SELECTOR)
      .frameLocator("iframe");
  }

  challengeSubmitButtonLocator() {
    return this.challengeFrame().getByRole("button", { name: "Submit" });
  }

  async expectThreeDsChallengeToOpen() {
    await expect(
      this.page.locator(THREE_DS_CHALLENGE_IFRAME_SELECTOR),
    ).toBeVisible();
    await expect(this.challengeSubmitButtonLocator()).toBeVisible();
  }

  async completeThreeDsChallenge(passcode = THREE_DS_CHALLENGE_PASSCODE) {
    await this.expectThreeDsChallengeToOpen();
    await this.challengeFrame().locator("#passcode").fill(passcode);
    await this.challengeSubmitButtonLocator().click();
  }

  async cancelThreeDsChallenge() {
    await this.expectThreeDsChallengeToOpen();
    await this.challengeFrame().getByRole("button", { name: "Cancel" }).click();
  }

  loaderLocator() {
    return this.page.locator(FULL_SCREEN_LOADER_SELECTOR);
  }

  /**
   * Samples the loader every 250ms from the moment the card is submitted until the 3DS
   * challenge opens, and returns the samples where it was absent. The shopper is waiting on the
   * payment for that whole stretch, so any absence is a stretch of checkout that looks idle.
   */
  async findLoaderGapsBeforeThreeDsChallenge() {
    const challenge = this.page.locator(THREE_DS_CHALLENGE_IFRAME_SELECTOR);
    const gaps = [];
    for (let sample = 0; sample < 160; sample++) {
      if ((await challenge.count()) > 0) {
        return gaps;
      }
      if (!(await this.loaderLocator().isVisible())) {
        gaps.push(sample * 250);
      }
      await this.page.waitForTimeout(250);
    }
    throw new Error("the 3ds challenge never opened");
  }

  errorMessageLocator() {
    return this.page.locator(CARD_ERROR_MESSAGE_SELECTOR);
  }

  async expectDeclineMessageToBeShown() {
    await expect(this.errorMessageLocator()).toBeVisible();
  }
}
