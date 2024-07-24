<?php
$servername = "localhost";
$username = "root";
$password = "rothila"; // Actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No content response for preflight request
    exit;
}

// Create connection using PDO for better security
try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nic']) && isset($_POST['status'])) {
    $nic = $_POST['nic'];
    $status = $_POST['status'];

    $sql = "UPDATE users SET status = :status WHERE NIC_Number = :nic";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['status' => $status, 'nic' => $nic]);

    echo "Status updated successfully";
}
?>
