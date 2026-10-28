
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function respond($success, $message, $extra = [], $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

$conn = null;
$transactionStarted = false;

try {
    // Only accept POST requests.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'POST requests only.', [], 405);
    }

    // Require database connection.
    require_once __DIR__ . '/db.php';

    // IMPORTANT: Do not reset $conn to null after including db.php.
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection is unavailable.');
    }

    if ($conn->connect_error) {
        throw new Exception('Database connection failed.');
    }

    // Check that Razorpay configuration exists.
    $configFile = __DIR__ . '/payment_config.php';

    if (!file_exists($configFile)) {
        throw new Exception('payment_config.php is missing.');
    }

    require_once $configFile;

    if (
        !defined('RAZORPAY_KEY_ID') ||
        !defined('RAZORPAY_KEY_SECRET') ||
        trim(RAZORPAY_KEY_ID) === '' ||
        trim(RAZORPAY_KEY_SECRET) === '' ||
        strpos(RAZORPAY_KEY_ID, 'REPLACE_WITH') !== false ||
        strpos(RAZORPAY_KEY_SECRET, 'REPLACE_WITH') !== false
    ) {
        throw new Exception('Valid Razorpay API keys are not configured.');
    }

    // Confirm donor session.
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        respond(false, 'Please sign in again as a donor.', [], 401);
    }

    // Read JSON request from donate-fund.html.
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        respond(false, 'Invalid JSON request.', [], 400);
    }

    $requestId = filter_var(
        $data['request_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $amountInput = $data['amount'] ?? null;

    if (
        $requestId === false ||
        $requestId === null ||
        $requestId <= 0 ||
        !is_numeric($amountInput)
    ) {
        respond(false, 'Invalid request ID or donation amount.', [], 400);
    }

    $amount = round((float)$amountInput, 2);

    if (
        !is_finite($amount) ||
        $amount < 1 ||
        $amount > 10000000
    ) {
        respond(
            false,
            'Donation amount must be at least ₹1.00.',
            [],
            400
        );
    }

    $amountPaise = (int)round($amount * 100);

    // Verify donor and lock the approved request.
    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare(
        'SELECT role FROM users WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$user ||
        strtolower(trim($user['role'] ?? '')) !== 'donor'
    ) {
        throw new Exception('Only donor accounts can donate funds.');
    }

    $stmt = $conn->prepare(
        'SELECT amount, donated_amount, status
         FROM fund_requests
         WHERE request_id = ?
         FOR UPDATE'
    );
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$request ||
        strtolower(trim($request['status'] ?? '')) !== 'approved'
    ) {
        throw new Exception(
            'This fund request is not available for donation.'
        );
    }

    $remaining = round(
        (float)$request['amount'] -
        (float)$request['donated_amount'],
        2
    );

    if ($remaining <= 0) {
        throw new Exception(
            'This receiver has already received the requested amount.'
        );
    }

    if ($amount > $remaining) {
        throw new Exception(
            'Maximum donation allowed is ₹' .
            number_format($remaining, 2)
        );
    }

    /*
     * Create a Razorpay order.
     * The amount sent to Razorpay is in paise.
     */
    $receipt = 'fs_' . $requestId . '_' . $userId . '_' .
        bin2hex(random_bytes(4));

    $payload = json_encode([
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => $receipt,
        'notes' => [
            'fund_request_id' => (string)$requestId,
            'donor_user_id' => (string)$userId
        ]
    ]);

    if ($payload === false) {
        throw new Exception('Could not prepare payment request.');
    }

    $ch = curl_init('https://api.razorpay.com/v1/orders');

    if ($ch === false) {
        throw new Exception(
            'Could not initialize the payment connection.'
        );
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_USERPWD =>
            RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);

    $gatewayResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($gatewayResponse === false) {
        error_log('FoodShare Razorpay cURL error: ' . $curlError);
        throw new Exception(
            'Unable to connect to the payment provider. Check the server log.'
        );
    }

    $order = json_decode($gatewayResponse, true);

    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        !is_array($order) ||
        empty($order['id']) ||
        (int)($order['amount'] ?? 0) !== $amountPaise ||
        ($order['currency'] ?? '') !== 'INR'
    ) {
        error_log(
            'FoodShare Razorpay order error. HTTP ' . $httpCode .
            ': ' . substr($gatewayResponse, 0, 1500)
        );

        throw new Exception(
            'Razorpay could not create the order. Check the PHP error log.'
        );
    }

    $orderId = $order['id'];

    // Save a pending payment attempt, not a completed donation.
    $method = 'razorpay';
    $paymentStatus = 'created';
    $transactionRef = null;

    $stmt = $conn->prepare(
        'INSERT INTO fund_donations
            (user_id, request_id, amount, payment_method,
             transaction_ref, payment_status, gateway_order_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->bind_param(
        'iidssss',
        $userId,
        $requestId,
        $amount,
        $method,
        $transactionRef,
        $paymentStatus,
        $orderId
    );

    $stmt->execute();
    $donationId = $conn->insert_id;
    $stmt->close();

    // Commit the pending attempt.
    $conn->commit();
    $transactionStarted = false;

    // Response fields expected by donate-fund.html.
    respond(true, 'Payment order created successfully.', [
        'donation_id' => $donationId,
        'order_id' => $orderId,
        'amount' => $amountPaise,
        'currency' => 'INR',
        'key_id' => RAZORPAY_KEY_ID
    ]);

} catch (Throwable $e) {
    if (
        $transactionStarted &&
        $conn instanceof mysqli
    ) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log(
                'FoodShare rollback error: ' .
                $rollbackError->getMessage()
            );
        }
    }

    error_log(
        'create_fund_order.php: ' . $e->getMessage()
    );

    // Return JSON instead of PHP warnings or HTML.
    respond(
        false,
        $e->getMessage(),
        [],
        500
    );
}
?>
