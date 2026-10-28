
<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('POST requests only.');
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection unavailable.');
    }

    $id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
    $action = strtolower(trim($_POST['action'] ?? ''));

    if (!$id || $id < 1) {
        http_response_code(400);
        throw new Exception('Invalid donation ID.');
    }

    if (!in_array($action, ['approve', 'reject', 'hold'], true)) {
        http_response_code(400);
        throw new Exception('Action must be approve, reject or hold.');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare(
        "SELECT donation_id, request_id, amount, payment_status
         FROM fund_donations
         WHERE donation_id = ?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $donation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$donation) {
        throw new Exception('Donation not found.');
    }

    $current = strtolower(trim((string)$donation['payment_status']));
    if ($current !== 'pending_verification') {
        throw new Exception(
            'This donation is not pending verification. Current status: ' . $current
        );
    }

    if ($action === 'hold') {
        $newStatus = 'pending_verification';

        $update = $conn->prepare(
            'UPDATE fund_donations SET payment_status = ? WHERE donation_id = ?'
        );
        $update->bind_param('si', $newStatus, $id);
        $update->execute();
        $update->close();

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Donation remains pending verification.'
        ]);
        exit;
    }

    if ($action === 'reject') {
        $newStatus = 'rejected';

        $update = $conn->prepare(
            'UPDATE fund_donations SET payment_status = ? WHERE donation_id = ?'
        );
        $update->bind_param('si', $newStatus, $id);
        $update->execute();
        $update->close();

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Donation rejected. No receiver total was changed.'
        ]);
        exit;
    }

    // Approval must only happen after checking the actual UPI/bank payment.
    $requestId = (int)$donation['request_id'];
    $amount = (float)$donation['amount'];

    if ($amount <= 0) {
        throw new Exception('Donation amount is invalid.');
    }

    $requestStmt = $conn->prepare(
        "SELECT amount, donated_amount, status
         FROM fund_requests
         WHERE request_id = ?
         LIMIT 1 FOR UPDATE"
    );
    $requestStmt->bind_param('i', $requestId);
    $requestStmt->execute();
    $request = $requestStmt->get_result()->fetch_assoc();
    $requestStmt->close();

    if (!$request) {
        throw new Exception('Linked receiver request was not found.');
    }

    if (strtolower(trim((string)$request['status'])) !== 'approved') {
        throw new Exception('The receiver application must be approved first.');
    }

    $requested = (float)$request['amount'];
    $donated = (float)$request['donated_amount'];
    $remaining = max(0, round($requested - $donated, 2));

    if ($remaining <= 0) {
        throw new Exception('This receiver request is already fully funded.');
    }

    if ($amount > $remaining) {
        throw new Exception(
            'Donation amount exceeds the receiver request remaining balance.'
        );
    }

    $newStatus = 'approved';
    $updateDonation = $conn->prepare(
        'UPDATE fund_donations SET payment_status = ? WHERE donation_id = ?'
    );
    $updateDonation->bind_param('si', $newStatus, $id);
    $updateDonation->execute();
    $updateDonation->close();

    $newDonated = round($donated + $amount, 2);
    $newRequestStatus = ($newDonated >= $requested) ? 'completed' : 'approved';

    $updateRequest = $conn->prepare(
        'UPDATE fund_requests SET donated_amount = ?, status = ? WHERE request_id = ?'
    );
    $updateRequest->bind_param('dsi', $newDonated, $newRequestStatus, $requestId);
    $updateRequest->execute();
    $updateRequest->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified. Receiver funding total updated.'
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    error_log('verify_fund_donation.php: ' . $e->getMessage());
    if (http_response_code() < 400) {
        http_response_code(400);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
