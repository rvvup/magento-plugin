/*
 * The Rvvup hosted payment page shows a OneTrust cookie banner inside the
 * payment iframe. It sits over the page and intercepts pointer events, so it
 * has to be dismissed before clicking anything in the iframe.
 */
export async function rejectCookies(frame) {
  await frame.getByRole("button", { name: "Reject all cookies" }).click();
}
