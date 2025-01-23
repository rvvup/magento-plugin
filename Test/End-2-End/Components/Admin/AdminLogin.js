import { expect } from "@playwright/test";

export default class AdminLogin {
  constructor(page) {
    this.page = page;
  }

  async login(username) {
    await this.page.goto("./admin");
    await this.page.getByPlaceholder("user name").fill(username);
    await this.page.getByPlaceholder("password").fill("password1");
    await this.page.getByPlaceholder("password").press("Enter");
    await this.page.waitForTimeout(1000);
    await expect(
      this.page.locator(".admin__form-loading-mask > .spinner").first(),
    ).toBeHidden();
    try {
      await expect(
        this.page.getByRole("button", { name: "Don't Allow" }),
      ).toBeVisible({ timeout: 1000 });
      await this.page.getByRole("button", { name: "Don't Allow" }).click();
    } catch (e) {
      //ignore
    }
  }
}
