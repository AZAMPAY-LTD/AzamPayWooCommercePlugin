# AzamPay Gateway for WooCommerce — Developer Guide

A developer-oriented tour of this repository: what it is, how the pieces fit
together, how a payment actually flows, and what to watch out for when changing
things.

- **Plugin name:** AzamPay Gateway for WooCommerce
- **Version:** 1.1.6
- **Gateway ID:** `azampaymomo`
- **Text domain:** `azampay`
- **License:** GPL-3.0+
- **Requires:** WordPress 6.0+, WooCommerce 10.1.0+, PHP 8.0+, Node 20+ / npm 8.19.2+ (build only)
- **Supported currency:** `TZS` only

---

## 1. What the plugin does

It registers a WooCommerce payment gateway that collects Tanzanian mobile-money
(MoMo) payments through AzamPay's Checkout API. At checkout the shopper picks a
wallet (AzamPesa, HaloPesa, Tigo, Airtel, M-Pesa/Vodacom) and enters their phone
number. The plugin sends a *push* request to AzamPay; the shopper confirms on
their handset by entering their PIN. Payment confirmation arrives later,
asynchronously, on a **webhook** — never inline in the checkout response.

That asynchronous split is the single most important thing to understand about
this codebase:

| Step | Where it happens | Order status after |
| --- | --- | --- |
| Shopper submits checkout | `process_payment()` → `azampay_payment_processing()` | `pending` (or `on-hold` for downloadables) |
| Shopper confirms on phone | AzamPay's side, out of band | unchanged |
| AzamPay notifies the store | `process_webhooks()` | `processing`/`completed`, `on-hold`, or `failed` |

If the webhook URL is not configured on the AzamPay merchant account, orders will
sit in `pending` forever even though money moved. This is the #1 source of
"the plugin is broken" reports.

---

## 2. Repository layout

```
woocommerce-azampay.php                     # Plugin entry point: constants, bootstrap, admin notices
includes/
  class-woo-azampay-gateway.php             # AzamPay_Gateway — all gateway logic (the core file)
  class-woo-azampay-blocks-support.php      # AzamPay_Blocks_Support — Checkout Blocks integration
resources/js/blocks/                        # React SOURCE for the Blocks checkout (edit these)
  index.js                                  #   registerPaymentMethod() + Label component
  Content.jsx                                #   payment form + onPaymentSetup validation
  InputFields.jsx                            #   phone input + wallet radio buttons
  constants.js                                #   phone regex patterns (JS side)
assets/
  js/ui/wc-azampay-blocks.js                # BUILD OUTPUT — do not hand-edit
  js/ui/wc-azampay-blocks.asset.php         # BUILD OUTPUT — dependency/version manifest
  admin/js/azampay-admin.js                 # Hand-written jQuery that rewrites the settings screen
  public/css/azampay-styles.css             # Checkout styling (shared by legacy + blocks)
  public/images/*                           # Wallet logos (svg, plus vodacom-logo.png)
  public/docs/*.pdf                         # KYC form, Terms, Privacy Policy (linked from admin UI)
webpack.config.js                           # wp-scripts config + WooCommerce dependency extraction
phpcs.xml                                   # WooCommerce-Core / WordPress-Extra ruleset
package.json                                # Build scripts and dev dependencies
README.md / README.txt                      # WordPress.org readme (same content, two names)
```

There is **no PHP autoloader, no Composer, and no test suite.** Classes are
`require_once`'d explicitly, and PHP files are loaded from the two `add_action`
bootstraps in `woocommerce-azampay.php`.

---

## 3. Bootstrap and load order

`woocommerce-azampay.php` defines the constants and then hooks three things:

### `plugins_loaded` → `woo_azampay_init()`
1. Bails with an admin notice if WooCommerce is missing (`woo_azampay_missing_wc_notice`).
2. Bails with an admin notice if `WC_VERSION < WC_AZAMPAY_MIN_WC_VER` (`woo_azampay_wc_not_supported`).
3. Registers the "test mode still on" nag (`woo_azampay_testmode_notice`).
4. Calls `woocommerce_azampay()`.

