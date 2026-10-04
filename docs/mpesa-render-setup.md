# M-Pesa STK Push on Render

## Current deployment defaults

The Render blueprint keeps `MPESA_ENABLED=false` and `MPESA_ENV=sandbox`. The payment code is not live until credentials are configured, the callback is reachable, and the admin `payments_enabled` site control is enabled. Never commit `.env` or paste secret values into source control, chat, or issue trackers.

## Values to set in Render

Open the `assignment-portal` web service in Render and set these under **Environment**. Blueprint entries marked `sync: false` must be supplied in the Render dashboard.

| Variable | What to configure |
| --- | --- |
| `DB_PASS` | Aiven MySQL password already used by the deployed database. |
| `MPESA_ENV` | `sandbox` for initial integration testing. |
| `MPESA_ENABLED` | Keep `false` until the callback and sandbox tests below pass; then set `true` for sandbox testing. |
| `MPESA_CONSUMER_KEY` | Consumer key from the Safaricom Daraja sandbox app. |
| `MPESA_CONSUMER_SECRET` | Consumer secret from that sandbox app. |
| `MPESA_SHORTCODE` | Sandbox shortcode, or the production Paybill/Till shortcode issued for the selected environment. |
| `MPESA_PASSKEY` | Passkey paired with that shortcode and environment. |
| `MPESA_CALLBACK_URL` | `https://assignment-portal-uklg.onrender.com/mpesa_callback.php` only if this is still the service's actual public URL. It must be public HTTPS and must not include the token. |
| `MPESA_CALLBACK_SECRET` | A newly generated, long random secret stored only in Render. Safaricom's callback URL will carry this as a query token. |

Keep `PORTAL_EXTENSIONS_ENABLED=true` because the extension schema is migrated. Keep `EDUCATION_COURSE_TARGETING_ENABLED=false` unless the rollout is intentionally enabled and student profiles have been handled.

## Where to obtain Daraja credentials

1. Create/sign in to a Safaricom Daraja developer account at [developer.safaricom.co.ke](https://developer.safaricom.co.ke/).
2. Create a sandbox app and enable the M-Pesa STK Push/Lipa Na M-Pesa Online product shown in the portal. The app provides its sandbox **Consumer Key** and **Consumer Secret**; copy those directly into Render's matching secret fields.
3. Use the sandbox shortcode and passkey from the Daraja STK Push product documentation/test credentials for the sandbox. A consumer key/secret alone does not supply a receiving account.
4. For real payments, register the business and receiving Paybill/Till with Safaricom, complete Daraja Go-Live approval, and obtain the production shortcode/passkey and production app credentials from Safaricom. Production values are not generated merely by changing `MPESA_ENV`.

The current code specifically sends `CustomerPayBillOnline` requests. It requires a Daraja business shortcode and matching passkey. **It cannot STK-push funds directly into a personal M-Pesa phone number or automatically verify ordinary person-to-person Send Money.** If the receiving method is a personal number only, keep the current M-Pesa switches off; that requires a separate manual-payment workflow where a student submits the M-Pesa transaction code and an administrator verifies it against the statement before granting access.

Do not switch `MPESA_ENV` to `production` or use production credentials until Safaricom has approved the production Daraja app and supplied production shortcode/passkey details. The existing local `.env` APP_URL was corrected to the Render hostname; verify that Render's own `APP_URL` and `MPESA_CALLBACK_URL` use the actual deployed `onrender.com` hostname.

## App and callback setup

1. Deploy the fixed Docker image and wait for Render's health check to pass. The image must report PHP `curl` as available in Admin Dashboard → Site health.
2. In Safaricom Daraja, configure the STK Push callback URL to the exact Render callback endpoint. The integration app appends `?token=<MPESA_CALLBACK_SECRET>` when sending the STK request; do not append a different token yourself.
3. Confirm the Render service is reachable over HTTPS from Safaricom's systems. Do not test the callback by visiting it in a browser; it expects a Daraja JSON POST and returns `403` without the shared token.
4. In Admin Dashboard → Site feature controls, set **Payments enabled** to Enabled only after the credentials and callback configuration are saved. This is a separate database setting from `MPESA_ENABLED`.
5. Configure an authorized course fee as a positive whole number of Kenyan shillings. Student payment is permitted only after the student's course application is approved.

## Sandbox validation checklist

Use a test student/approved application and a fee/course that can be safely tested. Verify: STK prompt arrival; successful payment; user cancellation/failure; duplicate callback idempotency; delayed callback retry; and amount, phone, checkout-ID, and receipt validation. Confirm the payment row becomes successful and resources unlock only after the verified success callback.

The retry worker is `php /var/www/html/bin/retry_payment_callbacks.php`. Set up a Render Cron Job to run it periodically (for example every five minutes) and alert on failures and callback inbox items requiring retry. Without a worker, callbacks that fail after being stored in the inbox are not retried automatically.

## Production security limitation

The current callback guard is a shared secret in the callback query string, not a cryptographic Safaricom signature. Do not process real-money production payments until callback authenticity and transaction reconciliation have an approved production control (such as Safaricom-supported verification/query mechanisms and suitable edge protections). Monitor payment and callback records and reconcile them against Daraja before enabling production traffic.
