
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";


try {

    /*
     * Load all fund requests.
     *
     * donated_amount is the total amount already
     * received for this receiver.
     *
     * remaining_amount is calculated from:
     *
     * amount - donated_amount
     */

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
            medical_certificate,
            details,
            status,
            created_at
        FROM fund_requests
        ORDER BY created_at DESC
    ";


    $result = $conn->query($sql);


    if (!$result) {

        throw new Exception(
            "Unable to load fund requests: " .
            $conn->error
        );
    }


    $requests = [];


    while ($row = $result->fetch_assoc()) {

        /*
         * Convert database values to numbers.
         */

        $requiredAmount =
            (float)($row["amount"] ?? 0);

        $donatedAmount =
            (float)($row["donated_amount"] ?? 0);


        /*
         * Calculate remaining amount.
         */

        $remainingAmount =
            max(
                0,
                $requiredAmount - $donatedAmount
            );


        $row["amount"] =
            number_format(
                $requiredAmount,
                2,
                ".",
                ""
            );


        $row["donated_amount"] =
            number_format(
                $donatedAmount,
                2,
                ".",
                ""
            );


        $row["remaining_amount"] =
            number_format(
                $remainingAmount,
                2,
                ".",
                ""
            );


        /*
         * Create certificate URL.
         *
         * apply_fund.php stores the certificate
         * inside uploads/fund_certificates/.
         */

        if (
            !empty(
                $row["medical_certificate"]
            )
        ) {

            $row["medical_certificate_url"] =
                "uploads/fund_certificates/" .
                rawurlencode(
                    basename(
                        $row["medical_certificate"]
                    )
                );

        } else {

            $row["medical_certificate_url"] =
                null;
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
        ]
    );

} finally {

    if (isset($conn) && $conn) {
        $conn->close();
    }
}

?>
