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


$login = trim($_POST["login"] ?? "");
$password = $_POST["password"] ?? "";


if ($login === "" || $password === "") {

    $response["message"] =
        "Please enter email/username and password.";

    echo json_encode($response);
    exit;
}


/* Find user using email OR name */

$stmt = $conn->prepare(
    "SELECT
        user_id,
        name,
        email,
        password,
        role
     FROM users
     WHERE email = ? OR name = ?
     LIMIT 1"
);

if (!$stmt) {

    $response["message"] =
        "Database error: " . $conn->error;

    echo json_encode($response);
    exit;
}


$stmt->bind_param(
    "ss",
    $login,
    $login
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $response["message"] =
        "Email / username not found.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}


$user = $result->fetch_assoc();


/* Verify hashed password */

if (!password_verify($password, $user["password"])) {

    $response["message"] =
        "Incorrect password.";

    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}


/* Login successful */

$response = [
    "success" => true,
    "message" => "Login successful.",
    "user_id" => $user["user_id"],
    "username" => $user["name"],
    "email" => $user["email"],
    "role" => $user["role"]
];


$stmt->close();
$conn->close();

echo json_encode($response);

?>
