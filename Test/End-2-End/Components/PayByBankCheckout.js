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
        await frame.getByRole('button', { name: 'Mock Bank' }).click();
        await frame.getByRole('button', {name: 'Log in on this device'}).click();

        await this.page.waitForURL("**/checkout/onepage/success/");
        await expect(this.page.getByRole('heading', { name: 'Thank you for your purchase!' })).toBeVisible();
        await expect(this.page.getByText("Your payment is being processed and is pending confirmation. You will receive an email confirmation when the payment is confirmed.")).toBeVisible();
    }

    async decline() {
        await this.page.getByLabel('Pay by Bank').click();
        await this.page.getByRole('button', {name: 'Place order'}).click();

        const frame = this.page.frameLocator('#rvvup_iframe-rvvup_YAPILY');
        await frame.getByRole('button', { name: 'Natwest' }).click();
        await frame.getByRole('button', {name: 'Log in on this device'}).click();

        await this.page.getByRole('button', { name: 'Cancel' }).click();
        await expect(this.page.getByText('Payment Declined')).toBeVisible();
    }

    async declineInsufficientFunds() {
        await this.page.getByLabel('Pay by Bank').click();
        await this.page.getByRole('button', {name: 'Place order'}).click();

        const frame = this.page.frameLocator('#rvvup_iframe-rvvup_YAPILY');
        await frame.getByRole('button', { name: 'Natwest' }).click();
        await frame.getByRole('button', {name: 'Log in on this device'}).click();

        await this.page.locator('input#customer-number').pressSequentially('123456789012');
        await this.page.locator('button#customer-number-login').click();
        
        await this.page.locator('input#pin-1').pressSequentially('5');
        await this.page.locator('input#pin-2').pressSequentially('7');
        await this.page.locator('input#pin-3').pressSequentially('2');
        await this.page.locator('input#password-1').pressSequentially('4');
        await this.page.locator('input#password-2').pressSequentially('3');
        await this.page.locator('input#password-3').pressSequentially('6');
        await this.page.getByRole('button', { name: 'Continue' }).click();

        await this.page.locator('dl')
            .filter({ hasText: 'Account NameSydney Beard (Personal Savings)' })
            .getByRole('button', { name: 'Select account'}).click();
        await this.page.getByRole('button', { name: 'Confirm payment'}).click();

        await expect(this.page.getByText(/Insufficient funds/)).toBeVisible();
    }

    async exitModalBeforeCompletingTransaction() {
        await this.page.getByLabel('Pay by Bank').click();
        await this.page.getByRole('button', {name: 'Place order'}).click();

        const frame = this.page.frameLocator('#rvvup_iframe-rvvup_YAPILY');
        await frame.getByRole('button', { name: 'Natwest' }).click();

        // we want to open modal, open natwest in a new tab, close modal, 
        // complete natwest transaction, see where the redirect sends us to
        await frame.getByRole('button', {name: 'Log in on this device'}).click();

        await this.page.locator('input#customer-number').pressSequentially('123456789012');
        await this.page.locator('button#customer-number-login').click();
        
        await this.page.locator('input#pin-1').pressSequentially('5');
        await this.page.locator('input#pin-2').pressSequentially('7');
        await this.page.locator('input#pin-3').pressSequentially('2');
        await this.page.locator('input#password-1').pressSequentially('4');
        await this.page.locator('input#password-2').pressSequentially('3');
        await this.page.locator('input#password-3').pressSequentially('6');
        await this.page.getByRole('button', { name: 'Continue' }).click();
    }
}
