
<?php
// submit_manual_fund_donation.php
// Records a manual UPI donation as pending admin verification.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

/**
 * Send a JSON response and stop execution.
 * This unique function name avoids the previous respond() redeclaration error.
 */
function foodshare_manual_donation_response($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

// Ensure db.php provides a valid mysqli connection named $conn.
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    foodshare_manual_donation_response(
        false,
        'Database connection is not available. Check db.php.'
    );
}

$conn->set_charset('utf8mb4');

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    foodshare_manual_donation_response(
        false,
        'Only POST requests are allowed.'
    );
}

// Read JSON sent by donate-fund.html.
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    foodshare_manual_donation_response(
        false,
        'Invalid request data. Expected JSON.'
    );
}

$userId = isset($data['user_id']) ? trim((string)$data['user_id']) : '';
$requestId = isset($data['request_id']) ? trim((string)$data['request_id']) : '';
$transactionRef = isset($data['transaction_ref'])
    ? trim((string)$data['transaction_ref'])
    : '';

// Validate required fields.
if (
    $userId === '' ||
    !ctype_digit($userId) ||
    (int)$userId < 1
) {
    http_response_code(400);
    foodshare_manual_donation_response(false, 'Invalid donor user ID.');
}

if (
    $requestId === '' ||
    !ctype_digit($requestId) ||
    (int)$requestId < 1
) {
    http_response_code(400);
    foodshare_manual_donation_response(false, 'Please select a valid fund request.');
}

if ($transactionRef === '') {
    http_response_code(400);
    foodshare_manual_donation_response(
        false,
        'Please enter your UPI transaction reference (UTR).'
    );
}

// UPI references vary by payment app. Limit length and reject control characters.
if (
    strlen($transactionRef) > 150 ||
    preg_match('/[\x00-\x1F\x7F]/', $transactionRef)
) {
    http_response_code(400);
    foodshare_manual_donation_response(
        false,
        'The transaction reference is invalid or too long.'
    );
}

$userId = (int)$userId;
$requestId = (int)$requestId;

try {
    /*
     * Confirm that this account exists and has the donor role.
     */
    $userStmt = $conn->prepare(
        'SELECT user_id, role FROM users WHERE user_id = ? LIMIT 1'
    );

    if (!$userStmt) {
        throw new Exception('Could not prepare donor verification query.');
    }

    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        http_response_code(403);
        foodshare_manual_donation_response(
            false,
            'Donor account was not found. Please sign in again.'
        );
    }

    if (strtolower(trim((string)$user['role'])) !== 'donor') {
        http_response_code(403);
        foodshare_manual_donation_response(
            false,
            'Only donors can submit fund donations.'
        );
    }

    /*
     * Begin a transaction so the request is checked consistently
     * before a pending donation record is inserted.
     */
    $conn->begin_transaction();

    // Lock the selected request while validating it.
    $requestStmt = $conn->prepare(
        "SELECT request_id, amount, donated_amount, status
         FROM fund_requests
         WHERE request_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    if (!$requestStmt) {
        throw new Exception('Could not prepare fund request query.');
    }

    $requestStmt->bind_param('i', $requestId);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    $fundRequest = $requestResult->fetch_assoc();
    $requestStmt->close();

    if (!$fundRequest) {
        $conn->rollback();
        http_response_code(404);
        foodshare_manual_donation_response(
            false,
            'The selected fund request was not found.'
        );
    }

    if (strtolower(trim((string)$fundRequest['status'])) !== 'approved') {
        $conn->rollback();
        http_response_code(400);
        foodshare_manual_donation_response(
            false,
            'This fund request is not approved for donations.'
        );
    }

    $requestedAmount = (float)$fundRequest['amount'];
    $alreadyDonated = (float)$fundRequest['donated_amount'];
    $remainingAmount = round($requestedAmount - $alreadyDonated, 2);

    if ($remainingAmount <= 0) {
        $conn->rollback();
        http_response_code(400);
        foodshare_manual_donation_response(
            false,
            'This fund request has already received its requested amount.'
        );
    }

    /*
     * Prevent reuse of a transaction reference.
     * This assumes transaction_ref and payment_status exist
     * in the fund_donations table.
     */
    $duplicateStmt = $conn->prepare(
        'SELECT donation_id
         FROM fund_donations
         WHERE transaction_ref = ?
         LIMIT 1'
    );

    if (!$duplicateStmt) {
        throw new Exception('Could not prepare transaction reference check.');
    }

    $duplicateStmt->bind_param('s', $transactionRef);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    $duplicate = $duplicateResult->fetch_assoc();
    $duplicateStmt->close();

    if ($duplicate) {
        $conn->rollback();
        http_response_code(409);
        foodshare_manual_donation_response(
            false,
            'This UPI transaction reference has already been submitted.'
        );
    }

    /*
     * Record the donation as pending verification.
     * Do not update fund_requests.donated_amount here:
     * that should happen only after the admin confirms payment.
     */
    $paymentMethod = 'UPI';
    $paymentStatus = 'pending_verification';

    $insertStmt = $conn->prepare(
        'INSERT INTO fund_donations
            (user_id, request_id, amount, payment_method,
             transaction_ref, payment_status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if (!$insertStmt) {
        throw new Exception('Could not prepare donation insert query.');
    }

    $insertStmt->bind_param(
        'iidsss',
        $userId,
        $requestId,
        $remainingAmount,
        $paymentMethod,
        $transactionRef,
        $paymentStatus
    );

    if (!$insertStmt->execute()) {
        $insertError = $insertStmt->error;
        $insertStmt->close();
        throw new Exception('Could not save donation: ' . $insertError);
    }

    $donationId = $conn->insert_id;
    $insertStmt->close();

    $conn->commit();

    foodshare_manual_donation_response(
        true,
        'Donation submitted successfully. It is pending admin verification.',
        [
            'donation_id' => $donationId,
            'request_id' => $requestId,
            'amount' => number_format($remainingAmount, 2, '.', ''),
            'payment_status' => 'pending_verification'
        ]
    );

} catch (Throwable $e) {
    // Roll back only if a transaction is still active.
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // Nothing else to do.
    }

    error_log(
        'FoodShare manual fund donation error: ' . $e->getMessage()
    );

    http_response_code(500);

    // Keep database details out of the browser response.
    foodshare_manual_donation_response(
        false,
        'Unable to save the donation. Check the PHP error log or database table structure.'
    );
}
?>
