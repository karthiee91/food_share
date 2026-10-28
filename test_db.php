
<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=UTF-8');

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    echo "SUCCESS: Connected to the foodshare database.";
} else {
    echo "FAILED: The database connection variable is unavailable.";
}
?>
