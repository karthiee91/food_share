
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";


/*
|--------------------------------------------------------------------------
| Helper function
|--------------------------------------------------------------------------
*/

function sendResponse($success, $message, $extra = [], $httpCode = 200)
{
    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        )
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | Only POST requests
    |--------------------------------------------------------------------------
    */

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        sendResponse(
            false,
            "Invalid request method.",
            [],
            405
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Read input
    |--------------------------------------------------------------------------
    |
    | Supports normal FormData POST.
    |
    */

    $donation_id =
        intval($_POST["donation_id"] ?? 0);

    $receiver_id =
        intval($_POST["receiver_id"] ?? 0);

    $receiver_name =
        trim($_POST["receiver_name"] ?? "");

    $receiver_phone =
        trim($_POST["receiver_phone"] ?? "");

    $message =
        trim($_POST["message"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validate donation ID
    |--------------------------------------------------------------------------
    */

    if ($donation_id <= 0) {

        sendResponse(
            false,
            "Invalid food donation ID.",
            [],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate receiver ID
    |--------------------------------------------------------------------------
    */

    if ($receiver_id <= 0) {

        sendResponse(
            false,
            "Invalid receiver ID. Please sign in again.",
            [],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate receiver name
    |--------------------------------------------------------------------------
    */

    if ($receiver_name === "") {

        sendResponse(
            false,
            "Receiver name is required.",
            [],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate receiver phone
    |--------------------------------------------------------------------------
    */

    if (!preg_match(
        '/^[6-9][0-9]{9}$/',
        $receiver_phone
    )) {

        sendResponse(
            false,
            "Enter a valid 10-digit Indian mobile number.",
            [],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check receiver account
    |--------------------------------------------------------------------------
    */

    $userSql = "
        SELECT
            user_id,
            name,
            role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ";

    $userStmt =
        $conn->prepare($userSql);


    if (!$userStmt) {

        throw new Exception(
            "User query preparation failed: " .
            $conn->error
        );
    }


    $userStmt->bind_param(
        "i",
        $receiver_id
    );

    if (!$userStmt->execute()) {

        $error =
            $userStmt->error;

        $userStmt->close();

        throw new Exception(
            "User query failed: " . $error
        );
    }


    /*
     * Do NOT use get_result().
     * Use bind_result() for better compatibility.
     */

    $userStmt->bind_result(
        $db_user_id,
        $db_user_name,
        $db_user_role
    );


    if (!$userStmt->fetch()) {

        $userStmt->close();

        sendResponse(
            false,
            "Receiver account was not found.",
            [],
            400
        );
    }


    $userStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Make sure this is a receiver account
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(trim($db_user_role)) !== "receiver"
    ) {

        sendResponse(
            false,
            "Only receiver accounts can claim food.",
            [],
            403
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Lock the food donation row
    |--------------------------------------------------------------------------
    */

    $foodSql = "
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
            status
        FROM food_donations
        WHERE donation_id = ?
        LIMIT 1
        FOR UPDATE
    ";


    $foodStmt =
        $conn->prepare($foodSql);


    if (!$foodStmt) {

        throw new Exception(
            "Food query preparation failed: " .
            $conn->error
        );
    }


    $foodStmt->bind_param(
        "i",
        $donation_id
    );


    if (!$foodStmt->execute()) {

        $error =
            $foodStmt->error;

        $foodStmt->close();

        throw new Exception(
            "Food query failed: " . $error
        );
    }


    /*
     * Use bind_result() instead of get_result().
     */

    $foodStmt->bind_result(
        $db_donation_id,
        $db_donor_user_id,
        $db_donor_name,
        $db_donor_contact,
        $db_food_type,
        $db_quantity,
        $db_pickup_datetime,
        $db_expiry_datetime,
        $db_locality,
        $db_location_name,
        $db_details,
        $db_status
    );


    if (!$foodStmt->fetch()) {

        $foodStmt->close();

        throw new Exception(
            "Food donation was not found."
        );
    }


    $foodStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Check expiry
    |--------------------------------------------------------------------------
    */

    $expiryTimestamp =
        strtotime($db_expiry_datetime);


    if (
        $expiryTimestamp !== false &&
        $expiryTimestamp <= time()
    ) {

        /*
         * Mark expired.
         */

        $expireStmt = $conn->prepare("
            UPDATE food_donations
            SET status = 'Expired'
            WHERE donation_id = ?
        ");


        if ($expireStmt) {

            $expireStmt->bind_param(
                "i",
                $donation_id
            );

            $expireStmt->execute();

            $expireStmt->close();
        }


        throw new Exception(
            "This food donation has expired."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check current status
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(trim($db_status)) !== "available"
    ) {

        throw new Exception(
            "This food is no longer available for claiming. Current status: " .
            $db_status
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check whether this receiver already claimed it
    |--------------------------------------------------------------------------
    */

    $existingSql = "
        SELECT
            claim_id
        FROM food_claims
        WHERE donation_id = ?
        AND receiver_id = ?
        LIMIT 1
    ";


    $existingStmt =
        $conn->prepare($existingSql);


    if (!$existingStmt) {

        throw new Exception(
            "Claim check preparation failed: " .
            $conn->error
        );
    }


    $existingStmt->bind_param(
        "ii",
        $donation_id,
        $receiver_id
    );


    if (!$existingStmt->execute()) {

        $error =
            $existingStmt->error;

        $existingStmt->close();

        throw new Exception(
            "Claim check failed: " . $error
        );
    }


    $existingStmt->bind_result(
        $existing_claim_id
    );


    if ($existingStmt->fetch()) {

        $existingStmt->close();

        throw new Exception(
            "You have already claimed this food."
        );
    }


    $existingStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert food claim
    |--------------------------------------------------------------------------
    |
    | Matches your existing food_claims table:
    |
    | claim_id
    | donation_id
    | receiver_id
    | receiver_name
    | receiver_phone
    | message
    | status
    | created_at
    |
    */

    $insertSql = "
        INSERT INTO food_claims
        (
            donation_id,
            receiver_id,
            receiver_name,
            receiver_phone,
            message,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            'Pending'
        )
    ";


    $insertStmt =
        $conn->prepare($insertSql);


    if (!$insertStmt) {

        throw new Exception(
            "Claim insert preparation failed: " .
            $conn->error
        );
    }


    $insertStmt->bind_param(
        "iisss",
        $donation_id,
        $receiver_id,
        $receiver_name,
        $receiver_phone,
        $message
    );


    if (!$insertStmt->execute()) {

        $error =
            $insertStmt->error;

        $insertStmt->close();

        throw new Exception(
            "Unable to save food claim: " . $error
        );
    }


    $claim_id =
        $insertStmt->insert_id;


    $insertStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Mark food as claimed
    |--------------------------------------------------------------------------
    */

    $updateSql = "
        UPDATE food_donations
        SET status = 'Claimed'
        WHERE donation_id = ?
        AND status = 'Available'
        AND expiry_datetime > NOW()
    ";


    $updateStmt =
        $conn->prepare($updateSql);


    if (!$updateStmt) {

        throw new Exception(
            "Food status update preparation failed: " .
            $conn->error
        );
    }


    $updateStmt->bind_param(
        "i",
        $donation_id
    );


    if (!$updateStmt->execute()) {

        $error =
            $updateStmt->error;

        $updateStmt->close();

        throw new Exception(
            "Unable to update food status: " . $error
        );
    }


    /*
     * Make sure the food was actually updated.
     */

    if ($updateStmt->affected_rows !== 1) {

        $updateStmt->close();

        throw new Exception(
            "Food could not be marked as claimed. It may have already been claimed."
        );
    }


    $updateStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Success response
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        "Food claimed successfully. The donor has been notified.",
        [
            "claim_id" => $claim_id,
            "donation_id" => $donation_id,
            "food_type" => $db_food_type,
            "quantity" => $db_quantity,
            "donor_name" => $db_donor_name,
            "donor_contact" => $db_donor_contact,
            "pickup_datetime" => $db_pickup_datetime,
            "expiry_datetime" => $db_expiry_datetime,
            "locality" => $db_locality,
            "location_name" => $db_location_name
        ],
        200
    );


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback if transaction is active
    |--------------------------------------------------------------------------
    */

    if (
        isset($conn) &&
        $conn instanceof mysqli
    ) {

        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Ignore rollback error.
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Return actual error as JSON
    |--------------------------------------------------------------------------
    */

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} finally {

    if (
        isset($conn) &&
        $conn instanceof mysqli
    ) {

        $conn->close();
    }
}

?>