`woocommerce_azampay()` is an unusual pattern worth knowing: the `Woo_AzamPay`
class is **declared inside the function body**, guarded by a `static $plugin`.
This means the class does not exist until `plugins_loaded` runs, so you cannot
reference `Woo_AzamPay` from code that runs earlier. It is a singleton
(`get_instance()`, private `__clone`/`__wakeup`) and its `init()`:

- `require_once`s the gateway class,
- registers the gateway via `woocommerce_payment_gateways`,
- adds `TZS` to `woocommerce_currencies` and its symbol via `woocommerce_currency_symbol`,
- adds the Settings link and the T&C / Privacy Policy row-meta links on the plugins screen.

`get_main_azampay_gateway()` memoizes a single `AzamPay_Gateway` instance so the
gateway is not constructed twice.

### `woocommerce_blocks_loaded` → Blocks registration
Loads `class-woo-azampay-blocks-support.php`, registers `AzamPay_Blocks_Support`
as a shared instance in the Blocks DI container, and registers it with the
`PaymentMethodRegistry`. Guarded by `class_exists()` on both the Blocks abstract
class and `AzamPay_Gateway`, so an old WooCommerce simply skips Blocks support.

### `before_woocommerce_init` → HPOS compatibility
Declares `custom_order_tables` compatibility. This is why all order data access
in the gateway uses the CRUD API (`$order->update_meta_data()` / `get_meta()` /
`save()`) rather than `update_post_meta()`. **Keep it that way** — using post
meta directly would break High-Performance Order Storage.

### Constants
| Constant | Meaning |
| --- | --- |
| `WC_AZAMPAY_VERSION` | `1.1.6` — also used as asset cache-buster |
| `WC_AZAMPAY_MIN_PHP_VER` / `MIN_WC_VER` / `FUTURE_MIN_WC_VER` | Compatibility floors |
| `WC_AZAMPAY_MAIN_FILE` / `ABSPATH` | Plugin file and directory |
| `WC_AZAMPAY_PLUGIN_URL` / `PLUGIN_PATH` | Untrailingslashed URL / filesystem path for assets |

---

## 4. `AzamPay_Gateway` — the core class

Extends `WC_Payment_Gateway`. `const ID = 'azampaymomo'` is the canonical
identifier; settings live in the option `woocommerce_azampaymomo_settings`.

### 4.1 Environments and endpoints

```php
test:  https://authenticator-sandbox.azampay.co.tz/   +  https://sandbox.azampay.co.tz/
prod:  https://authenticator.azampay.co.tz/           +  https://checkout.azampay.co.tz/
```

| Key | Path | Base |
| --- | --- | --- |
| `token` | `AppRegistration/GenerateToken` | auth URL |
| `partners` | `api/v1/Partner/GetPaymentPartners` | checkout URL |
| `mno` | `azampay/mno/checkout` | checkout URL |

The constructor picks a prefix (`test` or `prod`) from the `test_mode` setting and
reads the matching four credentials into `$client_credentials`:
`app_name`, `client_id`, `client_secret`, `callback_token`.

All outbound calls use `wp_remote_post` / `wp_remote_get` with
`HTTP_TIMEOUT = 15` seconds.

### 4.2 Lazy loading and caching (important)

Earlier versions fetched the token and the partner list **in the constructor**,
which meant every single admin and front-end page load made two blocking HTTP
calls. As of 1.1.6 nothing is fetched at construction time. Instead:

```
get_token_result()    → ensure_token()    → per-request memo → transient → generate_token()
get_partners_result() → ensure_partners() → per-request memo → transient → get_all_partners()
```

Cache keys are scoped by environment *and* a 12-char md5 of the credentials:

```php
azampay_token_{test|prod}_{cred_hash}
azampay_partners_{test|prod}_{cred_hash}
azampay_partners_{test|prod}_{cred_hash}_fail   // negative cache
```

