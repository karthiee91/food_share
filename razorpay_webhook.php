<?php
// razorpay_webhook.php

require_once 'db.php';
require_once 'payment_config.php';
require_once 'fund_payment_helper.php';

$rawBody = file_get_contents('php://input');
$receivedSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

$expectedSignature = hash_hmac(
    'sha256',
    $rawBody,
    RAZORPAY_WEBHOOK_SECRET
);

if (
    !$receivedSignature ||
    !hash_equals($expectedSignature, $receivedSignature)
) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($rawBody, true);
$eventName = $event['event'] ?? '';

if ($eventName !== 'payment.captured') {
    http_response_code(200);
    exit('Event ignored');
}

$payment = $event['payload']['payment']['entity'] ?? [];

if (
    ($payment['status'] ?? '') !== 'captured' ||
    ($payment['currency'] ?? '') !== 'INR' ||
    empty($payment['order_id']) ||
    empty($payment['id']) ||
    !isset($payment['amount'])
) {
    http_response_code(400);
    exit('Invalid payment data');
}

try {
    applyCapturedFundPayment(
        $conn,
        (string)$payment['order_id'],
        (string)$payment['id'],
        (int)$payment['amount']
    );

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    // A non-2xx response allows the provider to retry delivery.
    http_response_code(500);
    echo 'Retry required';
}