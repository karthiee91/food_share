
<?php
$host = "localhost";
$username = "root";
$password = ""; // Your MySQL password, if configured
$database = "foodshare";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("FoodShare database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit(json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]));
}

$conn->set_charset("utf8mb4");
?>
