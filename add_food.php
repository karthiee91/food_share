
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";


try {

    /*
     * Only POST requests are allowed.
     */

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        throw new Exception(
            "Invalid request method."
        );
    }


    /*
     * Get submitted values.
     */

    $user_id =
        intval($_POST["user_id"] ?? 0);

    $donor_name =
        trim($_POST["donor_name"] ?? "");

    $contact =
        trim($_POST["contact"] ?? "");

    $food_type =
        trim($_POST["food_type"] ?? "");

    $quantity =
        trim($_POST["quantity"] ?? "");

    $pickup_datetime =
        trim($_POST["pickup_datetime"] ?? "");

    $expiry_datetime =
        trim($_POST["expiry_datetime"] ?? "");

    $locality =
        trim($_POST["locality"] ?? "");

    $location_name =
        trim($_POST["location_name"] ?? "");

    $details =
        trim($_POST["details"] ?? "");


    /*
     * Basic validation.
     */

    if ($user_id <= 0) {

        throw new Exception(
            "Invalid user ID. Please sign in again."
        );
    }


    if ($donor_name === "") {

        throw new Exception(
            "Donor name is required."
        );
    }


    if ($contact === "") {

        throw new Exception(
            "Mobile number is required."
        );
    }


    if ($food_type === "") {

        throw new Exception(
            "Food type is required."
        );
    }


    if ($quantity === "") {

        throw new Exception(
            "Quantity is required."
        );
    }


    if ($pickup_datetime === "") {

        throw new Exception(
            "Pickup date and time are required."
        );
    }


    if ($expiry_datetime === "") {

        throw new Exception(
            "Expiry date and time are required."
        );
    }


    if ($locality === "") {

        throw new Exception(
            "Locality is required."
        );
    }


    if ($location_name === "") {

        throw new Exception(
            "Pickup location is required."
        );
    }


    /*
     * Validate Indian mobile number.
     */

    if (!preg_match(
        '/^[6-9][0-9]{9}$/',
        $contact
    )) {

        throw new Exception(
            "Enter a valid 10-digit Indian mobile number."
        );
    }


    /*
     * IMPORTANT:
     * OTP must have been verified in this session.
     */

    if (
        !isset($_SESSION["phone_verified"]) ||
        $_SESSION["phone_verified"] !== true
    ) {

        throw new Exception(
            "Please verify your mobile number with OTP before donating food."
        );
    }


    /*
     * Make sure the verified phone matches
     * the number being submitted.
     */

    if (
        !isset($_SESSION["verified_phone"]) ||
        $_SESSION["verified_phone"] !== $contact
    ) {

        throw new Exception(
            "The verified mobile number does not match the submitted mobile number."
        );
    }


    /*
     * Check the logged-in user's account.
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
            "Unable to prepare user query: " .
            $conn->error
        );
    }


    $userStmt->bind_param(
        "i",
        $user_id
    );

    $userStmt->execute();


    $userResult =
        $userStmt->get_result();


    if (!$userResult) {

        $userStmt->close();

        throw new Exception(
            "Unable to verify user account."
        );
    }


    if ($userResult->num_rows === 0) {

        $userStmt->close();

        throw new Exception(
            "User account was not found."
        );
    }


    $user = $userResult->fetch_assoc();

    $userStmt->close();


    /*
     * Only donors can add food.
     */

    if (
        strtolower(trim($user["role"])) !== "donor"
    ) {

        throw new Exception(
            "Only donor accounts can donate food."
        );
    }


    /*
     * Convert HTML datetime values into
     * MySQL DATETIME format.
     */

    $pickupTimestamp =
        strtotime($pickup_datetime);

    $expiryTimestamp =
        strtotime($expiry_datetime);


    if ($pickupTimestamp === false) {

        throw new Exception(
            "Invalid pickup date and time."
        );
    }


    if ($expiryTimestamp === false) {

        throw new Exception(
            "Invalid expiry date and time."
        );
    }


    /*
     * Pickup must be in the future.
     */

    if (
        $pickupTimestamp <= time()
    ) {

        throw new Exception(
            "Pickup date and time must be in the future."
        );
    }


    /*
     * Keep the existing FoodShare rule:
     * pickup should be within 48 hours.
     */

    if (
        $pickupTimestamp >
        (time() + (48 * 60 * 60))
    ) {

        throw new Exception(
            "Pickup time must be within the next 48 hours."
        );
    }


    /*
     * Expiry must be after pickup.
     */

    if (
        $expiryTimestamp <= $pickupTimestamp
    ) {

        throw new Exception(
            "Expiry date and time must be after pickup date and time."
        );
    }


    /*
     * Convert to MySQL DATETIME.
     */

    $pickupDb =
        date(
            "Y-m-d H:i:s",
            $pickupTimestamp
        );

    $expiryDb =
        date(
            "Y-m-d H:i:s",
            $expiryTimestamp
        );


    /*
     * Insert donation.
     *
     * IMPORTANT:
     * There is NO latitude or longitude here.
     *
     * Only location_name is stored.
     */

    $sql = "
        INSERT INTO food_donations
        (
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
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'Available'
        )
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to prepare donation query: " .
            $conn->error
        );
    }


    /*
     * 1 integer + 9 strings.
     */

    $stmt->bind_param(
        "isssssssss",
        $user_id,
        $donor_name,
        $contact,
        $food_type,
        $quantity,
        $pickupDb,
        $expiryDb,
        $locality,
        $location_name,
        $details
    );


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            "Unable to save food donation: " .
            $error
        );
    }


    $donationId =
        $stmt->insert_id;


    $stmt->close();


    /*
     * OTP verification is consumed after
     * successful donation.
     *
     * This means a donor must verify again
     * for the next donation.
     */

    unset(
        $_SESSION["phone_verified"]
    );

    unset(
        $_SESSION["verified_phone"]
    );


    echo json_encode([
        "success" => true,
        "message" => "Food donation added successfully.",
        "donation_id" => $donationId
    ]);


} catch (Throwable $e) {

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

