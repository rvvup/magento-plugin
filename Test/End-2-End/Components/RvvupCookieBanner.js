import { errors } from "@playwright/test";

/**
 * The Rvvup checkout (served inside the #rvvup_iframe-* modal) shows a OneTrust cookie banner
 * whose overlay covers the modal and intercepts every click on it, so it has to be rejected
 * before interacting with the modal. The consent is remembered for the browser context, which
 * means the banner may not show up at all (e.g. on a second tab), so a banner that never
 * appears is not an error.
 *
 * @param frame a frame locator for the Rvvup modal iframe
 */
export async function rejectRvvupCookieBanner(frame, timeout = 10000) {
  try {
    await frame
      .getByRole("button", { name: "Reject all cookies" })
      .click({ timeout });
  } catch (error) {
    if (!(error instanceof errors.TimeoutError)) {
      throw error;
    }
  }
}