Hashing the credentials means editing them automatically orphans the old cache —
you cannot serve a token minted from stale credentials.

TTL rules:

- **Token:** `compute_token_ttl()` defensively interprets the API's `data.expire`
  field, which may be epoch seconds (`> 1_000_000_000`), a seconds-to-live
  integer, or a datetime string. Result is clamped to `[60s, 1 day]` and refreshed
  `TOKEN_EXPIRY_SKEW = 60s` early. Falls back to `TOKEN_TTL_FALLBACK = 3540s`.
- **Partners:** `PARTNERS_TTL = 1 day` on success.
- **Partners failure:** `PARTNERS_FAIL_TTL = 60s` negative cache. Without this, a
  sandbox outage makes every checkout page load hang for the full timeout and
  hammers the API.
- **Failures are never cached for the token** — the next request retries.

`process_admin_options()` deletes all three transients so a settings save takes
effect immediately.

### 4.3 Sandbox flakiness workaround

`get_all_partners()` retries up to **3 times**. The AzamPay sandbox
intermittently answers `HTTP 200` with an *empty body*; the same request seconds
later returns the full array. The loop distinguishes three outcomes:

- 200 + decodable **array** → success, return.
- 200 + object with `status === 'Error'` → a genuine API error, return immediately
  (do not retry).
- anything else (empty body, non-200, `WP_Error`) → retry.

After exhaustion the message is either `"Could not get payment partners (empty
response)."` or the generic variant.

### 4.4 Payment partners

`$partners_dictionary` maps the API's partner name to the value sent as
`provider` on the checkout call:

```php
'Azampesa' => 'Azampesa',
'HaloPesa' => 'Halopesa',
'Tigopesa' => 'Tigo',
'Airtel'   => 'Airtel',
'vodacom'  => 'Mpesa',
```

`get_allowed_partners()` intersects the live API list with the merchant's
`allowed_partners` setting. Note the asymmetry: the **left-hand keys** are what
the API returns and what the settings array is keyed by; the **right-hand values**
are AzamPay's provider codes. Adding a wallet means touching this dictionary,
`process_admin_options()`, `admin_options()`'s checkbox loop, the icon list in
`AzamPay_Blocks_Support::get_partner_icons()`, and dropping a logo in
`assets/public/images/`.

AzamPesa is special-cased throughout: its checkbox is rendered `disabled` and
`process_admin_options()` hard-codes `'Azampesa' => true`, so it can never be
turned off. It also gets its own larger "Pay with AzamPesa" tile in the checkout
markup, separate from the row of smaller logos.

### 4.5 Settings screen

`init_form_fields()` declares the flat WooCommerce settings: `enabled`,
`instructions`, `autocomplete_order`, `test_mode`, and the two credential sets
(`prod_*` and `test_*`).

`admin_options()` renders extra chrome on top of `generate_settings_html()`:

- an `<h2>` heading,
- the **mandatory callback URL** the merchant must register with AzamPay:
  `{site_url}/?wc-api=wc_azampay_webhook`,
- a hidden `<tr>` containing the per-partner checkboxes,
- or, if the store currency is not TZS, an error panel instead of the form.

`assets/admin/js/azampay-admin.js` then reshapes that DOM with jQuery: it moves
the partner-checkbox row above the Test Mode toggle and un-hides it, injects
"Test Instructions" / "Production Instructions" rows linking to the sandbox
registration page and the bundled KYC PDF, and shows only the credential set
matching the current Test Mode state (toggling live on change). It reads
`wc_azampay_admin_params` (`id`, `kycUrl`), localized in `admin_scripts()`.

Because the partner checkboxes are injected outside the normal settings API,
they are read manually from `$_POST` in `process_admin_options()` and stored as a
single serialized `allowed_partners` array.

### 4.6 Legacy (shortcode) checkout — `payment_fields()`

Returns early unless `is_checkout()`. Resolves token + partners, enqueues the
stylesheet, prints the description, then:

