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

### 4761 3699 8032 0253 - 3DS challenge happy path

This card runs the full 3DS challenge journey and then authorises:

1. Add a product to the cart and go to checkout.
2. Fill in shipping details and select "Pay by Card".
3. Enter card number `4761 3699 8032 0253`, any future expiry (e.g. `12/33`) and any CVC
   (e.g. `123`).
4. Submit the payment.
5. The challenge opens in `iframe#challengeIframe`, which embeds Basis Theory's
   `3ds.basistheory.com/pages/challenge.html`, which in turn embeds the acquirer's ACS page
   (`acs.ravelin.com`). Enter passcode `1234` (the sandbox ACS shows it as the input's
   placeholder, "hint: 1234") and press Submit.

Expected result: the same as the frictionless card, the order is placed, the shopper is
redirected to the Rvvup return URL and the confirmation page is shown, with the order in
Processing.

The full screen loader stays up for the whole journey, including the several seconds the SDK
spends on the 3DS device check before the challenge appears. It steps aside only while the
challenge is on screen asking the shopper for input, and comes back once they have answered.

Cancelling the challenge instead returns the shopper to the checkout with the card form usable
again. No shopper-facing message is shown for a cancellation today.

Covered by:
- `it completes an inline card payment end to end through the 3ds challenge`
- `it registers the 3ds card order as processing in the admin`
- `it keeps a loader on screen from card submit until the 3ds challenge opens`
- `it hands the card form back when the 3ds challenge is cancelled`

## Known TODOs before running against a live sandbox

The inline card selectors in `Test/End-2-End/Components/PaymentMethods/CardCheckout.js` (Basis
Theory field iframes, the `#challengeIframe` 3DS challenge and the ACS passcode form) were
confirmed against the sandbox. Still open:

- Whether the hosted card page (inside `#rvvup_iframe-rvvup_CARD`) still exposes the pre-SDK
  SecureTrading-style card iframes (`.st-card-number-iframe` etc.) assumed in
  `HostedCardCheckout.js`, and the exact submit button label.
- A card number that reliably declines without a 3DS challenge, so the decline path can be
  asserted independently of the 3DS journey. `INLINE_DECLINED_TEST_CARD_NUMBER` in
  `CardCheckout.js` is still a placeholder and its test is `fixme`.

## Running the suite

```bash
# From the module directory
./scripts/run-e2e-tests.sh          # headless
./scripts/run-e2e-tests.sh --ui     # Playwright UI mode
```

This spins up the module's own docker-compose Magento store and points Playwright at
`TEST_BASE_URL` (see `scripts/run-e2e-tests.sh` and `playwright.config.js`). It requires a live
Rvvup sandbox merchant (via `RVVUP_API_KEY`) and is not run as part of this authoring task.
