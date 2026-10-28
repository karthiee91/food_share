
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        throw new Exception(
            "Invalid request method."
        );
    }


    /* =========================
       GET FORM DATA
       ========================= */

    $user_id =
        intval($_POST["user_id"] ?? 0);

    $receiver_name =
        trim($_POST["receiver_name"] ?? "");

    $age =
        intval($_POST["age"] ?? 0);

    $phone =
        trim($_POST["phone"] ?? "");

    $amount =
        floatval($_POST["amount"] ?? 0);

    $reason =
        trim($_POST["reason"] ?? "");

    $details =
        trim($_POST["details"] ?? "");


    /* =========================
       BASIC VALIDATION
       ========================= */

    if ($user_id <= 0) {

        throw new Exception(
            "Please sign in again."
        );
    }


    if ($receiver_name === "") {

        throw new Exception(
            "Receiver name is required."
        );
    }


    if ($age < 1 || $age > 120) {

        throw new Exception(
            "Enter a valid age."
        );
    }


    if (
        !preg_match(
            '/^[6-9][0-9]{9}$/',
            $phone
        )
    ) {

        throw new Exception(
            "Enter a valid 10-digit Indian mobile number."
        );
    }


    if ($amount <= 0) {

        throw new Exception(
            "Required fund must be greater than zero."
        );
    }


    if ($reason === "") {

        throw new Exception(
            "Please enter the assistance needed."
        );
    }


    /* =========================
       MEDICAL CERTIFICATE
       ========================= */

    if (
        !isset($_FILES["medical_certificate"]) ||
        $_FILES["medical_certificate"]["error"] !== UPLOAD_ERR_OK
    ) {

        throw new Exception(
            "Medical certificate is required."
        );
    }


    $certificateFile =
        $_FILES["medical_certificate"];


    if ($certificateFile["size"] > 5 * 1024 * 1024) {

        throw new Exception(
            "Medical certificate must be 5 MB or smaller."
        );
    }


    $certificateAllowedTypes = [

        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "application/pdf" => "pdf"

    ];


    $finfo =
        new finfo(FILEINFO_MIME_TYPE);


    $certificateMime =
        $finfo->file(
            $certificateFile["tmp_name"]
        );


    if (
        !isset(
            $certificateAllowedTypes[
                $certificateMime
            ]
        )
    ) {

        throw new Exception(
            "Only JPG, JPEG, PNG and PDF medical certificates are allowed."
        );
    }


    /* =========================
       PAYMENT QR CODE
       ========================= */

    if (
        !isset($_FILES["payment_qr"]) ||
        $_FILES["payment_qr"]["error"] !== UPLOAD_ERR_OK
    ) {

        throw new Exception(
            "Payment QR code is required."
        );
    }


    $qrFile =
        $_FILES["payment_qr"];


    if ($qrFile["size"] > 5 * 1024 * 1024) {

        throw new Exception(
            "Payment QR code must be 5 MB or smaller."
        );
    }


    /*
     * Only image QR files are allowed.
     */
    $qrAllowedTypes = [

        "image/jpeg" => "jpg",
        "image/png"  => "png"

    ];


    $qrMime =
        $finfo->file(
            $qrFile["tmp_name"]
        );


    if (
        !isset(
            $qrAllowedTypes[$qrMime]
        )
    ) {

        throw new Exception(
            "Payment QR must be a JPG or PNG image."
        );
    }


    /* =========================
       VERIFY USER
       ========================= */

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
            "Unable to verify user."
        );
    }


    $userStmt->bind_param(
        "i",
        $user_id
    );


    $userStmt->execute();


    $userResult =
        $userStmt->get_result();


    if (
        !$userResult ||
        $userResult->num_rows === 0
    ) {

        $userStmt->close();

        throw new Exception(
            "User account was not found."
        );
    }


    $user =
        $userResult->fetch_assoc();


    $userStmt->close();


    if (
        strtolower(
            trim($user["role"])
        ) !== "receiver"
    ) {

        throw new Exception(
            "Only receiver accounts can submit fund requests."
        );
    }


    /* =========================
       CREATE UPLOAD FOLDERS
       ========================= */

    $certificateDir =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "uploads" .
        DIRECTORY_SEPARATOR .
        "fund_certificates";


    $qrDir =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "uploads" .
        DIRECTORY_SEPARATOR .
        "payment_qr";


    if (!is_dir($certificateDir)) {

        if (
            !mkdir(
                $certificateDir,
                0755,
                true
            )
        ) {

            throw new Exception(
                "Unable to create certificate upload folder."
            );
        }
    }


    if (!is_dir($qrDir)) {

        if (
            !mkdir(
                $qrDir,
                0755,
                true
            )
        ) {

            throw new Exception(
                "Unable to create payment QR upload folder."
            );
        }
    }


    /* =========================
       MEDICAL CERTIFICATE FILE
       ========================= */

    $certificateExtension =
        $certificateAllowedTypes[
            $certificateMime
        ];


    $certificateFilename =
        "certificate_" .
        $user_id .
        "_" .
        time() .
        "_" .
        bin2hex(
            random_bytes(4)
        ) .
        "." .
        $certificateExtension;


    $certificateTarget =
        $certificateDir .
        DIRECTORY_SEPARATOR .
        $certificateFilename;


    if (
        !move_uploaded_file(
            $certificateFile["tmp_name"],
            $certificateTarget
        )
    ) {

        throw new Exception(
            "Unable to save medical certificate."
        );
    }


    $certificatePath =
        "uploads/fund_certificates/" .
        $certificateFilename;


    /* =========================
       PAYMENT QR FILE
       ========================= */

    $qrExtension =
        $qrAllowedTypes[$qrMime];


    $qrFilename =
        "qr_" .
        $user_id .
        "_" .
        time() .
        "_" .
        bin2hex(
            random_bytes(4)
        ) .
        "." .
        $qrExtension;


    $qrTarget =
        $qrDir .
        DIRECTORY_SEPARATOR .
        $qrFilename;


    if (
        !move_uploaded_file(
            $qrFile["tmp_name"],
            $qrTarget
        )
    ) {

        /*
         * QR failed, so remove the
         * medical certificate already saved.
         */
        @unlink($certificateTarget);

        throw new Exception(
            "Unable to save payment QR code."
        );
    }


    $qrPath =
        "uploads/payment_qr/" .
        $qrFilename;


    /* =========================
       DEFAULT VALUES
       ========================= */

    $status =
        "pending";

    $donatedAmount =
        0.00;


    /* =========================
       INSERT FUND REQUEST
       ========================= */

    $sql = "
        INSERT INTO fund_requests
        (
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
            ?
        )
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        @unlink($certificateTarget);
        @unlink($qrTarget);

        throw new Exception(
            "Unable to prepare fund request query: " .
            $conn->error
        );
    }


    /*
     * Types:
     *
     * i = user_id
     * s = receiver_name
     * i = age
     * s = phone
     * d = amount
     * d = donated_amount
     * s = reason
     * s = details
     * s = certificate path
     * s = QR path
     * s = status
     */

    $stmt->bind_param(
        "isidsdsssss",
        $user_id,
        $receiver_name,
        $age,
        $phone,
        $amount,
        $donatedAmount,
        $reason,
        $details,
        $certificatePath,
        $qrPath,
        $status
    );


    if (!$stmt->execute()) {

        @unlink($certificateTarget);
        @unlink($qrTarget);

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            "Unable to save fund request: " .
            $error
        );
    }


    $requestId =
        $stmt->insert_id;


    $stmt->close();


    /* =========================
       SUCCESS
       ========================= */

    echo json_encode(
        [
            "success" => true,
            "message" =>
                "Fund request submitted successfully. It is waiting for admin approval.",
            "request_id" => $requestId,
            "payment_qr" => $qrPath
        ],
        JSON_UNESCAPED_SLASHES
    );


} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode(
        [
            "success" => false,
            "message" => $e->getMessage()
        ],
        JSON_UNESCAPED_SLASHES
    );


} finally {

    if (
        isset($conn) &&
        $conn
    ) {

        $conn->close();
    }
}

?>

