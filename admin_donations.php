
<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/db.php';

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection unavailable.');
    }

    $sql = "SELECT d.donation_id, d.user_id, d.request_id,
                   d.amount, d.payment_method, d.transaction_ref,
                   d.payment_status, r.receiver_name, r.phone
            FROM fund_donations d
            LEFT JOIN fund_requests r ON r.request_id = d.request_id
            ORDER BY d.donation_id DESC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Could not load donations.');
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = (float)$row['amount'];
        $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    error_log('admin_donations.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load donations. Check the fund_donations table structure.'
    ]);
}
?>
