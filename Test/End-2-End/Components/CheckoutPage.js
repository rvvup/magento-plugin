import {expect} from "@playwright/test";

export default class CheckoutPage {
    constructor(page) {
        this.page = page;
    }

    async getGrandTotalElement() {
        return await this.page.getByRole('row', { name: 'Order Total £' }).locator('span')
    }
    async getGrandTotalValue(stripSign = false) {
        const value = await (await this.getGrandTotalElement()).innerText();
        return stripSign ? value.replace('£', '') : value;
    }

    async applyDiscountCode(discountCode) {
        await this.page.getByText('Apply Discount Code').click();
        await this.page.getByPlaceholder('Enter discount code').fill(discountCode);
        await this.page.getByRole('button', { name: 'Apply Discount' }).click();
        await expect(await this.page.getByText('Your coupon was successfully applied.')).toBeVisible();
    }
}
