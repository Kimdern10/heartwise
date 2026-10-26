# Setting up Stripe for Heartwise Premium

## 0. Can you open a Stripe account?

Stripe only accepts businesses registered in its supported countries
(see https://stripe.com/global). Nigeria is not on that list at the time
of writing. To charge US customers with Stripe from Nigeria, the usual
route is **Stripe Atlas** (https://stripe.com/atlas): it registers a US
company (Delaware LLC/C-corp) for you, gets an EIN (US tax ID) and opens
a Stripe account. A US company also makes the dating site's legal side
(Terms, privacy, US taxes) simpler. Talk to an accountant about taxes.

## 1. Create the account and stay in Test mode

1. Sign up at https://dashboard.stripe.com/register
2. In the top bar, make sure **Test mode** is ON while you set things up.

## 2. Copy your API keys into `.env`

Dashboard → **Developers → API keys**

```
STRIPE_KEY=pk_test_...        # "Publishable key"
STRIPE_SECRET=sk_test_...     # "Secret key" (never share it, never commit it)
```

## 3. Create the Premium product and its three prices

Dashboard → **Product catalog → + Add product**

- Name: `Heartwise Premium`
- Add 3 prices, all **Recurring**, currency **USD**:
  - $29.99 every **1 month**
  - $59.99 every **3 months**
  - $89.99 every **6 months**

Open each price and copy its ID (starts with `price_`):

```
STRIPE_PRICE_MONTHLY=price_...
STRIPE_PRICE_QUARTERLY=price_...
STRIPE_PRICE_BIANNUAL=price_...
```

If you change the prices in Stripe, also change the `amount` numbers in
`config/premium.php` so the website shows the same price.

## 4. Turn on the customer portal (for "Manage or cancel")

Dashboard → **Settings → Billing → Customer portal** → Activate.
Allow customers to: cancel subscriptions, update payment methods,
view invoices, switch plans (choose the 3 Premium prices).

## 5. Webhook (tells the site when someone pays or cancels)

On the **live server** (after uploading the site), run:

```
php artisan cashier:webhook --url "https://YOUR-DOMAIN.com/stripe/webhook"
```

Stripe then shows a **Signing secret** for that webhook
(Developers → Webhooks → your endpoint). Put it in `.env`:

```
STRIPE_WEBHOOK_SECRET=whsec_...
```

To test on your computer instead, install the Stripe CLI and run
`stripe listen --forward-to http://heartwise.test/stripe/webhook`;
it prints a temporary `whsec_...` to use.

## 6. Reload settings and test

```
php artisan config:clear
```

Open `/premium`, choose a plan, and pay with Stripe's test card
**4242 4242 4242 4242**, any future date, any CVC, any ZIP.
You should come back to Premium with your perks on, and
"Manage or cancel subscription" should open Stripe's portal.

## 7. Going live

1. In Stripe, complete **Activate account** (business details, bank account).
2. Switch Test mode OFF, copy the **live** keys (`pk_live_`, `sk_live_`).
3. Re-create the product and 3 prices in live mode and copy the new IDs.
4. Run the `cashier:webhook` command again (live mode) and update `STRIPE_WEBHOOK_SECRET`.
5. `php artisan config:clear`.
