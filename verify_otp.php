
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");


try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        throw new Exception(
            "Invalid request method."
        );
    }


    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $otp = trim(
        $_POST["otp"] ?? ""
    );


    /*
     * Validate phone.
     */

    if (!preg_match(
        '/^[6-9][0-9]{9}$/',
        $phone
    )) {

        throw new Exception(
            "Invalid mobile number."
        );
    }


    /*
     * Validate OTP format.
     */

    if (!preg_match(
        '/^[0-9]{6}$/',
        $otp
    )) {

        throw new Exception(
            "Enter the 6-digit OTP."
        );
    }


    /*
     * Make sure an OTP was requested.
     */

    if (
        !isset($_SESSION["demo_otp"]) ||
        !isset($_SESSION["demo_otp_phone"])
    ) {

        throw new Exception(
            "Please click Send OTP first."
        );
    }


    /*
     * Make sure OTP belongs to this phone number.
     */

    if (
        $_SESSION["demo_otp_phone"] !== $phone
    ) {

        throw new Exception(
            "This OTP was generated for a different mobile number. Please request a new OTP."
        );
    }


    /*
     * Check OTP.
     */

    if (
        !hash_equals(
            (string) $_SESSION["demo_otp"],
            (string) $otp
        )
    ) {

        throw new Exception(
            "Incorrect OTP. Please enter the correct OTP."
        );
    }


    /*
     * OTP is correct.
     */

    $_SESSION["phone_verified"] =
        true;

    $_SESSION["verified_phone"] =
        $phone;


    /*
     * OTP should not be reused.
     */

    unset(
        $_SESSION["demo_otp"]
    );

    unset(
        $_SESSION["demo_otp_phone"]
    );

    unset(
        $_SESSION["demo_otp_last_sent"]
    );


    echo json_encode([
        "success" => true,
        "message" => "Mobile number verified successfully."
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

?>

