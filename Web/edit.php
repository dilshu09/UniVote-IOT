<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "rothila"; // Actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // Cache for 1 day

// Handle OPTIONS requests
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

// Handle AJAX request for image removal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_image'])) {
    $nicNumber = htmlspecialchars($_POST['NIC_Number']);
    $field = htmlspecialchars($_POST['field']);
    
    $sql = "UPDATE users SET $field = NULL WHERE NIC_Number = :nic";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['nic' => $nicNumber]);
    
    echo json_encode(["success" => true]);
    exit;
}

if (isset($_GET['nic'])) {
    $nicNumber = htmlspecialchars($_GET['nic']);
    
    $sql = "SELECT NIC_Number, User_Name, Face_ID, Fingerprint_ID, NIC_Picture FROM users WHERE NIC_Number = :nic";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['nic' => $nicNumber]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("User not found");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['remove_image'])) {
    $nicNumber = htmlspecialchars($_POST['NIC_Number']);
    $userName = htmlspecialchars($_POST['User_Name']);
    $fingerprintID = htmlspecialchars($_POST['Fingerprint_ID']);
    $faceID = !empty($_FILES['Face_ID']['tmp_name']) ? file_get_contents($_FILES['Face_ID']['tmp_name']) : null;
    $nicPicture = !empty($_FILES['NIC_Picture']['tmp_name']) ? file_get_contents($_FILES['NIC_Picture']['tmp_name']) : null;

    // Verify PIN before saving changes
    $pin = htmlspecialchars($_POST['pin']);
    if ($pin !== '1234') {
        die("Incorrect PIN. Changes not allowed.");
    }

    // Handle PIN verification for NIC Number change
    if (isset($_POST['new_NIC_Number']) && !empty($_POST['new_NIC_Number'])) {
        $newNICNumber = htmlspecialchars($_POST['new_NIC_Number']);
        
        $sql = "UPDATE users SET NIC_Number = :new_nic WHERE NIC_Number = :nic";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['new_nic' => $newNICNumber, 'nic' => $nicNumber]);
        
        $nicNumber = $newNICNumber; // Update NIC Number in case it changed
    }
    
    $sql = "UPDATE users SET User_Name = :user_name, Fingerprint_ID = :fingerprint_id" . 
           ($faceID ? ", Face_ID = :face_id" : "") . 
           ($nicPicture ? ", NIC_Picture = :nic_picture" : "") . 
           " WHERE NIC_Number = :nic";
    
    $stmt = $conn->prepare($sql);
    $params = [
        'user_name' => $userName,
        'fingerprint_id' => $fingerprintID,
        'nic' => $nicNumber
    ];
    if ($faceID) {
        $params['face_id'] = $faceID;
    }
    if ($nicPicture) {
        $params['nic_picture'] = $nicPicture;
    }
    
    $stmt->execute($params);
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #001f3f;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            box-sizing: border-box;
        }
        .header {
            position: sticky;
            top: 0;
            background-color: #ffffff;
            padding: 20px;
            margin: -20px -20px 20px -20px;
            border-bottom: 2px solid #ddd;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 800px;
        }
        h1 {
            margin: 0;
            color: #333;
            text-align: center;
            font-size: 2em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group input[type="password"] {
            width: 90%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .button {
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .save-button {
            background-color: #4CAF50;
        }
        .save-button:hover {
            background-color: #388E3C;
            transform: translateY(-2px);
        }
        .cancel-button {
            background-color: #f44336;
        }
        .cancel-button:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
        }
        .remove-button {
            background-color: #FFEB3B; /* Yellow background */
            color: black; /* Black text */
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
        }
        .remove-button:hover {
            background-color: #FFD700; /* Darker yellow on hover */
            transform: translateY(-2px);
        }
        .image-preview {
            max-width: 200px;
            display: block;
            margin-bottom: 10px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>Edit User: <?php echo htmlspecialchars($user['NIC_Number']); ?></h1>
        </div>
    </div>
    <form id="editForm" action="edit.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="NIC_Number">NIC Number</label>
            <input type="text" id="NIC_Number" name="NIC_Number" value="<?php echo htmlspecialchars($user['NIC_Number']); ?>" readonly>
        </div>
        <div class="form-group">
            <label for="User_Name">User Name</label>
            <input type="text" id="User_Name" name="User_Name" value="<?php echo htmlspecialchars($user['User_Name']); ?>">
        </div>
        <div class="form-group">
            <label for="new_NIC_Number">New NIC Number (Optional)</label>
            <input type="text" id="new_NIC_Number" name="new_NIC_Number" placeholder="Enter new NIC Number">
        </div>
        <div class="form-group">
            <label for="pin">PIN (Required for saving changes)</label>
            <input type="password" id="pin" name="pin" placeholder="Enter PIN" required>
        </div>
        <div class="form-group">
            <label for="Fingerprint_ID">Fingerprint ID</label>
            <input type="text" id="Fingerprint_ID" name="Fingerprint_ID" value="<?php echo htmlspecialchars($user['Fingerprint_ID']); ?>">
        </div>
        <div class="form-group">
            <label for="Face_ID">Face ID</label>
            <div id="face-id-container">
                <?php if ($user['Face_ID']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($user['Face_ID']); ?>" alt="Face ID" class="image-preview">
                    <button type="button" class="button remove-button" onclick="removeImage('Face_ID')">Remove Image</button>
                <?php endif; ?>
            </div>
            <input type="file" id="Face_ID" name="Face_ID">
        </div>
        <div class="form-group">
            <label for="NIC_Picture">NIC Picture</label>
            <div id="nic-picture-container">
                <?php if ($user['NIC_Picture']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($user['NIC_Picture']); ?>" alt="NIC Picture" class="image-preview">
                    <button type="button" class="button remove-button" onclick="removeImage('NIC_Picture')">Remove Image</button>
                <?php endif; ?>
            </div>
            <input type="file" id="NIC_Picture" name="NIC_Picture">
        </div>
        <div class="button-group">
            <button type="submit" class="button save-button">Save Changes</button>
            <a href="index.php" class="button cancel-button">Cancel</a>
        </div>
    </form>
</div>
<script>
    function removeImage(field) {
        if (confirm("Are you sure you want to remove this image?")) {
            const nicNumber = document.getElementById('NIC_Number').value;
            fetch('edit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    NIC_Number: nicNumber,
                    field: field,
                    remove_image: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById(field.toLowerCase() + '-container');
                    if (container) {
                        container.innerHTML = `<input type="file" id="${field}" name="${field}">`;
                    }
                } else {
                    alert("Failed to remove image.");
                }
            })
            .catch(error => console.error('Error:', error));
        }
    }

    document.getElementById('editForm').onsubmit = function() {
        return confirm("Are you sure you want to save the changes?");
    };
</script>
</body>
</html>
