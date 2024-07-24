<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['storedFace']) && isset($_POST['liveFace'])) {
        $storedFace = base64_decode($_POST['storedFace']);
        $liveFace = base64_decode($_POST['liveFace']);

        // Save the images to temporary files
        $storedFacePath = tempnam(sys_get_temp_dir(), 'storedFace') . '.jpg';
        $liveFacePath = tempnam(sys_get_temp_dir(), 'liveFace') . '.jpg';

        file_put_contents($storedFacePath, $storedFace);
        file_put_contents($liveFacePath, $liveFace);

        // Call the Python script
        $output = shell_exec("python compare_faces.py $storedFacePath $liveFacePath 2>&1");

        // Debugging output
        error_log("Python script output: $output");

        // Delete temporary files
        unlink($storedFacePath);
        unlink($liveFacePath);

        if ($output === NULL) {
            echo json_encode(['success' => false, 'message' => 'Face comparison failed. No output from Python script.']);
        } else {
            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                echo json_encode(['success' => false, 'message' => 'Failed to decode JSON response from Python script.']);
            } else {
                if ($result['success']) {
                    if ($result['match_percentage'] < 70) {
                        $result['message'] = 'Faces do not match. Match percentage is below 70%.';
                    }
                }
                echo json_encode($result);
            }
        }
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }
}
?>
