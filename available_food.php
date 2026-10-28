
<?php

header("Content-Type: application/json");

require_once "db.php";

try {

    /*
     * Mark expired donations as Expired
     */
    $updateSql = "
        UPDATE food_donations
        SET status = 'Expired'
        WHERE status = 'Available'
        AND expiry_datetime <= NOW()
    ";

    $conn->query($updateSql);


    /*
     * Get available food
     */
    $sql = "
        SELECT
            donation_id,
            user_id,
            donor_name,
            contact,
            food_type,
            quantity,
            pickup_datetime,
            expiry_datetime,
            locality,
            location_name,
            details,
            status,
            created_at
        FROM food_donations
        WHERE status = 'Available'
        AND expiry_datetime > NOW()
        ORDER BY pickup_datetime ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception(
            "Database query failed: " . $conn->error
        );
    }

    $food = [];

    while ($row = $result->fetch_assoc()) {

        /*
         * location_name is the donor's
         * readable live-location address.
         *
         * Fallback to locality if it is empty.
         */
        $row["pickup_location"] =
            !empty($row["location_name"])
                ? $row["location_name"]
                : $row["locality"];

        $food[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $food
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();

?>
