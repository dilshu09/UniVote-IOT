<?php
$servername = "localhost";
$username = "root";
$password = "rothila"; // Your actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['status' => 0, 'message' => "Connection failed: " . $e->getMessage()]));
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (isset($data['pin'])) {
    $pin = $data['pin'];

    $stmt = $conn->prepare("SELECT * FROM candidates WHERE Candidate_id = :pin");
    $stmt->bindParam(':pin', $pin, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("UPDATE candidates SET Vote_Count = Vote_Count + 1 WHERE Candidate_id = :pin");
        $stmt->bindParam(':pin', $pin, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['status' => 1]); // Vote accepted
    } else {
        echo json_encode(['status' => 0, 'message' => 'Invalid Candidate ID']); // Candidate ID not found
    }
} else {
    echo json_encode(['status' => 0, 'message' => 'PIN not provided']);
}
?>
