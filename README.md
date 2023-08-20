## Integration Requirements

This version requires the following:

* WHMCS 7.x
* Running Bitcart instance: [deployment guide](https://docs.bitcart.ai/deployment/deployment)

## Installing the Plugin

1. From your WHMCS business account, go to setup > payments > payment gateways

2. On the next screen, click on the **All Payment Gateways** tab and click on **Bitcart Checkout** to enable the plugin. The next step will be to configure it.

## Plugin Configuration

After you have enabled the Bitcart plugin, the configuration steps are:

1. Enter your admin panel URL (for example, https://admin.bitcart.ai) without slashes
2. Enter your merchants API URl (for example, https://api.bitcart.ai) without slashes

This plugin also includes an IPN (Instant Payment Notification) endpoint that will update your WHMCS invoice status.

* Initially the WHMCS invoice will be in a **Unpaid** status when it is initially created.
* After the invoice is paid by the user, it will change to a **Payment Pending** status.
* When Bitcart finalizes the transaction, it will change to a **Paid** status, and your order will be safe to ship, allow access to downloadable products, etc.
