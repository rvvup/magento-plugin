import CheckoutPage from "../CheckoutPage";

export default class CardCheckout {
  constructor(page) {
    this.page = page;
    this.checkoutPage = new CheckoutPage(page);
  }

  async checkout() {
    await this.checkoutPage.selectCard();
    // Credit card form
    await this.page
      .frameLocator(".st-card-number-iframe")
      .getByLabel("Card Number")
      .fill("4900 4900 0000 0667");
    await this.page
      .frameLocator(".st-expiration-date-iframe")
      .getByLabel("Expiration Date")
      .fill("1233");
    await this.page
      .frameLocator(".st-security-code-iframe")
      .getByLabel("Security Code")
      .fill("123");

    await this.checkoutPage.pressPlaceOrder();
    // OTP form
    await this.page
      .frameLocator("#tp-3ds-challenge-iframe")
      .getByPlaceholder("Enter code here")
      .fill("1234");
    await this.page
      .frameLocator("#tp-3ds-challenge-iframe")
      .getByRole("button", { name: "SUBMIT" })
      .click();
  }

  async checkoutUsingFrictionless3DsCard() {
    await this.checkoutPage.selectCard();
    // Credit card form
    await this.page
      .frameLocator(".st-card-number-iframe")
      .getByLabel("Card Number")
      .fill("4000 0000 0000 2701");
    await this.page
      .frameLocator(".st-expiration-date-iframe")
      .getByLabel("Expiration Date")
      .fill("1233");
    await this.page
      .frameLocator(".st-security-code-iframe")
      .getByLabel("Security Code")
      .fill("123");

    await this.checkoutPage.pressPlaceOrder();
  }

  async checkoutUsingInvalidCard() {
    await this.checkoutPage.selectCard();
    // Credit card form
    await this.page
      .frameLocator(".st-card-number-iframe")
      .getByLabel("Card Number")
      .fill("4900490000000519");
    await this.page
      .frameLocator(".st-expiration-date-iframe")
      .getByLabel("Expiration Date")
      .fill("1233");
    await this.page
      .frameLocator(".st-security-code-iframe")
      .getByLabel("Security Code")
      .fill("123");

    await this.checkoutPage.pressPlaceOrder();
  }

  async checkoutUsingInvalidCardFailsAt3DS() {
    await this.checkoutPage.selectCard();
    // Credit card form
    await this.page
      .frameLocator(".st-card-number-iframe")
      .getByLabel("Card Number")
      .fill("4900490000000568");
    await this.page
      .frameLocator(".st-expiration-date-iframe")
      .getByLabel("Expiration Date")
      .fill("1233");
    await this.page
      .frameLocator(".st-security-code-iframe")
      .getByLabel("Security Code")
      .fill("123");

    await this.checkoutPage.pressPlaceOrder();

    await this.page
      .frameLocator("#tp-3ds-challenge-iframe")
      .getByPlaceholder("Enter code here")
      .fill("1234");
    await this.page
      .frameLocator("#tp-3ds-challenge-iframe")
      .getByRole("button", { name: "SUBMIT" })
      .click();
  }
}