- **Token failed** → disables the radio via inline jQuery and adds a WooCommerce
  notice. Code `203` (misconfiguration) shows admins a link to the settings page
  and everyone else "Contact store owner"; other codes surface the raw message as
  an `error`. `wc_has_notice()` guards against duplicates.
- **Partners failed** → error notice, no form.
- **Success** → builds the fieldset: a required `payment_number` text input, the
  AzamPesa tile, and a radio per remaining allowed wallet. Output is filtered
  through `wp_kses()` with an explicit `input` allowlist merged into
  `wp_kses_allowed_html('post')`, then re-enables the payment radio.

### 4.7 Blocks checkout

Two halves that must agree with each other.

**PHP — `AzamPay_Blocks_Support`** (`AbstractPaymentMethodType`):

- `initialize()` pulls the gateway out of `WC()->payment_gateways`.
- `is_active()` → `$gateway->is_available()`; when false, scripts are not enqueued at all.
- `get_payment_method_script_handles()` registers `wc-azampay-blocks-integration`
  from `assets/js/ui/wc-azampay-blocks.js`, reading dependencies and the version
  hash from the generated `.asset.php` (with a hard-coded fallback if the build
  artifact is missing), and enqueues the shared stylesheet.
- `get_payment_method_data()` is the PHP→JS bridge. It emits `enabled`, `name`,
  `title`, `description`, `icon`, `partners.data`, `partners.icons`, and
  `supports`. This is what the JS reads as `getSetting("azampaymomo_data")`.
- `add_payment_request_order_meta()` hooks
  `woocommerce_rest_checkout_process_payment_with_context` at priority **8** —
  deliberately *before* the gateway's own processing — and writes
  `payment_number` / `payment_network` from `$context->payment_data` onto the
  order. This is the Blocks equivalent of the legacy
  `woocommerce_checkout_update_order_meta` hook; without it the Store API path
  would lose the phone number.

**JS — `resources/js/blocks/`:**

- `index.js` reads the settings blob, builds the `Label` (text + right-aligned
  logo) and calls `registerPaymentMethod()` with `canMakePayment: () => true`.
- `Content.jsx` holds `paymentNumber` and `paymentPartner` (default `"Azampesa"`)
  in state and subscribes to `onPaymentSetup`. On submit it validates the wallet
  and the phone number against `PHONE_PATTERNS`, and on success returns
  `meta.paymentMethodData = { payment_network, payment_number }` — which is
  exactly what the PHP side reads back out of `$context->payment_data`. The
  description is rendered with `RawHTML` because PHP sends pre-formatted HTML.
- `InputFields.jsx` renders the phone input and the radio group, mirroring the
  legacy markup and CSS classes so one stylesheet covers both checkouts.

**The contract to preserve:** the field names `payment_network` and
`payment_number`, and the settings key `azampaymomo_data` (derived from the
gateway ID + `_data`). Change either side alone and Blocks checkout silently
stops recording payment details.

### 4.8 Validation

Phone numbers, PHP side:

```php
azampesa: /^(0|1|255|\+255)?(6[1-9]|7[1-8])([0-9]{7})$/
others:   /^(0|255|\+255)?(6[1-9]|7[1-8])([0-9]{7})$/
```

AzamPesa additionally accepts a leading `1`. `validate_fields()` (legacy path)
requires both POST fields, sanitizes with `sanitize_text_field(wp_unslash(...))`,
and adds a notice on failure. The Blocks path validates client-side in
`Content.jsx` instead — see the known-issues section about the JS regex.

### 4.9 Paying — `process_payment()` / `azampay_payment_processing()`

`process_payment()` is thin: for a non-zero total it calls
`azampay_payment_processing()`, otherwise `payment_complete()` immediately; then
empties the cart and **always returns `result => success`** with the thank-you
redirect.

