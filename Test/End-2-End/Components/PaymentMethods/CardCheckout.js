import CheckoutPage from "../CheckoutPage";

export default class CardCheckout {
  constructor(page) {
    this.page = page;
    this.checkoutPage = new CheckoutPage(page);
  }

  async checkout() {
    await this.checkoutPage.selectCard();

    await CardCheckout.fillDefaultCard(this.page);

    await this.checkoutPage.pressPlaceOrder();
    await CardCheckout.fillOtp(this.page);
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
      .fill("4000 0000 0000 2537");
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

    static async fillDefaultCard(locator) {
      await locator
          .frameLocator(".st-card-number-iframe")
          .getByLabel("Card Number")
          .fill("4111 1111 1111 1111");
      await locator
          .frameLocator(".st-expiration-date-iframe")
          .getByLabel("Expiration Date")
          .fill("1233");
      await locator
          .frameLocator(".st-security-code-iframe")
          .getByLabel("Security Code")
          .fill("123");
  }

    static async fillOtp(locator){
        await locator
            .frameLocator('iframe[title="Bank Authentication"]')
            .getByPlaceholder(" Enter Code Here")
            .fill("1234");
        await locator
            .frameLocator('iframe[title="Bank Authentication"]')
            .getByRole("button", { name: "SUBMIT" })
            .click();
    }
}
