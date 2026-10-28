<?php
// verify_fund_payment.php
session_start();
header('Content-Type: application/json');

require_once 'db.php';
require_once 'payment_config.php';
require_once 'fund_payment_helper.php';

function respond($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'POST requests only.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    respond(false, 'Please sign in again.');
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$donationId = (int)($data['donation_id'] ?? 0);
$orderId = trim($data['razorpay_order_id'] ?? '');
$paymentId = trim($data['razorpay_payment_id'] ?? '');
$signature = trim($data['razorpay_signature'] ?? '');

if (!$donationId || !$orderId || !$paymentId || !$signature) {
    http_response_code(400);
    respond(false, 'Missing payment verification details.');
}

// Confirm this order belongs to the signed-in donor.
$stmt = $conn->prepare(
    "SELECT gateway_order_id
     FROM fund_donations
     WHERE donation_id = ? AND user_id = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $donationId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !hash_equals($row['gateway_order_id'] ?? '', $orderId)) {
    http_response_code(400);
    respond(false, 'Donation or order does not match.');
}

// Verify Checkout's signature using the server-stored order ID.
$expectedSignature = hash_hmac(
    'sha256',
    $orderId . '|' . $paymentId,
    RAZORPAY_KEY_SECRET
);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(400);
    respond(false, 'Invalid payment signature.');
}

// Ask Razorpay for the actual payment status.
$url = 'https://api.razorpay.com/v1/payments/' .
       rawurlencode($paymentId);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_TIMEOUT => 25
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$payment = json_decode($response ?: '', true);

if ($httpCode !== 200 || !is_array($payment)) {
    http_response_code(502);
    respond(false, 'Could not confirm payment with the provider. Retry shortly.');
}

if (
    ($payment['status'] ?? '') !== 'captured' ||
    ($payment['order_id'] ?? '') !== $orderId ||
    ($payment['currency'] ?? '') !== 'INR'
) {
    http_response_code(400);
    respond(false, 'Payment is not captured or its details do not match.');
}

// Confirm exact amount against the saved donation row.
$stmt = $conn->prepare(
    "SELECT amount FROM fund_donations
     WHERE donation_id = ? AND user_id = ? LIMIT 1"
);
$stmt->bind_param('ii', $donationId, $userId);
$stmt->execute();
$donation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (
    !$donation ||
    (int)$payment['amount'] !==
        (int)round((float)$donation['amount'] * 100)
) {
    http_response_code(400);
    respond(false, 'The verified payment amount does not match.');
}

try {
    applyCapturedFundPayment(
        $conn,
        $orderId,
        $paymentId,
        (int)$payment['amount']
    );

    respond(true, 'Payment verified successfully.');
} catch (Throwable $e) {
    http_response_code(500);
    respond(false, 'Payment was received but FoodShare could not update it. Contact support.');
}