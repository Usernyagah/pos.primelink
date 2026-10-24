# M-Pesa Daraja for TakePOS

This external Dolibarr module adds a Daraja STK Push option to the TakePOS payment popup. The invoice is paid through Dolibarr's regular TakePOS payment flow only after a matching successful Daraja callback has been received and independently verified with Daraja. Each confirmation can be used only once.

The module also adds an optional supermarket cashier layout in TakePOS. Use the cashier layout button in the POS action panel to switch layouts; the choice is saved separately for each terminal in that browser. The layout emphasizes barcode scanning and product lookup while retaining the normal basket, keypad, products, and payment actions.

## Requirements

- Dolibarr 20.0 or later and PHP 8.1 or later.
- TakePOS and Bank modules enabled.
- A public HTTPS address for Dolibarr callbacks.
- A Daraja app and an M-Pesa PayBill or Till configured for STK Push.
- A payment type with code `MPESA` in **Home > Setup > Dictionaries > Payment types**.
- A bank account assigned per terminal using the Dolibarr constant `CASHDESK_ID_BANKACCOUNT_MPESA1` (replace `1` with each terminal number).

## Credentials and environment

Set these environment variables in the hosting service. Do not put secrets in Git, Dolibarr source files, or chat.

| Variable | Value |
| --- | --- |
| `MPESA_DARAJA_ENV` | `sandbox` or `production` |
| `MPESA_DARAJA_CONSUMER_KEY` | Daraja Consumer Key |
| `MPESA_DARAJA_CONSUMER_SECRET` | Daraja Consumer Secret |
| `MPESA_DARAJA_SHORTCODE` | PayBill or Till shortcode |
| `MPESA_DARAJA_PASSKEY` | Daraja STK Push passkey |
| `MPESA_DARAJA_CALLBACK_URL` | `https://your-dolibarr-host/custom/mpesadaraja/callback.php` |
| `MPESA_DARAJA_TRANSACTION_TYPE` | `CustomerPayBillOnline` or `CustomerBuyGoodsOnline` |

The callback URL must be publicly reachable over HTTPS and configured as the callback URL in your deployment environment. The module sends whole-KES requests only, up to 150,000 KES per STK Push.

Keep Dolibarr's `MAIN_CURL_DEBUG` disabled in production because verbose HTTP debugging can record API request details.

## Install

Copy `htdocs/custom/mpesadaraja` into the active Dolibarr custom modules directory, enable **M-Pesa Daraja** in **Home > Setup > Modules/Applications**, then configure the payment type and bank account constants above. The module setup page displays whether the required environment variables are present and the expected callback URL.

Test using Daraja sandbox credentials and a sandbox test MSISDN before switching `MPESA_DARAJA_ENV` to `production`.
