<?php

/**
 * Bitcart Checkout IPN 3.0.1.7
 *
 * This file demonstrates how a payment gateway callback should be
 * handled within WHMCS.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */
// Require libraries needed for gateway module functions.
require_once '../../../init.php';
require_once '../../../includes/gatewayfunctions.php';
require_once '../../../includes/invoicefunctions.php';

function checkInvoiceStatus($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$gatewayModuleName = 'bitcartcheckout';
$gatewayParams = getGatewayVariables($gatewayModuleName);
$api_url = $gatewayParams['bitcart_api_endpoint'];

$data = json_decode(file_get_contents("php://input"), true);

$order_status = $data['status'];
$order_invoice = $data['id'];
$url_check = sprintf('%s/%s', $api_url, 'invoices/' . $order_invoice);
$invoice = json_decode(checkInvoiceStatus($url_check));
if ($order_status != $invoice->status):
    #ipn doesnt match data, stop
    die();
endif;
$orderid = $invoice->order_id;

$orderid = checkCbInvoiceID($orderid, $gatewayParams['name']);
checkCbTransID($order_invoice);

if ($order_status == 'complete') {
    addInvoicePayment(
        $orderid,
        $order_invoice,
        $invoice->price,
        0,
        'bitcartcheckout'
    );
}
