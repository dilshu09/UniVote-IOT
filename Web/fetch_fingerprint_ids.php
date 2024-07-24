<?php
$servername = "localhost";
$username = "root";
$password = "rothila"; // Actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT NIC_Number, Fingerprint_ID FROM users";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $fingerprints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($fingerprints);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
