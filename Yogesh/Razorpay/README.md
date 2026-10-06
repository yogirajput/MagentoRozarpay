# Yogesh Razorpay for Magento 2

Magento 2.4 module skeleton integrating Razorpay Checkout with a server-created Razorpay Order and server-side payment verification.

## Install

Copy this directory to `<magento-root>/app/code/Yogesh/Razorpay`, then run:

```bash
bin/magento module:enable Yogesh_Razorpay
bin/magento setup:upgrade
bin/magento cache:flush
```

In production mode, also run dependency compilation and static content deployment.

Configure **Stores → Configuration → Sales → Payment Methods → Razorpay**. Enter a Razorpay **test** Key ID and Key Secret for testing; Razorpay uses the same API hostname for test and live credentials. The Key Secret is stored through Magento's encrypted configuration backend. Switch to live credentials only after completing a successful test payment.

## Payment flow and security

1. Magento creates a pending order.
2. The storefront asks this module to create a Razorpay Order using the saved Magento order's amount and currency. The Razorpay Order ID is stored on the payment record.
3. Razorpay Checkout returns its payment ID, order ID, and signature to the storefront.
4. The server verifies the HMAC signature and fetches the payment directly from Razorpay. The payment must be captured and match the saved Razorpay Order, amount, and currency before Magento records capture and moves the order to processing.

The storefront's request carries Magento's form key; both actions are POST-only and use the checkout session's last order. API errors are logged server-side while generic messages are returned to the customer. Do not log or expose the Key Secret. Configure Razorpay to auto-capture payments; uncaptured/authorized payments are deliberately not marked paid by this implementation.

## Scope and production work

This is a starter integration for Magento Open Source / Adobe Commerce 2.4 and standard Luma checkout. It currently accepts INR orders only and does not implement refunds, webhooks, vaulting, multishipping, or headless checkout. Test compatibility ayogeshst the exact Magento/PHP version and checkout/theme stack before production use. Add a signed Razorpay webhook and a durable idempotency strategy for recovery from browser closure and network interruption before relying on it for production order fulfillment.