> Note this deliberately-lenient behaviour: the redirect is returned even when
> `azampay_payment_processing()` returned `false`. The failure surfaces as a
> WooCommerce notice rather than as a blocked checkout. Bear it in mind when
> debugging "the order was created but nothing happened".

`azampay_payment_processing()` posts to the `mno` endpoint:

```php
[
  'provider'      => $payment_network,          // e.g. 'Mpesa'
  'source'        => 'Woo commerce Plugin',
  'accountNumber' => $payment_number,
  'amount'        => $order->get_total(),
  'externalId'    => $order->get_id(),          // echoed back as utilityref
  'currency'      => $order->get_currency(),
  'additionalProperties' => [ 'customerId', 'orderId', 'total' ],
]
```

Auth is `Authorization: Bearer {token}`. On non-200 / `WP_Error` /
`success !== true` it adds a notice and returns false. On success the order goes
to `on-hold` for downloadable items, otherwise `pending`, filtered through
`woocommerce_azampay_process_payment_order_status`.

`externalId` → `utilityref` is the correlation key the webhook uses to find the
order. Do not change it.

### 4.10 The webhook — `process_webhooks()`

Hooked on `woocommerce_api_wc_azampay_webhook`, i.e.
`GET|POST {site_url}/?wc-api=wc_azampay_webhook`.

Validation chain (each failure sets an HTTP code, prints a message, and `exit`s):

1. Non-POST → `405`.
2. Empty body → `400`.
3. Missing any of `utilityref`, `reference`, `transactionstatus`, `amount` → `400`.
4. `utilityref` non-numeric → `400`; order not found → `400`.
5. Order already `processing` / `completed` / `on-hold` → prints "already
   processed" and exits (idempotency guard).
6. `amount <= 0` → `400`; `transactionstatus` not in `success|failed` → `400`.

Then:

| Condition | Result |
| --- | --- |
| `success` and `amount >= total` | `payment_complete($reference)`, order note with the reference, plus `completed` if `autocomplete_order` |
| `success` but `amount < total` | `on-hold`, `transaction_id` meta, customer + admin notes explaining the shortfall, stock reduced manually |
| `failed` | `failed` with "Payment was declined by AzamPay." |

An optional `message` field is appended as a customer-visible order note.

**Security caveat:** the endpoint does **not** verify a signature or the
`callback_token` on inbound requests — the callback token is only ever sent
*outbound* as `X-API-KEY`. Protection today rests on the numeric-order-id lookup,
the amount check, and the already-processed guard. If you harden anything in this
plugin, harden this. Note also that `amount >= total` completes the order, so
overpayment is treated as full payment.

### 4.11 Order display

| Hook | Method | Shows |
| --- | --- | --- |
| `woocommerce_checkout_update_order_meta` | `azampay_checkout_update_order_meta()` | Saves `payment_number` / `payment_network` (legacy checkout) |
| `woocommerce_admin_order_data_after_billing_address` | `azampay_order_data_after_billing_address()` | Phone + network on the admin order screen |
| `woocommerce_get_order_item_totals` | `azampay_order_item_meta_end()` | Inserts phone + network rows above the order total for the customer |
| `woocommerce_thankyou_azampaymomo` | `thankyou_page()` | The configured `instructions` text |

All three guard on `self::ID === $order->get_payment_method()` so they never
touch orders paid by another gateway.

---

## 5. Extension points

Filters this plugin provides:

| Filter | Purpose |
| --- | --- |
| `woocommerce_azampay_supported_currencies` | Add currencies beyond `TZS` |
| `woocommerce_azampay_process_payment_order_status` | Change the post-request order status |
| `azampay_tnc_url` | Override the Terms & Conditions link |
| `azampay_pp_url` | Override the Privacy Policy link |
| `woocommerce_gateway_icon` | (WooCommerce core) applied in `get_icon()` |

Public API surface on the gateway: `get_token_result()`, `get_partners_result()`,
`get_allowed_partners()`, `get_description()`, `is_available()`,
`is_valid_for_use()`, and the static `$icon_url`.

---

