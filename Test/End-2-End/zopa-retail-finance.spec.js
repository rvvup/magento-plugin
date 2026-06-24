import { expect, test } from "@playwright/test";
import GoTo from "./Components/GoTo";

async function selectSwatchWithRetry(
  page,
  label,
  maxRetries = 5,
  delayMs = 300,
) {
  const swatch = page.locator(`.swatch-option[data-option-label=${label}]`);
  for (let attempt = 0; attempt < maxRetries; attempt++) {
    await swatch.click();
    try {
      await expect(swatch).toHaveClass(/selected/, { timeout: delayMs });
      return;
    } catch (e) {
      if (attempt === maxRetries - 1) throw e;
      await page.waitForTimeout(delayMs);
    }
  }
}

async function monthlyPrice(container) {
  const text = await container.getByText(/p\/m/).first().textContent();
  return text.match(/£\d+\.\d{2} p\/m/)[0];
}

test("renders the ZRF widget on the configurable product page", async ({
  page,
}) => {
  await new GoTo(page).product.configurable();

  await expect(page.locator("#rvvup-zrf-widget-container")).toBeHidden();

  await selectSwatchWithRetry(page, "XS");
  await expect(
    page.locator(".swatch-option[data-option-label=XS].selected"),
  ).toBeVisible();

  await selectSwatchWithRetry(page, "Black");
  await expect(
    page.locator(".swatch-option[data-option-label=Black].selected"),
  ).toBeVisible();

  await expect(page.locator("#rvvup-zrf-widget-container")).toBeHidden();

  await selectSwatchWithRetry(page, "S");
  await expect(
    page.locator(".swatch-option[data-option-label=S].selected"),
  ).toBeVisible();

  await selectSwatchWithRetry(page, "Black");
  await expect(
    page.locator(".swatch-option[data-option-label=Black].selected"),
  ).toBeVisible();
  const container = page.locator("#rvvup-zrf-widget-container");
  await expect(container).toBeVisible();
  const priceAtSmall = await monthlyPrice(container);

  await selectSwatchWithRetry(page, "XL");
  await expect.poll(() => monthlyPrice(container)).not.toBe(priceAtSmall);

  await selectSwatchWithRetry(page, "Black");
  await expect(
    page.locator(".swatch-option[data-option-label=Black].selected"),
  ).toBeVisible();

  await expect(page.locator("#rvvup-zrf-widget-container")).toBeHidden();
});

test("does not render on standard product page for cheap product", async ({
  page,
}) => {
  await new GoTo(page).product.standard("cheap");

  await page.waitForTimeout(1000);

  await expect(page.locator("#rvvup-zrf-widget-container")).toBeHidden();
});

test("renders the ZRF widget on the standard product page", async ({
  page,
}) => {
  await new GoTo(page).product.standard("medium-priced");

  await expect(page.locator("#rvvup-zrf-widget-container")).toBeVisible();

  await expect(
    page
      .locator("#rvvup-zrf-widget-container")
      .getByText(/Or from £\d+\.\d{2} p\/m, at \d+\.\d{2}%/),
  ).toBeVisible();
});
