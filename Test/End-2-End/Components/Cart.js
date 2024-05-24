import { expect } from "@playwright/test";
import GoTo from "./GoTo";

export default class Cart {
  constructor(page) {
    this.page = page;
  }

  async addStandardItemToCart() {
    await new GoTo(this.page).product.standard();
    await this.page.getByRole("button", { name: "Add to cart" }).click();
    await expect(
      this.page.getByText(/You added [A-Za-z0-9 ]+ to your shopping cart/i),
    ).toBeVisible();
  }
}
