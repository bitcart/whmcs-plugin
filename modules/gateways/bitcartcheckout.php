<?php
/**
 * BitcartCC Checkout 1.0
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

#create the transaction table
use WHMCS\Database\Capsule;

// Create a new table.
try {
    Capsule::schema()->create(
        '_bitcart_checkout_transactions',
        function ($table) {
            /** @var \Illuminate\Database\Schema\Blueprint $table */
            $table->increments('id');
            $table->integer('order_id');
            $table->string('transaction_id');
            $table->string('transaction_status');
            $table->timestamps();
        }
    );
} catch (\Exception $e) {
    #echo "Unable to create my_table: {$e->getMessage()}";
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
        'DisplayName' => 'BitcartCC_Checkout_WHCMS',
        'APIVersion' => '1.0',
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
            'Value' => 'BitcartCC Checkout',
        ),

        'bitcartcc_api_endpoint' => array(
            'FriendlyName' => 'API Endpoint',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Your BitcartCC instance\'s Merchants API URL.',
        ),

        'bitcartcc_admin_url' => array(
            'FriendlyName' => 'Admin URL',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Your BitcartCC instance\'s Admin Panel URL.',
        ),

        'bitcartcc_store_id' => array(
            'FriendlyName' => 'Store ID',
            'Type' => 'text',
            'Default' => '1',
            'Description' => 'Store ID of your BitcartCC Store.',
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
    $admin_url = $config_params['bitcartcc_admin_url'];
    $api_url = strtolower($config_params['bitcartcc_api_endpoint']);
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

    $callback_url = $protocol . $_SERVER['SERVER_NAME'] . $dir . '/modules/gateways/bitcartcheckout/bitcartcheckout_callback.php';
    $params->price = $amount;
    $params->store_id = intval($config_params['bitcartcc_store_id']);
    $params->currency = $currencyCode;
    $params->order_id = trim($invoiceId);

    $params->notification_url = $protocol . $_SERVER['SERVER_NAME'] . $dir . '/modules/gateways/bitcartcheckout/bitcartcheckout_ipn.php';
    $params->redirect_url = $params->notificationURL;

    $params->buyer_email = $email;

    // create the invoice

    $invoice = send_request(sprintf('%s/%s', $api_url, 'invoices'), $params);
    $invoiceID = $invoice->id;

    #insert into the database
    $pdo = Capsule::connection()->getPdo();
    $pdo->beginTransaction();

    $created_at = 'Y-m-d';

    try {
        $statement = $pdo->prepare(
            'insert into _bitcart_checkout_transactions (order_id, transaction_id, transaction_status,created_at) values (:order_id, :transaction_id, :transaction_status,:created_at)'
        );

        $statement->execute(
            [
                ':order_id' => $params->order_id,
                ':transaction_id' => $invoiceID,
                ':transaction_status' => 'new',
                ':created_at' => date($created_at . ' H:i:s'),
            ]
        );
        $pdo->commit();
    } catch (\Exception $e) {
        error_log($e->getMessage());
        $pdo->rollBack();
    }

    $htmlOutput .= '<button name = "bitcart-payment" class = "btn btn-success btn-sm" onclick = "showModal(\'' . base64_encode(json_encode($invoice)) . '\');return false;">' . $langPayNow . '</button>';

    ?>
<script src="<?php echo $admin_url; ?>/modal/bitcart.js" type="text/javascript"></script>
<script type='text/javascript'>
function showModal(invoiceData) {
    $post_url = '<?php echo $callback_url; ?>'
    $idx = $post_url.indexOf('https')
    if($idx == -1 && location.protocol == 'https:'){
        $post_url = $post_url.replace('http','https')
    }

    $invoiceData = invoiceData;

    var payment_status = null;
    var is_paid = false
    window.addEventListener("message", function(event) {
        if (event.data.status == 'complete') {
            var saveData = jQuery.ajax({
                type: 'POST',
                url: $post_url,
                data: $invoiceData,
                dataType: "text",
                success: function(resultData) {
                    location.href = location.href;
                }
            });
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
