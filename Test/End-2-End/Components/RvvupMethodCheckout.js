import {expect} from "@playwright/test";

export default class RvvupMethodCheckout {
    constructor(page) {
        this.page = page;
    }

    /*
    * On the checkout page, place a pay by bank order and complete it
     */
    async checkout() {
        await this.page.getByLabel('Rvvup Payment Method').click();
        await this.page.getByRole('button', {name: 'Place order'}).click();
        const frame = this.page.frameLocator('#rvvup_iframe-rvvup_FAKE_PAYMENT_METHOD');
        await frame.getByRole('button', {name: 'Pay now'}).click();

        await this.page.waitForURL("**/checkout/onepage/success/");
        await expect(this.page.getByRole('heading', {name: 'Thank you for your purchase!'})).toBeVisible();
        await expect(this.page.getByText("Your payment is being processed and is pending confirmation. You will receive an email confirmation when the payment is confirmed.")).toBeHidden();

    }
}
