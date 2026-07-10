import { expect } from "@playwright/test";
import CheckoutPage from "../CheckoutPage";

export const CARD_FORM_CONTAINER_SELECTOR = "#rvvup-card-form-container";
export const CARD_SUBMIT_BUTTON_SELECTOR = "#submit-card";
export const CARD_ERROR_MESSAGE_SELECTOR = '[data-ui-id="message-error"]';

// Acquirer's happy path test card, see Test/End-2-End/CARD_TESTING.md.
export const FOUR_ONE_ONE_ONE_TEST_CARD_NUMBER = "4111 1111 1111 1111";

// TODO: confirm the current Basis Theory 3DS test card number(s) from
// https://developers.basistheory.com/docs/api/testing#3ds-test-cards before running against the
// sandbox. Basis Theory test cards are not yet perfectly aligned with the acquirer's own test
// cards, so the 3DS journey is expected to run and the acquirer is expected to decline the
// payment. See Test/End-2-End/CARD_TESTING.md for the full caveat.
export const BASIS_THEORY_3DS_TEST_CARD_NUMBER = "4000 0000 0000 0002";

// TODO: confirm a card number that is declined without triggering a 3DS challenge, so the
// "declined" scenario can be asserted independently from the 3DS journey scenario above.
export const INLINE_DECLINED_TEST_CARD_NUMBER = "4000 0000 0000 0119";

/**
 * Drives the INLINE card flow rendered by
 * src/view/frontend/web/js/view/payment/method-renderer/card.js, i.e. the Basis Theory SDK
 * fields mounted into #rvvup-card-form-container, submitted via #submit-card.
 *
 * The card number/expiry/cvc inputs themselves live inside an iframe injected by the Basis
 * Theory SDK. The exact iframe title/selector and field labels below are a best guess and are
 * marked with TODOs; confirm them against a live sandbox run and update accordingly.
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

  cardFieldsFrame() {
    // TODO: confirm the exact Basis Theory iframe selector against a live sandbox run. This
    // assumes a single iframe is injected directly into the mount container.
    return this.page.frameLocator(`${CARD_FORM_CONTAINER_SELECTOR} iframe`);
  }

  async fillCardDetails(cardNumber, expiry = "12/33", cvc = "123") {
    const cardFieldsFrame = this.cardFieldsFrame();

    // TODO: confirm the exact accessible labels Basis Theory renders for these fields.
    await cardFieldsFrame.getByLabel(/card number/i).fill(cardNumber);
    await cardFieldsFrame.getByLabel(/expiry|expiration/i).fill(expiry);
    await cardFieldsFrame.getByLabel(/security code|cvc|cvv/i).fill(cvc);
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

  async expectThreeDsChallengeToOpen() {
    // TODO: confirm the exact 3DS challenge iframe title/selector against a live sandbox run.
    await expect(
      this.page.frameLocator('iframe[title="3DS Challenge"]').locator("body"),
    ).toBeVisible();
  }

  errorMessageLocator() {
    return this.page.locator(CARD_ERROR_MESSAGE_SELECTOR);
  }

  async expectDeclineMessageToBeShown() {
    await expect(this.errorMessageLocator()).toBeVisible();
  }
}
