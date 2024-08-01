import Cart from '../Cart';
import GoTo from "../GoTo";

export default class PaypalCheckout {
  constructor(page) {
    this.page = page;
  }

  async pressPaypalButton() {
    const popupPromise = this.page.waitForEvent("popup");
    const paypalFrame = this.page.frameLocator(".rvvup-paypal-express-button-container [title='PayPal']").first();
    await paypalFrame.getByRole("link", { name: "PayPal" }).click();

    const popup = await popupPromise;
    await this.acceptPayment(await popup);

    await popup.waitForEvent("close");
  }

  async processMiniCartPaypalExpress() {
      const cart = new Cart(this.page);
      await cart.addStandardItemToCart();
      const popupPromise = this.page.waitForEvent("popup");

      await this.page.locator("div.minicart-wrapper a.action.showcart").click();
      const paypalFrame = this.page.frameLocator(".rvvup-paypal-minicart-block-container [title='PayPal']").first();
      await paypalFrame.getByRole("link", { name: "PayPal" }).click();

      const popup = await popupPromise;
      await this.acceptPayment(await popup);
      await popup.waitForEvent("close");
    }
  async processCartPaypalExpress() {
      const cart = new Cart(this.page);
      await cart.addStandardItemToCart();
      await new GoTo(this.page).cart();
      const popupPromise = this.page.waitForEvent("popup");

      await this.page.locator("div.minicart-wrapper a.action.showcart").click();
      const paypalFrame = this.page.frameLocator(".rvvup-paypal-cart-block-container [title='PayPal']").first();
      await paypalFrame.getByRole("link", { name: "PayPal" }).click();

      const popup = await popupPromise;
      await this.acceptPayment(await popup);
      await popup.waitForEvent("close");
    }

  async acceptPayment(popup) {
    await popup
      .getByPlaceholder("Email")
      .fill("sb-uqeqf29136249@personal.example.com");
    if (await popup.getByRole("button", { name: "Next" }).isVisible()) {
      await popup.getByRole("button", { name: "Next" }).click();
    }
    await popup.getByPlaceholder("Password").fill("h5Hc/b8M");
    await popup.getByRole("button", { name: "Log In" }).click();

    await popup.getByTestId("submit-button-initial").click();
  }

  async checkoutUsingCard() {
    const paypalFrame = this.page.frameLocator("[title='PayPal']").first();
    await paypalFrame
      .getByRole("link", { name: "Debit or Credit Card" })
      .click();

    const paypalCardForm = this.page.frameLocator("[title='paypal_card_form']");
    await paypalCardForm.getByLabel("Card number").fill("4698 4665 2050 8153");
    await paypalCardForm.getByLabel("Expires").fill("1125");
    await paypalCardForm.getByLabel("Security code").fill("141");
    await paypalCardForm.getByLabel("Mobile").fill("1234567890");

    if (await paypalCardForm.getByPlaceholder("First name").isVisible()) {
      await paypalCardForm.getByPlaceholder("First name").fill("John");
      await paypalCardForm.getByPlaceholder("Last name").fill("Doe");
      await paypalCardForm
        .getByPlaceholder("Address line 1")
        .fill("123 Main St");
      await paypalCardForm.getByPlaceholder("Town/City").fill("London");
      await paypalCardForm.getByPlaceholder("Postcode").fill("SW1A 1AA");
      await paypalCardForm
        .getByPlaceholder("Email")
        .fill("johndoe@example.com");
    }
    await paypalCardForm
      .getByRole("button", { name: /Buy Now|Continue/i })
      .click();
  }
}
