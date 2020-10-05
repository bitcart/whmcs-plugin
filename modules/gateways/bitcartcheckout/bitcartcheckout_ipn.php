<?php

/**
 * BitcartCC Checkout IPN 3.0.1.7
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
$api_url = $gatewayParams['bitcartcc_api_endpoint'];

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
#first see if the ipn matches
#get the user id first
$table = "_bitcart_checkout_transactions";
$fields = "order_id,transaction_id";
$where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
$result = select_query($table, $fields, $where);
$rowdata = mysql_fetch_array($result);

$btn_id = $rowdata['transaction_id'];
if ($btn_id):
    switch ($order_status) {
        #complete, update invoice table to Paid
        case 'complete':
            $table = "tblinvoices";
            $update = array("status" => 'Paid', 'datepaid' => date("Y-m-d H:i:s"));
            $where = array("id" => $orderid, "paymentmethod" => "bitcartcheckout");
            try {
                update_query($table, $update, $where);
            } catch (Exception $e) {
            }
            #update the bitcart_invoice table
            $table = "_bitcart_checkout_transactions";
            $update = array("transaction_status" => $order_status);
            $where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
            try {
                update_query($table, $update, $where);
            } catch (Exception $e) {
            }

            addInvoicePayment(
                $orderid,
                $order_invoice,
                $invoice->price,
                0,
                'bitcartcheckout'
            );

            break;

        case 'invalid':
            $table = "tblinvoices";
            $update = array("status" => 'Unpaid');
            $where = array("id" => $orderid, "paymentmethod" => "bitcartcheckout");
            try {
                update_query($table, $update, $where);
            } catch (Exception $e) {
            }

            #update the bitcart_invoice table
            $table = "_bitcart_checkout_transactions";
            $update = array("transaction_status" => $order_status);
            $where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
            try {
                update_query($table, $update, $where);
            } catch (Exception $e) {
            }

            break;

        #expired, remove from transaction table, wont be in invoice table
        case 'expired':
            #delete any orphans
            $table = "_bitcart_checkout_transactions";
            $delete = 'DELETE FROM _bitcart_checkout_transactions WHERE transaction_id = "' . $order_invoice . '"';
            try {
                full_query($delete);
            } catch (Exception $e) {
            }
            break;

    }
endif; #end of the table lookup
