import {expect, test} from "@playwright/test";
import GoTo from "./Components/GoTo";
import {v7 as uuidv7} from 'uuid';
import CardCheckout from "./Components/PaymentMethods/CardCheckout";

test.describe.configure({mode: 'serial'});


test("virtual terminal order", async ({page}) => {
    await new GoTo(page).admin.ordersList();
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
    await page.getByText('Rvvup Virtual Terminal').click();
    await page.getByRole('link', {name: 'Get shipping methods and rates'}).click();
    await page.getByLabel('Fixed - £').click();
    await page.getByRole('button', {name: 'Submit Order'}).first().click();
    await page.getByRole('button', {name: 'Launch virtual terminal'}).click();
    await CardCheckout.fillDefaultCard(page.frameLocator('.rvvup-embedded-checkout iframe'));
    await page.waitForTimeout(1000);
    await page.frameLocator('.rvvup-embedded-checkout iframe').getByRole('button', {name: 'Submit'}).click();
    await expect(page.locator('#order_status')).toHaveText('Processing');
});
