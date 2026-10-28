
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

    $id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $status = strtolower(trim($_POST['status'] ?? ''));

    if (!$id || $id < 1) {
        http_response_code(400);
        throw new Exception('Invalid request ID.');
    }

    if (!in_array($status, ['approved', 'hold'], true)) {
        http_response_code(400);
        throw new Exception('Status must be approved or hold.');
    }

    $stmt = $conn->prepare(
        'UPDATE fund_requests SET status = ? WHERE request_id = ?'
    );
    if (!$stmt) {
        throw new Exception('Could not prepare status update.');
    }

    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();

    // affected_rows may be 0 when the row already has that status.
    $check = $conn->prepare(
        'SELECT request_id FROM fund_requests WHERE request_id = ? LIMIT 1'
    );
    $check->bind_param('i', $id);
    $check->execute();
    $found = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$found) {
        http_response_code(404);
        throw new Exception('Fund request not found.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Receiver application status updated to ' . $status . '.'
    ]);
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    error_log('update_fund_status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>