## 6. Build and local development

### Install
```bash
npm install
```

### Scripts
| Command | Does |
| --- | --- |
| `npm start` | `wp-scripts start` — watch mode for the Blocks bundle |
| `npm run build` | `wp-scripts build` — production bundle into `assets/js/ui/` |
| `npm run deploy` | `packages-update` then `build` |
| `npm run packages-update` | Bump `@wordpress/*` packages |
| `npm run check-engines` | Verify Node/npm versions |

The build is driven by `webpack.config.js`, which extends
`@wordpress/scripts`'s default config, swaps WordPress's
`DependencyExtractionWebpackPlugin` for
`WooCommerceDependencyExtractionWebpackPlugin`, and maps
`@woocommerce/blocks-registry` → `wc.wcBlocksRegistry` /
`@woocommerce/settings` → `wc.wcSettings` so those are treated as externals
rather than bundled. Entry `resources/js/blocks/index.js` →
`assets/js/ui/wc-azampay-blocks.js`, with the dependency array and version hash
written to the sibling `.asset.php`.

**`assets/js/ui/` is generated. Edit `resources/js/blocks/` and rebuild.** Both
build artifacts *are* committed (a WordPress.org plugin ships without a build
step), so a JS change is two files in the diff.

### PHP linting
`phpcs.xml` targets `WooCommerce-Core` + `WordPress-Extra` + `PHPCompatibility`,
excluding `assets/`, `node_modules/`, `vendor/`, `tests/`. PHPCS itself is not a
declared dependency — install it globally or via a scratch Composer project:

```bash
phpcs --standard=phpcs.xml woocommerce-azampay.php includes/
```

Note the ruleset's `testVersion` is `5.6-`, which is stale relative to the
plugin's actual PHP 8.0 floor.

### Manual test setup
1. A WordPress + WooCommerce site with store currency set to **TZS** (the gateway
   disables itself otherwise).
2. Symlink or copy the repo into `wp-content/plugins/`, activate it.
3. Register an app at <https://developers.azampay.co.tz/sandbox/registerapp> to get
   sandbox `appName`, `clientId`, `clientSecret`, and callback token.
4. **WooCommerce → Settings → Payments → AzamPay**: enable, tick Test Mode, paste
   the four test credentials, save.
5. Register the callback URL shown on that settings screen
   (`{site}/?wc-api=wc_azampay_webhook`) with AzamPay. Local development needs a
   tunnel (ngrok/cloudflared) for the webhook to reach you.
6. Test **both** checkouts — the shortcode `[woocommerce_checkout]` page and the
   Checkout **block** page. They run entirely separate code paths.

To simulate a callback without AzamPay:

```bash
curl -X POST "https://your-site.test/?wc-api=wc_azampay_webhook" \
  -H "Content-Type: application/json" \
  -d '{"utilityref":"123","reference":"TESTREF1","transactionstatus":"success","amount":"1000","message":"Test callback"}'
```

(`utilityref` is the WooCommerce order ID; the order must not already be
`processing`/`completed`/`on-hold`.)

### Clearing caches while debugging
Stale credentials or a wedged negative cache explain most "settings look right
but checkout is broken" cases. Saving the settings page clears all three
transients; otherwise, from WP-CLI:

```bash
wp transient delete --all
```

---

## 7. Release checklist

The version number lives in **four** places and they must match:

1. `woocommerce-azampay.php` plugin header `Version:`
2. `define( 'WC_AZAMPAY_VERSION', ... )` (marked `WRCS: DEFINED_VERSION`)
3. `package.json` → `version`
4. `README.txt` **and** `README.md` → `Stable tag:`

Then:

- Update `Tested up to` / `WC tested up to` if compatibility changed.
- Add `== Changelog ==` and `== Upgrade Notice ==` entries in both readmes.
- Run `npm run build` and commit the regenerated `assets/js/ui/` files.
- Update `@since` / `@version` docblock tags on methods you touched — this
  codebase maintains them consistently.
