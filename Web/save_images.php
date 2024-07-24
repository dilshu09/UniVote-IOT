<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['storedFace']) && isset($_POST['liveFace'])) {
        $storedFace = base64_decode($_POST['storedFace']);
        $liveFace = base64_decode($_POST['liveFace']);

        // Save the images to temporary files
        $storedFacePath = 'path/to/your/temp/directory/storedFace.jpg';
        $liveFacePath = 'path/to/your/temp/directory/liveFace.jpg';

        file_put_contents($storedFacePath, $storedFace);
        file_put_contents($liveFacePath, $liveFace);

        echo json_encode(['success' => true, 'storedFacePath' => $storedFacePath, 'liveFacePath' => $liveFacePath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    }
    exit;
}
?>
