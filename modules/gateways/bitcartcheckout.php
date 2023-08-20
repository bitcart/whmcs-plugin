<?php
/**
 * Bitcart Checkout 1.0.4
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "bitcartcheckout" and therefore all functions
 * begin "bitcartcheckout_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */

function bitcartcheckout_MetaData()
{
    return array(
        'DisplayName' => 'Bitcart_Checkout_WHCMS',
        'APIVersion' => '1.0.4',
        'DisableLocalCreditCardInput' => false,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */

function bitcartcheckout_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Bitcart Checkout',
        ),

        'bitcart_api_endpoint' => array(
            'FriendlyName' => 'API Endpoint',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Your Bitcart instance\'s Merchants API URL.',
        ),

        'bitcart_admin_url' => array(
            'FriendlyName' => 'Admin URL',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Your Bitcart instance\'s Admin Panel URL.',
        ),

        'bitcart_store_id' => array(
            'FriendlyName' => 'Store ID',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Store ID of your Bitcart Store.',
        ),

    );
}

function send_request($url, $data)
{
    $post_fields = json_encode($data);

    $request_headers = array();
    $request_headers[] = 'Content-Type: application/json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);

    curl_close($ch);

    return json_decode($result);
}

function bitcartcheckout_link($config_params)
{
    $curpage = basename($_SERVER["SCRIPT_FILENAME"]);

    $curpage = str_replace("/", "", $curpage);
    if ($curpage != 'viewinvoice.php'): return;endif;
    ?>
<?php

    // Settings
    $admin_url = $config_params['bitcart_admin_url'];
    $api_url = strtolower($config_params['bitcart_api_endpoint']);
    // Invoice Parameters
    $invoiceId = $config_params['invoiceid'];
    $amount = $config_params['amount'];
    $currencyCode = $config_params['currency'];
    // Client Parameters
    $email = $config_params['clientdetails']['email'];
    // System Parameters
    $langPayNow = $config_params['langpaynow'];

    $params = new stdClass();
    $dir = dirname($_SERVER['REQUEST_URI']);
    if ($dir == '/') {
        $dir = '';
    }
    $protocol = 'https://';

    $params->price = $amount;
    $params->store_id = $config_params['bitcart_store_id'];
    $params->currency = $currencyCode;
    $params->order_id = trim($invoiceId);

    $params->notification_url = $protocol . $_SERVER['SERVER_NAME'] . $dir . '/modules/gateways/bitcartcheckout/bitcartcheckout_ipn.php';
    $params->redirect_url = $params->notificationURL;

    $params->buyer_email = $email;

    // create the invoice

    $invoice = send_request(sprintf('%s/%s', $api_url, 'invoices/order_id/' . urlencode($params->order_id)), $params);
    $invoiceID = $invoice->id;

    $htmlOutput .= '<button name = "bitcart-payment" class = "btn btn-success btn-sm" onclick = "showModal();return false;">' . $langPayNow . '</button>';

    ?>
<script src="<?php echo $admin_url; ?>/modal/bitcart.js" type="text/javascript"></script>
<script type='text/javascript'>
function showModal() {
    window.addEventListener("message", function(event) {
        if (event.data.status == 'complete') {
            location.href = location.href;
        }
    }, false);

    // show the modal
    bitcart.showInvoice('<?php echo $invoiceID; ?>');
}
</script>
<?php
$htmlOutput .= '</form>';
    return $htmlOutput;

}