- Ship without `node_modules/` (the only `.gitignore` entry).

---

## 8. Known quirks and gotchas

Things that will surprise you, in rough order of how much time they can cost:

1. **`get_all_partners()` reads `$this->token_result` directly**
   (`class-woo-azampay-gateway.php:674`) rather than calling `ensure_token()`. It
   only works because `ensure_partners()` resolves the token first. Any new caller
   of `get_all_partners()` must do the same, or it will hit a null-property access.

2. **The JS phone regex does not match the PHP one.**
   `resources/js/blocks/constants.js` has `\\+255` inside a regex *literal*, which
   means "one or more backslashes", not "an optional plus". So `+255712345678` is
   rejected on the Blocks checkout but accepted on the legacy one. Plain
   `0…`/`255…`/bare 9-digit numbers work on both. Fix is `\+255`.

3. **`Content.jsx`'s `enabled` flag is always truthy** —
   `settings.enabled || true` can never be false, so the "AzamPay is disabled"
   branch is dead code. The real gating happens in PHP via `is_active()`, which
   prevents the script loading at all. Should be `settings.enabled ?? true`.

4. **`$azampesa_field` is used unconditionally** in `payment_fields()` even though
   it is only assigned inside the AzamPesa branch of the partner loop. Safe today
   because AzamPesa is force-enabled, but it warns if the API ever omits AzamPesa.

5. **`process_payment()` always reports success** — see §4.9.

6. **The webhook is unauthenticated** — see §4.10.

7. **Settings keys are prefix-based.** `$this->get_option($access_key . '_client_id')`
   builds field names from the `test`/`prod` prefix. Renaming a settings key breaks
   silently — no error, just empty credentials and a `203`.

8. **`admin_options()` and `admin_scripts()` call `get_current_screen()`** and
   compare against `woocommerce_page_wc-settings`. In contexts where that function
   is unavailable or the screen is null this can fatal; it is why those methods
   return early so aggressively.

9. **The partner-name casing is inconsistent by design** — `vodacom` is lowercase
   while the others are capitalized, because that is what the API returns. The
   logo file is `vodacom-logo.png` while everything else is `.svg`, so
   `payment_fields()` and `get_partner_icons()` both special-case it.

10. **Two readmes, one content.** `README.md` and `README.txt` are near-identical
    WordPress.org-format files. Edit both.

11. **No i18n `.pot` file** is generated or shipped despite thorough
    `__()`/`esc_html__()` usage under the `azampay` text domain.

---

## 9. Quick reference

| Thing | Value |
| --- | --- |
| Gateway ID | `azampaymomo` |
| Settings option | `woocommerce_azampaymomo_settings` |
| Blocks settings key | `azampaymomo_data` |
| Webhook URL | `{site_url}/?wc-api=wc_azampay_webhook` |
| Order meta | `payment_number`, `payment_network`, `transaction_id` |
| Transients | `azampay_token_*`, `azampay_partners_*`, `azampay_partners_*_fail` |
| POST field names | `payment_number`, `payment_network` |
| HTTP timeout | 15s |
| Sandbox registration | <https://developers.azampay.co.tz/sandbox/registerapp> |

### Where to make a given change

| Task | Files |
| --- | --- |
| Add a settings field | `init_form_fields()` (+ `azampay-admin.js` if it needs show/hide) |
| Add a wallet | `$partners_dictionary`, `process_admin_options()`, `admin_options()` loop, `get_partner_icons()`, `assets/public/images/` |
| Change checkout markup (legacy) | `payment_fields()` |
| Change checkout markup (blocks) | `resources/js/blocks/*` then `npm run build` |
| Change checkout styling | `assets/public/css/azampay-styles.css` (affects both) |
| Change webhook handling | `process_webhooks()` |
| Change the checkout API payload | `azampay_payment_processing()` |
| Change caching behaviour | `ensure_token()`, `ensure_partners()`, `compute_token_ttl()`, the `*_TTL*` constants |
