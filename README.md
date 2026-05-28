# NakoPay for Easy Digital Downloads

Crypto checkout gateway for Easy Digital Downloads. Payments settle directly to
your wallet - NakoPay never touches your funds.

[![Status](https://img.shields.io/badge/status-stable-blue)](https://nakopay.com/integrations/edd)
[![License](https://img.shields.io/badge/license-MIT-green)](../LICENSE)

## Requirements

- WordPress 6.0+
- Easy Digital Downloads 3.0+
- PHP 8.0+
- A NakoPay account ([sign up free](https://nakopay.com))

## Install

### Option A: Upload zip (recommended)

1. Download `nakopay-edd.zip` from [the latest release](https://github.com/NakoPayHQ/plugin-edd/releases/latest)
2. In wp-admin: **Plugins > Add New > Upload Plugin > Choose File** > pick the zip > **Install Now > Activate**

### Option B: Clone from GitHub

```bash
cd wp-content/plugins
git clone https://github.com/NakoPayHQ/plugin-edd.git nakopay-edd
```

Activate in wp-admin: **Plugins > Installed Plugins > NakoPay for Easy Digital Downloads > Activate**

## Configure

1. Get your API key at [nakopay.com/dashboard/api-keys](https://nakopay.com/dashboard/api-keys)
2. In wp-admin: **Downloads > Settings > Payments > Gateways**
3. Check **NakoPay (Bitcoin)** to enable it
4. Click the **NakoPay** tab under Gateway Settings
5. Paste your **Secret key** (`sk_test_*` or `sk_live_*`) in the API Key field
6. Paste your **Webhook Signing Secret** (`whsec_*`) in the Webhook Secret field
7. Click **Save Changes**

### Webhook URL

Set this URL in your NakoPay dashboard at [nakopay.com/dashboard/webhooks](https://nakopay.com/dashboard/webhooks):

```
https://your-site.com/?edd-listener=nakopay
```

Subscribe to events: `invoice.paid`, `invoice.expired`, `invoice.canceled`

## Test mode

Use `sk_test_*` keys and enable EDD Test Mode to test the full checkout flow
without real funds. Flip to `sk_live_*` when you're ready for production.

## How it works

1. Customer selects "Bitcoin via NakoPay" at EDD checkout
2. Plugin creates a NakoPay invoice via the API
3. Customer is redirected to NakoPay's hosted checkout page (QR code + pay address)
4. On payment confirmation, NakoPay sends a webhook to your site
5. Plugin verifies the HMAC-SHA256 signature and marks the EDD payment as complete
6. Customer receives download links

## Supported features

- [x] One-time purchases
- [x] Hosted checkout (redirect)
- [x] HMAC-SHA256 webhook verification
- [x] Test/live mode toggle
- [x] Transaction ID storage for accounting
- [x] Automatic payment status updates

## Troubleshooting

**NakoPay doesn't appear at checkout**
- Downloads > Settings > Payments > Gateways - make sure NakoPay is checked
- Make sure you have an API key entered in the NakoPay settings tab

**Payment stays pending after customer pays**
1. Check the webhook URL in nakopay.com/dashboard/webhooks matches your domain
2. Re-paste the `whsec_*` secret (don't retype)
3. Resend the webhook from the dashboard - one-click retry

**"Could not create NakoPay invoice" error**
- Verify your API key is valid at nakopay.com/dashboard/api-keys
- Check your server can make outbound HTTPS requests

## Support

- [Open a GitHub issue](https://github.com/NakoPayHQ/plugin-edd/issues)
- [NakoPay documentation](https://nakopay.com/docs)
- [Contact support](https://nakopay.com/contact)

## About Easy Digital Downloads

[Easy Digital Downloads](https://easydigitaldownloads.com/) - WordPress plugin for selling digital products. Visit their website to learn more about the platform and its features.

## License

MIT


## Subscription Webhooks

NakoPay supports recurring crypto subscriptions. When enabled, your webhook endpoint receives these additional events:

| Event | Description |
|-------|-------------|
| `subscription.created` | New subscription activated |
| `subscription.renewed` | Renewal invoice generated, billing period advanced |
| `subscription.past_due` | Payment overdue (3-day grace period expired) |
| `subscription.updated` | Subscription details changed |
| `subscription.canceled` | Subscription ended (manual cancel or non-payment after 7 days past due) |

Each event payload includes the subscription `id`, `amount`, `coin`, `interval`, `status`, and `metadata` (plan name, plan ID). Renewal and past-due events also include the `invoice_id`.

To enable subscription webhooks, add `subscription.*` to your webhook endpoint's enabled events in your NakoPay dashboard at `nakopay.com/dashboard/settings`.
