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

function logNotification($message, $type) {
    $log = ['message' => $message, 'type' => $type, 'timestamp' => time()];
    file_put_contents('notifications.log', json_encode($log) . PHP_EOL, FILE_APPEND);
}

if (isset($data['disable3digit'])) {
    $candidate_id = $data['disable3digit'];

    $stmt = $conn->prepare("SELECT * FROM candidates WHERE Candidate_id = :candidate_id");
    $stmt->bindParam(':candidate_id', $candidate_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("UPDATE candidates SET Vote_Count = Vote_Count + 1 WHERE Candidate_id = :candidate_id");
        $stmt->bindParam(':candidate_id', $candidate_id, PDO::PARAM_INT);
        $stmt->execute();
        logNotification('Vote count updated successfully', 'success');
        echo json_encode(['status' => 1, 'message' => 'Vote count updated successfully']);
    } else {
        logNotification('Invalid Candidate ID', 'error');
        echo json_encode(['status' => 0, 'message' => 'Invalid Candidate ID']); // Candidate ID not found
    }
} else {
    logNotification('Candidate ID not provided', 'error');
    echo json_encode(['status' => 0, 'message' => 'Candidate ID not provided']);
}
?>
