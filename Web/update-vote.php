<?php
$servername = "localhost";
$username = "root";
$password = "rothila"; // Your actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $candidateID = $data['pin'];

    $sql = "UPDATE candidates SET vote_count = vote_count + 1 WHERE Candidate_id = :candidateID";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':candidateID', $candidateID, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo json_encode(['status' => 1, 'message' => 'Vote count updated successfully']);
    } else {
        echo json_encode(['status' => 0, 'message' => 'Failed to update vote count']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 0, 'message' => 'Connection failed: ' . $e->getMessage()]);
}
?>
