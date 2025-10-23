import { expect } from "@playwright/test";
import AdminLogin from "./Admin/AdminLogin";

export default class GoTo {
  constructor(page) {
    this.page = page;
    this.product = new GoToProduct(page);
  }

  admin(username) {
    return new GoToAdmin(this.page, username);
  }

  async checkout() {
    await this.page.goto("./checkout");
  }

  async cart() {
    await this.page.goto("./checkout/cart");
  }
}
class GoToProduct {
  constructor(page) {
    this.page = page;
    this.standardProducts = {
      cheap: "./affirm-water-bottle.html",
      "medium-priced": "./fusion-backpack.html",
    };
  }

  async standard(productType = "cheap") {
    await this.page.goto(this.standardProducts[productType]);
  }

  async configurable() {
    await this.page.goto("./hero-hoodie.html");
  }
}

class GoToAdmin {
  constructor(page, username) {
    this.page = page;
    this.username = username;
  }

  async ordersList() {
    await new AdminLogin(this.page).login(this.username);

    await this.page.getByRole("link", { name: "Sales" }).click();
    await this.page.getByRole("link", { name: "Orders" }).click();
  }

  async order(orderId) {
    await this.ordersList();
    await expect(
      this.page.locator(
        "#container > .admin__data-grid-outer-wrap > .admin__data-grid-loading-mask > .spinner",
      ),
    ).not.toBeVisible();
    await this.page
      .getByRole("textbox", { name: "Search by keyword" })
      .fill(orderId);
    await expect(
      this.page.locator(
        "#container > .admin__data-grid-outer-wrap > .admin__data-grid-loading-mask > .spinner",
      ),
    ).not.toBeVisible();
    await this.page.getByRole("button", { name: "Search" }).click();
    await expect(this.page.locator("#container")).toContainText(
      "1 records found",
    );
    await expect(
      this.page.locator(
        "#container > .admin__data-grid-outer-wrap > .admin__data-grid-loading-mask > .spinner",
      ),
    ).not.toBeVisible();
    await this.page.getByRole("link", { name: "View" }).click();
  }

  async creditMemoForOrder(orderId) {
    await this.order(orderId);
    await this.page.waitForTimeout(500);
    await this.page.getByRole("link", { name: "Invoices" }).click();
    await expect(
      this.page.locator("#sales_order_view_tabs_order_invoices_content"),
    ).toContainText("1 records found");
    await this.page.getByRole("link", { name: "View" }).click();
    await this.page.waitForTimeout(500);
    await this.page.getByRole("button", { name: "Credit Memo" }).click();
  }
}
