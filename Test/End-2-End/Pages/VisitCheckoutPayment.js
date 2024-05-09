import {expect} from "@playwright/test";
import Cart from "../Components/Cart";

export default class VisitCheckoutPayment {
    constructor(page) {
        this.page = page;
    }

    async visit() {
        const cart = new Cart(this.page);
        await cart.addStandardItemToCart();

        await this.page.goto('./checkout');

        await this.page.getByRole('textbox', { name: 'Email Address' }).fill('johndoe@example.com');
        await this.page.getByLabel('First name').fill('John');
        await this.page.getByLabel('Last name').fill('Doe');
        await this.page.getByLabel('Street Address: Line 1').fill('123 Main St');
        await this.page.getByLabel('City').fill('London');
        await this.page.getByLabel('Country').selectOption('United Kingdom');
        await this.page.getByLabel('ZIP').fill('SW1A 1AA');
        await this.page.getByLabel('Phone number').fill('+447500000000');

        await this.page.getByLabel('Fixed').click();

        await this.page.getByRole('button', { name: 'Next' }).click();

        await expect(this.page.getByText('Payment Method', { exact: true })).toBeVisible();
    }
}
