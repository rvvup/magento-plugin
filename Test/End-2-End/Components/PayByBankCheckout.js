import {expect} from "@playwright/test";

export default class PayByBankCheckout {
    constructor(page) {
        this.page = page;
    }

    /*
    * On the checkout page, place a pay by bank order and complete it
     */
    async checkout() {
        await this.page.getByLabel('Pay by Bank').click();
        await this.page.getByRole('button', {name: 'Place order'}).click();
        const frame = this.page.frameLocator('#rvvup_iframe-rvvup_YAPILY');
        await frame.getByLabel('Mock Bank').click();
        await frame.getByRole('button', {name: 'Log in on this device'}).click();

        await this.page.waitForURL("**/checkout/onepage/success/");
        await expect(this.page.getByRole('heading', {name: 'Thank you for your purchase!'})).toBeVisible();
        await expect(this.page.getByText("Your payment is being processed and is pending confirmation. You will receive an email confirmation when the payment is confirmed.")).toBeVisible();

    }
}
