import {expect} from "@playwright/test";

export default class OrderConfirmation {
    constructor(page) {
        this.page = page;
    }

    async expectOnOrderConfirmation(isPending = false) {

        await this.page.waitForURL("**/checkout/onepage/success/");
        await expect(this.page.getByRole('heading', {name: 'Thank you for your purchase!'})).toBeVisible();
        if (isPending) {
            await expect(this.page.getByText("Your payment is being processed and is pending confirmation. You will receive an email confirmation when the payment is confirmed.")).toBeHidden();
        }
    }
}
