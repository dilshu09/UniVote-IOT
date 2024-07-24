<?php
$servername = "localhost";
$username = "root";
$password = "rothila";
$dbname = "hardware_project";
$port = 3307;

// Create connection using PDO for better security
try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if this is an AJAX request for validating NIC number
if (isset($_GET['validate_nic'])) {
    $nicNumber = $_GET['validate_nic'];
    $checkSql = "SELECT COUNT(*) FROM users WHERE NIC_Number = :nic_number";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute(['nic_number' => $nicNumber]);
    $count = $stmt->fetchColumn();
    echo json_encode(['exists' => $count > 0]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nicNumber = $_POST['nic_number'];
    $username = $_POST['user_name'];

    $faceId = isset($_FILES['face_id']) && $_FILES['face_id']['error'] === UPLOAD_ERR_OK ? file_get_contents($_FILES['face_id']['tmp_name']) : null;
    $nicPicture = isset($_FILES['nic_picture']) && $_FILES['nic_picture']['error'] === UPLOAD_ERR_OK ? file_get_contents($_FILES['nic_picture']['tmp_name']) : null;

    // Check for duplicate NIC Number
    $checkSql = "SELECT COUNT(*) FROM users WHERE NIC_Number = :nic_number";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute(['nic_number' => $nicNumber]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $duplicateError = "The NIC Number already exists.";
    } else {
        try {
            $sql = "INSERT INTO users (NIC_Number, User_Name, Face_ID, NIC_Picture) VALUES (:nic_number, :user_name, :face_id, :nic_picture)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'nic_number' => $nicNumber,
                'user_name' => $username,
                'face_id' => $faceId,
                'nic_picture' => $nicPicture
            ]);

            header("Location: index.php");
            exit;
        } catch(PDOException $e) {
            $duplicateError = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        h1 {
            text-align: center;
            margin: 20px 0;
            color: #333;
            font-size: 3em;
        }
        form {
            display: flex;
            flex-direction: column;
            autocomplete: off; /* Disable form autocomplete */
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="file"] {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            autocomplete: off; /* Disable input field autocomplete */
        }
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        button {
            padding: 10px 20px;
            border: none;
            background-color: #4CAF50;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
        }
        .cancel-button {
            background-color: #b22222;
        }
        .error {
            color: red;
            font-weight: bold;
            margin-top: -10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Add New User</h1>
    <?php if (isset($duplicateError)): ?>
        <div class="error"><?php echo $duplicateError; ?></div>
    <?php endif; ?>
    <form id="addUserForm" action="" method="POST" enctype="multipart/form-data" autocomplete="off">
        <label for="nic_number">NIC Number</label>
        <input type="text" id="nic_number" name="nic_number" required autocomplete="off">
        <div id="nicError" class="error" style="display:none;">This NIC number already exists.</div>

        <label for="user_name">User Name</label>
        <input type="text" id="user_name" name="user_name" required autocomplete="off">

        <label for="face_id">Face ID</label>
        <input type="file" id="face_id" name="face_id" accept="image/*">

        <label for="nic_picture">NIC Picture</label>
        <input type="file" id="nic_picture" name="nic_picture" accept="image/*" required>

        <div class="button-group">
            <button type="submit" name="add_user">Add User</button>
            <button type="button" class="cancel-button" onclick="window.location.href='index.php'">Cancel</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Clear form inputs on page load
        const addUserForm = document.getElementById('addUserForm');
        addUserForm.reset();

        const nicNumberInput = document.getElementById('nic_number');
        const nicError = document.getElementById('nicError');

        nicNumberInput.addEventListener('input', function() {
            const nicNumber = nicNumberInput.value;
            fetch(`new_user.php?validate_nic=${nicNumber}`)
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        nicError.style.display = 'block';
                    } else {
                        nicError.style.display = 'none';
                    }
                });
        });

        addUserForm.addEventListener('submit', function(event) {
            const nicPictureInput = document.getElementById('nic_picture');
            if (nicPictureInput.files.length === 0) {
                alert('Please upload an NIC Picture.');
                event.preventDefault();
            }
        });
    });
</script>

</body>
</html>
