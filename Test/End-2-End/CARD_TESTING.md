# Manual Card Testing Guide

This guide covers manual and automated (Playwright) testing of the Rvvup Card payment method
against the Rvvup sandbox. It complements `pay_by_card.spec.js`, which encodes the same scenarios
as automated checks.

## The two card flows

Card payments run through one of two flows, chosen server-side by the merchant's Rvvup settings
(`settings.rvvup_card.flow`, read in `src/Model/ConfigProvider.php` and
`src/ViewModel/Assets.php`). This plugin never sets the flow itself, it only reacts to what the
Rvvup API returns for the merchant tied to the configured API key:

- **INLINE** - card fields are rendered directly on the checkout payment step using the Rvvup
  JS SDK (`src/view/frontend/web/js/view/payment/method-renderer/card.js`). The SDK mounts a
  Basis Theory card form into `#rvvup-card-form-container`, submitted via the `#submit-card`
  button.
- **HOSTED** - the standard Rvvup payment method component
  (`src/view/frontend/web/js/view/payment/method-renderer/rvvup-method.js`) opens a modal
  containing an iframe (`#rvvup_iframe-rvvup_CARD`) pointing at Rvvup's own hosted card page.

### Switching a sandbox merchant between INLINE and HOSTED

The flow is not a Magento admin setting, it lives on the merchant record in Rvvup's backend. To
test one flow vs the other:

1. Ask the Rvvup account/dashboard team to set the `card.flow` setting to `INLINE` or `HOSTED`
   for the sandbox merchant you're using, or use two separate sandbox merchants pre-configured
   one for each flow.
2. Point the docker store at the merchant you want to test by setting the `RVVUP_API_KEY`
   environment variable to that merchant's API key before starting the stack (see
   `docker-compose.yml`, which forwards `RVVUP_API_KEY` into the Magento container).
3. Start (or restart) the store so the new key is picked up, then run the suite:

   ```bash
   RVVUP_API_KEY=<sandbox-merchant-api-key> ./scripts/run-e2e-tests.sh
   # or, to watch it run:
   RVVUP_API_KEY=<sandbox-merchant-api-key> ./scripts/run-e2e-tests.sh --ui
   ```

`pay_by_card.spec.js` has one `test.describe` block per flow. Whichever flow the current merchant
is configured for is the one that will actually pass; the other block's tests are expected to
fail (or need to be skipped) until you switch merchants, since flow selection is entirely
server-side and cannot be toggled per test run.

## Test cards and expected outcomes

### 4111 1111 1111 1111 - happy path

This card should complete a payment end to end with no challenge:

1. Add a product to the cart and go to checkout.
2. Fill in shipping details and select "Pay by Card".
3. Enter card number `4111 1111 1111 1111`, any future expiry (e.g. `12/33`) and any CVC
   (e.g. `123`).
4. Submit the payment.

Expected result: the order is placed, the shopper is redirected to the Rvvup return URL, and the
order confirmation page ("Thank you for your purchase!") is shown with a completed order.

Covered by:
- `it renders the inline sdk card fields on the checkout payment step`
- `it completes an inline card payment end to end with the 4111 test card`
- `it still completes a hosted card payment through the modal flow`

### 3DS test cards - expected decline

Basis Theory publishes 3DS test cards at
https://developers.basistheory.com/docs/api/testing#3ds-test-cards. Use one of those numbers to
trigger the 3DS journey.

Expected result:
- The 3DS challenge journey opens (a challenge iframe/redirect is shown to the shopper).
- The acquirer declines the payment. This is visible as a reject on the `/card/auth` path on
  `api.dev.rvvuptech.com` (check the Rvvup sandbox logs/dashboard for that merchant).
- The plugin surfaces a shopper-facing failure message (no silent failure) and does **not**
  throw any JavaScript errors in the browser console.

**Important caveat:** Basis Theory's test card matrix is not yet perfectly aligned with the
acquirer's own test cards. As of this writing, a 3DS test card is expected to run the 3DS
journey and then be declined by the acquirer - that decline is the expected, successful outcome
of this test, not a bug. Do not treat the decline itself as a failure; treat a missing 3DS
journey, a silent failure, or a JS error as a failure.

Covered by:
- `it opens the 3ds journey for an inline 3ds test card and reports the decline to the shopper`
- `it shows a shopper facing message when the inline payment is declined`

## Known TODOs before running against a live sandbox

The Basis Theory card fields and the hosted card page are rendered by third parties (Basis
Theory's SDK, Rvvup's hosted page) and their exact DOM structure was not available while
authoring these specs. Before running the suite, confirm and update the following in
`Test/End-2-End/Components/PaymentMethods/CardCheckout.js` and
`Test/End-2-End/Components/PaymentMethods/HostedCardCheckout.js`:

- The Basis Theory iframe selector/title mounted into `#rvvup-card-form-container`
  (currently guessed as `${CARD_FORM_CONTAINER_SELECTOR} iframe`).
- The accessible labels Basis Theory renders for card number / expiry / CVC fields.
- The 3DS challenge iframe title/selector (currently guessed as
  `iframe[title="3DS Challenge"]`).
- Whether the hosted card page (inside `#rvvup_iframe-rvvup_CARD`) still exposes the pre-SDK
  SecureTrading-style card iframes (`.st-card-number-iframe` etc.) assumed in
  `HostedCardCheckout.js`, and the exact submit button label.
- The current Basis Theory 3DS test card number(s) and a card number that reliably declines
  without a 3DS challenge, both currently placeholders in `CardCheckout.js`.

## Running the suite

```bash
# From the module directory
./scripts/run-e2e-tests.sh          # headless
./scripts/run-e2e-tests.sh --ui     # Playwright UI mode
```

This spins up the module's own docker-compose Magento store and points Playwright at
`TEST_BASE_URL` (see `scripts/run-e2e-tests.sh` and `playwright.config.js`). It requires a live
Rvvup sandbox merchant (via `RVVUP_API_KEY`) and is not run as part of this authoring task.
