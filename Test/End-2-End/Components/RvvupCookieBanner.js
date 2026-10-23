const registeredPages = new WeakSet();

/**
 * The Rvvup checkout (served inside the #rvvup_iframe-* modal) shows a OneTrust cookie banner
 * whose overlay covers the modal and intercepts every click on it. It can appear at any point
 * while the modal is open, so rather than dismissing it at a fixed step this registers a handler
 * that rejects the cookies as soon as the banner blocks an action on the page.
 */
export function rejectRvvupCookieBanner(page) {
  if (registeredPages.has(page)) {
    return;
  }
  registeredPages.add(page);

  const rejectAllButton = page
    .frameLocator('iframe[id^="rvvup_iframe-"]')
    .first()
    .getByRole("button", { name: "Reject all cookies" });

  page.addLocatorHandler(rejectAllButton, async () => {
    await rejectAllButton.click();
  });
}
