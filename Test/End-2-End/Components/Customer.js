import { expect } from "@playwright/test";
import { v7 as uuidv7 } from "uuid";

const PASSWORD = "Rvvup-e2e-Passw0rd!";

/**
 * Every test registers its own customer, so the saved cards of one test can never be seen or used
 * by another.
 */
export default class Customer {
  constructor(page) {
    this.page = page;
  }

  async register() {
    // The form is only usable once the page scripts have run: input typed before that is lost when
    // they initialise, and the click then does nothing. So the whole attempt is retried on a fresh
    // page until the registration redirects to the account, each attempt with its own email.
    await expect(async () => {
      await this.page.goto("./customer/account/create");
      await this.page.waitForLoadState("networkidle");
      await this.page.locator("#firstname").fill("Saved");
      await this.page.locator("#lastname").fill("Cards");
      await this.page.locator("#email_address").fill(`${uuidv7()}@example.com`);
      await this.page.locator("#password").fill(PASSWORD);
      await this.page.locator("#password-confirmation").fill(PASSWORD);
      await this.page.getByRole("button", { name: "Create an Account" }).click();
      await expect(this.page).toHaveURL(/customer\/account\/?$/, {
        timeout: 15000,
      });
    }).toPass({ timeout: 90000 });

    await expect(this.page.getByText("Thank you for registering")).toBeVisible();
  }

  async openStoredPaymentMethods() {
    await this.page.goto("./vault/cards/listaction/");
    await this.page.waitForLoadState("networkidle");
    await expect(
      this.page.getByRole("heading", { name: "Stored Payment Methods" }),
    ).toBeVisible();
  }

  storedCards() {
    return this.page.locator("table.table-credit-cards tbody tr");
  }

  async deleteStoredCard() {
    const confirmation = this.page.locator(".modal-popup._show");
    const removed = this.page.getByText(
      "Stored Payment Method was successfully removed",
    );

    await this.storedCards().first().getByRole("button", { name: "Delete" }).click();

    // The delete button asks for a confirmation once the page scripts have initialised it. A click
    // that lands before that submits the form straight away, so both outcomes are accepted.
    await expect(confirmation.or(removed)).toBeVisible();
    if (await confirmation.isVisible()) {
      await confirmation.getByRole("button", { name: "Delete" }).click();
    }

    await expect(removed).toBeVisible();
  }
}
