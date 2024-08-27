import {expect} from "@playwright/test";

export default class AdminLogin {
    constructor(page) {
        this.page = page;
    }

    async login() {
        await this.page.goto("./admin");
        await this.page.getByPlaceholder('user name').fill('admin');
        await this.page.getByPlaceholder('password').fill('password1');
        await this.page.getByPlaceholder('password').press('Enter');
        await this.page.waitForTimeout(1000);
        await expect(this.page.locator('.admin__form-loading-mask > .spinner').first()).toBeHidden();
        try {
            await expect(this.page.getByRole('button', {name: "Don't Allow"})).toBeVisible({timeout: 1000});
            console.log("Detected Magento Usage Popup, clicking don't allow.")
            await this.page.getByRole('button', {name: "Don't Allow"}).click();
        } catch (e) {
            console.log("No Magento Usage popup, so ignoring it.")
        }
    }
}

