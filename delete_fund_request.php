
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
    if (!$id || $id < 1) {
        http_response_code(400);
        throw new Exception('Invalid request ID.');
    }

    $check = $conn->prepare(
        'SELECT donation_id FROM fund_donations WHERE request_id = ? LIMIT 1'
    );
    $check->bind_param('i', $id);
    $check->execute();
    $hasDonations = $check->get_result()->num_rows > 0;
    $check->close();

    if ($hasDonations) {
        http_response_code(409);
        throw new Exception(
            'This request has donation records. Keep it to preserve donation history.'
        );
    }

    $delete = $conn->prepare(
        'DELETE FROM fund_requests WHERE request_id = ?'
    );
    $delete->bind_param('i', $id);
    $delete->execute();
    $deleted = $delete->affected_rows;
    $delete->close();

    if (!$deleted) {
        http_response_code(404);
        throw new Exception('Fund request not found.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Fund request deleted successfully.'
    ]);
} catch (Throwable $e) {
    error_log('delete_fund_request.php: ' . $e->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
