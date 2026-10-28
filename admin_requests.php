
<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/db.php';

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection unavailable.');
    }

    $sql = "SELECT request_id, user_id, receiver_name, age, phone,
                   amount, donated_amount, reason, medical_certificate,
                   payment_qr, details, status, created_at
            FROM fund_requests
            ORDER BY created_at DESC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Could not load receiver applications.');
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = (float)$row['amount'];
        $row['donated_amount'] = (float)$row['donated_amount'];
        $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    error_log('admin_requests.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load fund requests. Check the PHP error log.'
    ]);
}
?>
