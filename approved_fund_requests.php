
<?php

header("Content-Type: application/json; charset=UTF-8");

ini_set("display_errors", "0");
ini_set("log_errors", "1");

require_once "db.php";

try {

    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed.");
    }

    $sql = "
        SELECT
            request_id,
            user_id,
            receiver_name,
            age,
            phone,
            amount,
            donated_amount,
            reason,
            details,
            medical_certificate,
            payment_qr,
            status,
            created_at
        FROM fund_requests
        WHERE status = 'approved'
        ORDER BY created_at DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception(
            "Database query failed: " . $conn->error
        );
    }

    $requests = [];

    while ($row = $result->fetch_assoc()) {

        $amount = (float)($row["amount"] ?? 0);
        $donated = (float)($row["donated_amount"] ?? 0);

        $remaining = max($amount - $donated, 0);

        /*
         * Medical certificate path
         */
        if (!empty($row["medical_certificate"])) {

            $filename = basename($row["medical_certificate"]);

            $row["medical_certificate"] =
                "uploads/fund_certificates/" .
                rawurlencode($filename);

        } else {

            $row["medical_certificate"] = "";

        }

        /*
         * Payment QR path
         */
        if (!empty($row["payment_qr"])) {

            $filename = basename($row["payment_qr"]);

            $row["payment_qr"] =
                "uploads/payment_qr/" .
                rawurlencode($filename);

        } else {

            $row["payment_qr"] = "";

        }

        $row["amount"] = $amount;
        $row["donated_amount"] = $donated;
        $row["remaining_amount"] = $remaining;

        /*
         * Do not show requests that are already completely funded.
         */
        if ($remaining <= 0) {
            continue;
        }

        $requests[] = $row;
    }

    echo json_encode(
        [
            "success" => true,
            "data" => $requests
        ],
        JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode(
        [
            "success" => false,
            "message" => $e->getMessage()
        ],
        JSON_UNESCAPED_SLASHES
    );
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>

