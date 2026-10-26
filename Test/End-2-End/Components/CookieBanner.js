import { expect } from "@playwright/test";

/*
 * The Rvvup hosted payment page shows a OneTrust cookie banner inside the
 * payment iframe. It sits over the page and intercepts pointer events, so it
 * has to be dismissed before clicking anything in the iframe.
 */
export async function rejectCookies(frame) {
  await frame.getByRole("button", { name: "Reject all cookies" }).click();
}

/*
 * The banner can come back when the iframe navigates to its next page, so dismissing it once
 * before the first click is not enough. Dismiss it, when visible, before every click and retry the
 * pair, so a banner that appears in between does not leave the click stuck on its overlay.
 */
export async function clickDismissingCookies(frame, button) {
  const reject = frame.getByRole("button", { name: "Reject all cookies" });
  await expect(async () => {
    if (await reject.isVisible()) {
      await reject.click({ timeout: 5000 });
    }
    await button.click({ timeout: 5000 });
  }).toPass({ timeout: 60000 });
}
