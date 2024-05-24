import {expect} from "@playwright/test";
import Cart from "../Components/Cart";
import GoTo from "../Components/GoTo";

export default class VisitCheckoutPayment {
    constructor(page) {
        this.page = page;
    }

    async visit() {
        await new Cart(this.page).addStandardItemToCart();

        await new GoTo(this.page).checkout();

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

    async visitCartWithMultipleProducts() {
        const cart = new Cart(this.page);
        await cart.addStandardItemToCart();
        await cart.addStandardItemToCart();
        await cart.addStandardItemToCart();

        await new GoTo(this.page).cart();
    }

    async visitCheckoutWithMultipleProducts() {
        const cart = new Cart(this.page);
        await cart.addStandardItemToCart();
        await cart.addStandardItemToCart();
        await cart.addStandardItemToCart();

        await new GoTo(this.page).checkout();

        await this.page.getByRole('textbox', { name: 'Email Address' }).fill('johndoe@example.com');
        await this.page.getByLabel('First name').fill('John');
        await this.page.getByLabel('Last name').fill('Doe');
        await this.page.getByLabel('Street Address: Line 1').fill('123 Main St');
        await this.page.getByLabel('City').fill('London');
        await this.page.getByLabel('Country').selectOption('United Kingdom');
        await this.page.getByLabel('ZIP').fill('SW1A 1AA');
        await this.page.getByLabel('Phone number').fill('+447500000000');

        await this.page.getByLabel('Free').click();

        await this.page.getByRole('button', { name: 'Next' }).click();

        await expect(this.page.getByText('Payment Method', { exact: true })).toBeVisible();
    }

    async visitWithoutShippingFee() {
        await new Cart(this.page).addStandardItemToCart();

        await new GoTo(this.page).checkout();

        await this.page.getByRole('textbox', { name: 'Email Address' }).fill('johndoe@example.com');
        await this.page.getByLabel('First name').fill('John');
        await this.page.getByLabel('Last name').fill('Doe');
        await this.page.getByLabel('Street Address: Line 1').fill('123 Main St');
        await this.page.getByLabel('City').fill('London');
        await this.page.getByLabel('Country').selectOption('United Kingdom');
        await this.page.getByLabel('ZIP').fill('SW1A 1AA');
        await this.page.getByLabel('Phone number').fill('+447500000000');

        await this.page.getByLabel('Free').click();

        await this.page.getByRole('button', { name: 'Next' }).click();

        await expect(this.page.getByText('Payment Method', { exact: true })).toBeVisible();
    }

    async visitAsClearpayUser() {
        await new Cart(this.page).addStandardItemToCart();

        await new GoTo(this.page).checkout();

        await this.page.getByRole('textbox', { name: 'Email Address' }).fill('debim90109@fretice.com');
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
