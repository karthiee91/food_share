
<?php

header("Content-Type: application/json");

require_once "db.php";


try {

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
       VALIDATION
       ========================= */

    if ($user_id <= 0) {
        throw new Exception(
            "Invalid user ID."
        );
    }


    if ($receiver_name === "") {
        throw new Exception(
            "Receiver name is required."
        );
    }


    if ($age < 1 || $age > 120) {
        throw new Exception(
            "Please enter a valid age."
        );
    }


    if ($phone === "") {
        throw new Exception(
            "Phone number is required."
        );
    }


    if ($amount <= 0) {
        throw new Exception(
            "Please enter a valid amount."
        );
    }


    if ($reason === "") {
        throw new Exception(
            "Please enter the assistance needed."
        );
    }


    /* =========================
       VERIFY RECEIVER
       ========================= */

    $checkUser = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = ?
        AND role = 'receiver'
        LIMIT 1
    ");


    if (!$checkUser) {
        throw new Exception(
            "Unable to verify receiver."
        );
    }


    $checkUser->bind_param(
        "i",
        $user_id
    );


    $checkUser->execute();


    $userResult =
        $checkUser->get_result();


    if ($userResult->num_rows === 0) {

        throw new Exception(
            "Receiver account not found."
        );
    }


    $checkUser->close();


    /* =========================
       MEDICAL CERTIFICATE
       ========================= */

    if (
        !isset($_FILES["medical_certificate"]) ||
        $_FILES["medical_certificate"]["error"]
        !== UPLOAD_ERR_OK
    ) {

        throw new Exception(
            "Medical certificate is required."
        );
    }


    $file =
        $_FILES["medical_certificate"];


    /* Maximum 5 MB */

    if (
        $file["size"] >
        5 * 1024 * 1024
    ) {

        throw new Exception(
            "Medical certificate must be less than 5 MB."
        );
    }


    /* =========================
       FILE TYPE
       ========================= */

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "application/pdf" => "pdf"
    ];


    $fileType =
        mime_content_type(
            $file["tmp_name"]
        );


    if (
        !isset(
            $allowedTypes[$fileType]
        )
    ) {

        throw new Exception(
            "Only JPG, PNG and PDF files are allowed."
        );
    }


    /* =========================
       UPLOAD DIRECTORY
       ========================= */

    $uploadDir =
        __DIR__ .
        "/uploads/medical_certificates/";


    if (!is_dir($uploadDir)) {

        if (
            !mkdir(
                $uploadDir,
                0755,
                true
            )
        ) {

            throw new Exception(
                "Unable to create upload folder."
            );
        }
    }


    /* =========================
       UNIQUE FILE NAME
       ========================= */

    $extension =
        $allowedTypes[$fileType];


    $fileName =
        "medical_" .
        $user_id .
        "_" .
        time() .
        "_" .
        bin2hex(
            random_bytes(4)
        ) .
        "." .
        $extension;


    $filePath =
        $uploadDir .
        $fileName;


    if (
        !move_uploaded_file(
            $file["tmp_name"],
            $filePath
        )
    ) {

        throw new Exception(
            "Unable to save medical certificate."
        );
    }


    /* =========================
       STATUS
       ========================= */

    $status = "pending";


    /* =========================
       INSERT INTO EXISTING TABLE
       ========================= */

    $sql = "
        INSERT INTO fund_requests
        (
            user_id,
            receiver_name,
            age,
            phone,
            amount,
            reason,
            medical_certificate,
            details,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        throw new Exception(
            "Database prepare failed: " .
            $conn->error
        );
    }


    /*
     * i = user_id
     * s = receiver_name
     * i = age
     * s = phone
     * d = amount
     * s = reason
     * s = medical_certificate
     * s = details
     * s = status
     */

    $stmt->bind_param(
        "isissssss",
        $user_id,
        $receiver_name,
        $age,
        $phone,
        $amount,
        $reason,
        $fileName,
        $details,
        $status
    );


    if (!$stmt->execute()) {

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        throw new Exception(
            "Unable to save fund request: " .
            $stmt->error
        );
    }


    /* =========================
       SUCCESS
       ========================= */

    echo json_encode([
        "success" => true,
        "message" =>
            "Fund request submitted successfully.",
        "request_id" =>
            $stmt->insert_id
    ]);


    $stmt->close();


} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            $e->getMessage()
    ]);
}


$conn->close();

?>

