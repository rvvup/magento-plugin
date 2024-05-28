import { expect } from "@playwright/test";

export default class CheckoutPage {
  constructor(page) {
    this.page = page;
  }

  async getGrandTotalElement() {
    return await this.page
      .getByRole("row", { name: "Order Total £" })
      .locator("span");
  }
  async getGrandTotalValue(stripSign = false) {
    const value = await (await this.getGrandTotalElement()).innerText();
    return stripSign ? value.replace("£", "") : value;
  }

  async applyDiscountCode(discountCode) {
    await this.page.getByText("Apply Discount Code").click();
    await this.page.getByPlaceholder("Enter discount code").fill(discountCode);
    await this.page.getByRole("button", { name: "Apply Discount" }).click();
    await expect(
      await this.page.getByText("Your coupon was successfully applied."),
    ).toBeVisible();
  }

  async selectPayByBank() {
    await this.page.getByLabel("Pay by Bank").click();
  }

  async selectRvvupTestMethod() {
    await this.page.getByLabel("Rvvup Payment Method").click();
  }

  async selectClearpay() {
    await this.page.getByLabel("Clearpay").click();
  }

  async selectCard() {
    await this.page.getByLabel("Pay by Card").click();
  }

  async selectPaypal() {
    await this.page.getByLabel("PayPal", { exact: true }).click();
  }

  async pressPlaceOrder() {
    await this.page.getByRole("button", { name: "Place order" }).click();
  }
}
