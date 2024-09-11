import {expect, test} from "@playwright/test";
import GoTo from "./Components/GoTo";
import {v7 as uuidv7} from 'uuid';
import CardCheckout from "./Components/PaymentMethods/CardCheckout";

test("payment link order", async ({browser, page}) => {
    await new GoTo(page).admin('e2e-tests-payment-link').ordersList();

    await page.getByRole('button', {name: 'Create New Order'}).click();
    await page.getByRole('button', {name: 'Create New Customer'}).click();
    await page.getByRole('button', {name: 'Add Products'}).click();
    await page.locator("#sales_order_create_search_grid_table tbody tr").nth(3).click();
    await page.getByRole('button', {name: 'Add Selected Product(s) to Order'}).click();
    await page.getByLabel('Email', {exact: true}).fill('virtual-terminal-' + uuidv7() + '@example.com');
    await page.getByRole('group', {name: 'Billing Address'}).getByLabel('First Name').fill('John');
    await page.getByRole('group', {name: 'Billing Address'}).getByLabel('Last Name').fill('Doe');
    await page.locator('#order-billing_address_street0').fill('123 Main St');
    await page.locator('#order-billing_address_street1').fill('Test');
    await page.getByRole('group', {name: 'Billing Address'}).getByLabel('City').fill('Birmingham');
    await page.getByRole('group', {name: 'Billing Address'}).getByLabel('Zip/Postal Code').fill('BZ1 1ZZ');
    await page.getByRole('group', {name: 'Billing Address'}).getByLabel('Phone Number').fill('0123456789');
    await page.getByRole('link', {name: 'Get available payment methods'}).click();
    await page.getByText('Rvvup Payment Link').click();
    await page.getByRole('link', {name: 'Get shipping methods and rates'}).click();
    await page.getByLabel('Fixed - £').click();
    await page.getByRole('button', {name: 'Submit Order'}).first().click();
    const paymentLink = (await (await page.getByText('This order requires payment, please pay using following button: ').first()).innerText()).replace("This order requires payment, please pay using following button: ", "")
    const context = await browser.newContext();
    const paymentLinkPage = await context.newPage()
    await paymentLinkPage.goto(paymentLink);
    await paymentLinkPage.getByRole('button', {name: 'Pay by Card'}).click();
    await paymentLinkPage.getByLabel('First name').fill('John');
    await paymentLinkPage.getByLabel('Last name').fill('Doe');
    await paymentLinkPage.getByLabel('Email').fill('john@doe.com');
    await paymentLinkPage.getByRole('button', {name: 'Billing Address'}).click();
    await paymentLinkPage.getByLabel('Name', {exact: true}).fill('John Doe');
    await paymentLinkPage.getByLabel('Address line 1').fill('111 Fake Street');
    await paymentLinkPage.getByLabel('City').fill('London');
    await paymentLinkPage.getByLabel('Postcode').fill('WC2R 0EX');
    await paymentLinkPage.getByRole('button', {name: 'Save changes'}).click();
    await paymentLinkPage.getByRole('button', {name: 'Continue'}).click();

    await CardCheckout.fillDefaultCard(paymentLinkPage);
    await page.waitForTimeout(1000);
    await paymentLinkPage.getByRole('button', {name: 'Submit'}).click();
    await CardCheckout.fillOtp(paymentLinkPage);
    await expect(paymentLinkPage.getByText("Payment Successful")).toBeVisible();
});
