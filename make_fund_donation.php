
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method.");
    }

    /*
     * Get submitted values
     */
    $donorId = intval($_POST["user_id"] ?? 0);
    $requestId = intval($_POST["request_id"] ?? 0);
    $amount = floatval($_POST["amount"] ?? 0);
    $transactionRef = trim($_POST["transaction_ref"] ?? "");


    /*
     * Basic validation
     */
    if ($donorId <= 0) {
        throw new Exception(
            "Invalid donor ID. Please sign in again."
        );
    }

    if ($requestId <= 0) {
        throw new Exception(
            "Invalid receiver request."
        );
    }

    if ($amount <= 0) {
        throw new Exception(
            "Donation amount must be greater than zero."
        );
    }

    if ($amount > 10000000) {
        throw new Exception(
            "Donation amount is too large."
        );
    }

    /*
     * Transaction reference is required
     */
    if ($transactionRef === "") {
        throw new Exception(
            "Please enter the UPI transaction/reference ID."
        );
    }


    /*
     * --------------------------------------------------
     * VERIFY LOGGED-IN USER
     * --------------------------------------------------
     */

    $userSql = "
        SELECT
            user_id,
            role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ";

    $userStmt = $conn->prepare($userSql);

    if (!$userStmt) {
        throw new Exception(
            "Unable to verify donor account."
        );
    }

    $userStmt->bind_param(
        "i",
        $donorId
    );

    $userStmt->execute();

    $userResult = $userStmt->get_result();

    if (
        !$userResult ||
        $userResult->num_rows === 0
    ) {

        $userStmt->close();

        throw new Exception(
            "User account was not found. Please sign in again."
        );
    }

    $user = $userResult->fetch_assoc();

    $userStmt->close();


    /*
     * Normalize role.
     *
     * Example:
     * Donor  -> donor
     * DONOR  -> donor
     * donor  -> donor
     */
    $role = strtolower(
        trim(
            (string)($user["role"] ?? "")
        )
    );


    /*
     * Only donor accounts can make donations.
     */
    if ($role !== "donor") {

        throw new Exception(
            "Only donor accounts can make fund donations. " .
            "Your current account role is: " .
            ($user["role"] ?? "not assigned") .
            ". Please sign in with a Donor account."
        );
    }


    /*
     * --------------------------------------------------
     * START DATABASE TRANSACTION
     * --------------------------------------------------
     */

    $conn->begin_transaction();


    /*
     * Lock receiver request.
     */
    $requestSql = "
        SELECT
            request_id,
            amount,
            donated_amount,
            status
        FROM fund_requests
        WHERE request_id = ?
        FOR UPDATE
    ";

    $requestStmt = $conn->prepare($requestSql);

    if (!$requestStmt) {
        throw new Exception(
            "Unable to prepare receiver request query."
        );
    }

    $requestStmt->bind_param(
        "i",
        $requestId
    );

    $requestStmt->execute();

    $requestResult = $requestStmt->get_result();

    if (
        !$requestResult ||
        $requestResult->num_rows === 0
    ) {

        $requestStmt->close();

        throw new Exception(
            "Receiver fund request was not found."
        );
    }

    $request = $requestResult->fetch_assoc();

    $requestStmt->close();


    /*
     * --------------------------------------------------
     * CHECK REQUEST STATUS
     * --------------------------------------------------
     */

    $requestStatus = strtolower(
        trim(
            (string)($request["status"] ?? "")
        )
    );

    if ($requestStatus !== "approved") {

        throw new Exception(
            "This receiver request is not currently open for donations."
        );
    }


    /*
     * --------------------------------------------------
     * CALCULATE REMAINING AMOUNT
     * --------------------------------------------------
     */

    $required = (float)$request["amount"];

    $alreadyDonated = (float)$request["donated_amount"];

    $remaining = $required - $alreadyDonated;


    if ($remaining <= 0) {

        /*
         * Mark request completed.
         */
        $completeStmt = $conn->prepare("
            UPDATE fund_requests
            SET status = 'completed'
            WHERE request_id = ?
        ");

        if ($completeStmt) {

            $completeStmt->bind_param(
                "i",
                $requestId
            );

            $completeStmt->execute();

            $completeStmt->close();
        }

        throw new Exception(
            "This receiver has already received the required fund."
        );
    }


    /*
     * Donation cannot exceed remaining requirement.
     */
    if ($amount > $remaining) {

        throw new Exception(
            "Maximum donation allowed for this request is ₹" .
            number_format($remaining, 2)
        );
    }


    /*
     * --------------------------------------------------
     * INSERT DONATION
     * --------------------------------------------------
     */

    $paymentMethod = "UPI";

    $paymentStatus = "Paid";


    $insertSql = "
        INSERT INTO fund_donations
        (
            user_id,
            request_id,
            amount,
            payment_method,
            transaction_ref,
            payment_status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";


    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {

        throw new Exception(
            "Unable to prepare donation query: " .
            $conn->error
        );
    }


    $insertStmt->bind_param(
        "iidsss",
        $donorId,
        $requestId,
        $amount,
        $paymentMethod,
        $transactionRef,
        $paymentStatus
    );


    if (!$insertStmt->execute()) {

        $error = $insertStmt->error;

        $insertStmt->close();

        throw new Exception(
            "Unable to record fund donation: " .
            $error
        );
    }


    $donationId = $insertStmt->insert_id;

    $insertStmt->close();


    /*
     * --------------------------------------------------
     * UPDATE RECEIVER FUND BALANCE
     * --------------------------------------------------
     */

    $newDonatedAmount =
        $alreadyDonated + $amount;

    $newRemaining =
        $required - $newDonatedAmount;


    if ($newRemaining <= 0) {

        $newStatus = "completed";

    } else {

        $newStatus = "approved";
    }


    $updateSql = "
        UPDATE fund_requests
        SET
            donated_amount = ?,
            status = ?
        WHERE request_id = ?
    ";


    $updateStmt = $conn->prepare($updateSql);

    if (!$updateStmt) {

        throw new Exception(
            "Unable to prepare balance update."
        );
    }


    $updateStmt->bind_param(
        "dsi",
        $newDonatedAmount,
        $newStatus,
        $requestId
    );


    if (!$updateStmt->execute()) {

        $error = $updateStmt->error;

        $updateStmt->close();

        throw new Exception(
            "Unable to update receiver fund balance: " .
            $error
        );
    }


    $updateStmt->close();


    /*
     * --------------------------------------------------
     * COMMIT
     * --------------------------------------------------
     */

    $conn->commit();


    /*
     * --------------------------------------------------
     * SUCCESS RESPONSE
     * --------------------------------------------------
     */

    echo json_encode([
        "success" => true,

        "message" =>
            $newStatus === "completed"
                ? "Donation successful. This receiver is now fully funded."
                : "Donation successful. Thank you for supporting this receiver.",

        "donation_id" => $donationId,

        "donated_amount" =>
            $newDonatedAmount,

        "remaining_amount" =>
            max(0, $newRemaining),

        "status" =>
            $newStatus
    ]);

} catch (Throwable $e) {

    /*
     * Roll back any database changes.
     */
    if (isset($conn) && $conn) {

        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }


    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} finally {

    if (isset($conn) && $conn) {
        $conn->close();
    }
}

?>
