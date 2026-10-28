<?php

header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set("display_errors", 0);

include "db.php";

$response = [
    "success" => false,
    "message" => ""
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["message"] = "Invalid request.";
    echo json_encode($response);
    exit;
}

$name = trim($_POST["username"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$role = trim($_POST["role"] ?? "");

if ($name === "" || $email === "" || $password === "" || $role === "") {
    $response["message"] = "Please fill all fields.";
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response["message"] = "Please enter a valid email address.";
    echo json_encode($response);
    exit;
}

if (strlen($password) < 6) {
    $response["message"] = "Password must be at least 6 characters.";
    echo json_encode($response);
    exit;
}

if (!in_array($role, ["donor", "receiver", "expert"], true)) {
    $response["message"] = "Invalid role selected.";
    echo json_encode($response);
    exit;
}

/* Check existing email */

$check = $conn->prepare(
    "SELECT user_id FROM users WHERE email = ?"
);

if (!$check) {
    $response["message"] = "Database error: " . $conn->error;
    echo json_encode($response);
    exit;
}

$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {

    $response["message"] = "Email already registered.";

    $check->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

$check->close();


/* Hash password */

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/* Insert new user */

$stmt = $conn->prepare(
    "INSERT INTO users
    (name, email, password, role)
    VALUES (?, ?, ?, ?)"
);

if (!$stmt) {
    $response["message"] = "Database error: " . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param(
    "ssss",
    $name,
    $email,
    $hashedPassword,
    $role
);

if ($stmt->execute()) {

    $response["success"] = true;
    $response["message"] = "Account created successfully.";

} else {

    $response["message"] =
        "Unable to create account: " . $stmt->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);

?>
