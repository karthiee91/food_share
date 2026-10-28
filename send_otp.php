
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


    /*
     * Validate Indian mobile number.
     */

    if (!preg_match(
        '/^[6-9][0-9]{9}$/',
        $phone
    )) {

        throw new Exception(
            "Enter a valid 10-digit Indian mobile number."
        );
    }


    /*
     * Prevent rapid OTP generation.
     */

    if (
        isset($_SESSION["demo_otp_last_sent"]) &&
        (time() - $_SESSION["demo_otp_last_sent"]) < 30
    ) {

        $remaining =
            30 -
            (time() - $_SESSION["demo_otp_last_sent"]);

        throw new Exception(
            "Please wait " .
            $remaining .
            " seconds before requesting another OTP."
        );
    }


    /*
     * Generate a random 6-digit OTP.
     */

    $otp = (string) random_int(
        100000,
        999999
    );


    /*
     * Store OTP in session.
     */

    $_SESSION["demo_otp_phone"] =
        $phone;

    $_SESSION["demo_otp"] =
        $otp;

    $_SESSION["demo_otp_last_sent"] =
        time();

    $_SESSION["phone_verified"] =
        false;

    unset(
        $_SESSION["verified_phone"]
    );


    /*
     * For a local/college project there is
     * no SMS provider.
     *
     * Therefore return the OTP to the browser
     * so it can be displayed as a DEMO OTP.
     */

    echo json_encode([
        "success" => true,
        "message" => "Demo OTP generated successfully.",
        "demo_otp" => $otp
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

?>
