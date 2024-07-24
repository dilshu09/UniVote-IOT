<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "rothila";
$dbname = "hardware_project";
$port = 3307;

$esp32_ip = "http://192.168.43.211"; // Ensure this is correct and accessible

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age", 86400);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$nic = isset($_SESSION['nic_number']) ? $_SESSION['nic_number'] : '';
$userDetails = null;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nic'])) {
    $nic = $_POST['nic'];

    try {
        $sql = "SELECT NIC_Number, User_Name, Face_ID, Fingerprint_ID, NIC_Picture FROM users WHERE NIC_Number = :nic";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['nic' => $nic]);

        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$userDetails) {
            $error_message = "No user found with NIC: " . htmlspecialchars($nic);
        }
    } catch (PDOException $e) {
        $error_message = "Query failed: " . $e->getMessage();
    }
}

if (isset($_GET['query'])) {
    $query = $_GET['query'];
    try {
        $sql = "SELECT NIC_Number FROM users WHERE NIC_Number LIKE :query LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['query' => $query . '%']);
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($suggestions as $suggestion) {
            echo '<li>' . htmlspecialchars($suggestion['NIC_Number']) . '</li>';
        }
        exit;
    } catch (PDOException $e) {
        $error_message = "Query failed: " . $e->getMessage();
    }
}

$mode = isset($_SESSION['mode']) ? $_SESSION['mode'] : 'manual';
$from_camera = isset($_GET['from']) && $_GET['from'] === 'camera';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
   <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 1200px;
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
            font-size: 3em;
        }
        h1 span {
            font-size: 0.5em;
            color: #666;
        }
        .search-bar {
            text-align: center;
            margin-bottom: 20px;
            margin-top: 20px;
            position: relative;
        }
        .input-container {
            width: 500px;
            position: relative;
            margin: 0 auto;
        }
        .icon {
            position: absolute;
            right: 10px;
            top: calc(50% + 5px);
            transform: translateY(calc(-50% - 5px));
        }
        .input {
            width: 100%;
            padding: 10px;
            transition: .2s linear;
            border: 2.5px solid black;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .input:focus {
            outline: none;
            border: 0.5px solid black;
            box-shadow: -5px -5px 0px black;
        }
        .input-container:hover > .icon {
            animation: anim 1s linear infinite;
        }
        @keyframes anim {
            0%, 100% {
                transform: translateY(calc(-50% - 5px)) scale(1);
            }
            50% {
                transform: translateY(calc(-50% - 5px)) scale(1.1);
            }
        }
        .search-bar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            max-width: 600px;
            width: 80%;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            display: none;
            z-index: 1000;
        }
        .search-bar ul li {
            padding: 10px;
            cursor: pointer;
        }
        .search-bar ul li:hover {
            background: #f1f1f1;
        }
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
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
        .register-button {
            background-color: #4CAF50;
        }
        .register-button:hover {
            background-color: #45A049;
            transform: translateY(-2px);
        }
        .compare-button {
            background-color: #1E90FF;
        }
        .compare-button:hover {
            background-color: #1C86EE;
            transform: translateY(-2px);
        }
        .delete-button {
            background-color: #FF4500;
        }
        .delete-button:hover {
            background-color: #FF6347;
            transform: translateY(-2px);
        }
        .back-home-button {
            background-color: #FFEB3B;
            color: black;
        }
        .back-home-button:hover {
            background-color: #FFD700;
            transform: translateY(-2px);
        }
        .ready-button {
            background-color: #FF4500;
        }
        .ready-button:hover {
            background-color: #FF6347;
            transform: translateY(-2px);
        }
        .button i {
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
            margin: 25px 0;
            border-radius: 10px;
            overflow: hidden;
            background-color: #ffffff;
            table-layout: fixed;
            border: 2px solid #333;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 0;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            box-sizing: border-box;
        }
        th {
            height: 50px;
            background-color: #333;
            color: #ffffff;
            padding: 10px;
        }
        td {
            background-color: #fff;
            padding: 10px;
            font-weight: bold;
        }
        td.image-cell, td.qrcode-cell {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        .image-cell img, .qrcode {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        tr:hover td {
            background-color: #f1f1f1;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }
        .modal.active {
            display: flex;
        }
        .copy-button {
            background-color: #FF0000;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .copy-button:hover {
            background-color: #CC0000;
        }
        @media (max-width: 600px) {
            th, td {
                width: 100px;
                height: 100px;
            }
            .image-cell img, .qrcode {
                width: 75px;
                height: 75px;
            }
            .input-container {
                width: 100%;
            }
        }
        #notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }
        #notification.error {
            background-color: #f44336;
        }
        .countdown {
            font-size: 1.2em;
            margin-top: 10px;
            color: #333;
            text-align: center;
        }
        .status-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }
        .status-message {
            font-size: 1.5em;
            font-weight: bold;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
        }
        .matched {
            background-color: #4CAF50;
        }
        .not-matched {
            background-color: #f44336;
        }
        .default-status {
            background-color: #FFA500; /* Orange color for waiting status */
        }
        .hidden-text {
            color: white;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>Fingerprint Management <span>Search by NIC</span></h1>
        </div>
    </div>
    <div class="button-group">
        <a href="index.php" class="button back-home-button">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <button class="button ready-button" onclick="sendReadyCommand()"><i class="fas fa-check-circle"></i> Ready</button>
        <button class="button delete-button" onclick="deleteAllFingerprints()">Delete Templates</button>
    </div>
    <div class="search-bar">
        <form method="POST" action="" id="nicForm">
            <div class="input-container">
                <input type="text" name="nic" id="nicInput" class="input" placeholder="Enter NIC Number" value="<?php echo htmlspecialchars($nic); ?>" autocomplete="off">
                <i class="fas fa-search icon"></i>
            </div>
            <ul id="suggestions"></ul>
        </form>
    </div>
    <?php if ($userDetails): ?>
        <table>
            <thead>
                <tr>
                    <th>NIC Number</th>
                    <th>User Name</th>
                    <th>Fingerprint ID</th>
                    <th>Matched Fingerprint ID</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($userDetails["NIC_Number"]); ?><br><button class='copy-button' onclick='copyToClipboard("<?php echo htmlspecialchars($userDetails["NIC_Number"]); ?>")'>Copy</button></td>
                    <td><?php echo nl2br(htmlspecialchars($userDetails["User_Name"])); ?></td>
                    <td id="fingerprintID"><?php echo htmlspecialchars($userDetails["Fingerprint_ID"]); ?></td>
                    <td id="matchedFingerprintID">Not matched</td>
                    <td id="status">Waiting for comparison...</td>
                </tr>
            </tbody>
        </table>
        <div class="button-group">
            <button class="button register-button" onclick="registerFingerprint()">Register</button>
            <button class="button compare-button" onclick="compareFingerprint()">Compare Fingerprint</button>
        </div>
    <?php elseif ($nic): ?>
        <p>No results found for NIC: <?php echo htmlspecialchars($nic); ?></p>
    <?php endif; ?>
</div>

<div id="notification"></div>
<script>
    let currentOperation = null;

    function loadVoices() {
        return new Promise((resolve) => {
            let voices = window.speechSynthesis.getVoices();
            if (voices.length !== 0) {
                resolve(voices);
            } else {
                window.speechSynthesis.onvoiceschanged = () => {
                    voices = window.speechSynthesis.getVoices();
                    resolve(voices);
                };
            }
        });
    }

    async function speak(text) {
        const voices = await loadVoices();
        const synth = window.speechSynthesis;
        const utterThis = new SpeechSynthesisUtterance(text);
        const femaleVoice = voices.find(voice => voice.name.includes('Female') || voice.gender === 'female');

        if (femaleVoice) {
            utterThis.voice = femaleVoice;
        }

        utterThis.pitch = 1;
        utterThis.rate = 1;
        synth.speak(utterThis);
    }

    function showNotification(message, type) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.style.display = 'block';
        
        // Speak the notification
        setTimeout(() => {
            speak(message);
        }, 100);  // Small delay to ensure the voices are loaded

        setTimeout(() => {
            notification.style.display = 'none';
        }, 2000);
    }

    document.addEventListener("DOMContentLoaded", function() {
        $('#nicInput').on('input', function() {
            const query = $(this).val();
            if (query.length >= 1) {
                $.ajax({
                    url: '',
                    method: 'GET',
                    data: { query: query },
                    success: function(data) {
                        $('#suggestions').html(data).show();
                        if ($('#suggestions li').length > 0 && $('#suggestions li').first().text() === query) {
                            $('#nicForm').submit();
                        }
                    }
                });
            } else {
                $('#suggestions').hide();
            }
        });

        $(document).on('click', '#suggestions li', function() {
            const selectedNic = $(this).text();
            $('#nicInput').val(selectedNic);
            $('#suggestions').hide();
            $('#nicForm').submit();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-bar').length) {
                $('#suggestions').hide();
            }
        });

        document.getElementById('nicInput').focus();

        fetchFingerprintIDs();

        const statusMessage = document.getElementById('status');
        statusMessage.textContent = 'Waiting for comparison...';
        statusMessage.classList.add('default-status');

        // Trigger compareFingerprint if Fingerprint ID is present and coming from camera
        const fingerprintID = document.getElementById('fingerprintID').textContent;
        if (fingerprintID && <?php echo json_encode($from_camera); ?>) {
            compareFingerprint();
        }
    });

    function fetchFingerprintIDs() {
        $.ajax({
            url: 'fetch_fingerprint_ids.php',
            method: 'GET',
            success: function(data) {
                const fingerprints = JSON.parse(data);
                const fingerprintList = $('#fingerprintIDs');
                fingerprintList.empty();
                fingerprints.forEach(fingerprint => {
                    fingerprintList.append(`<li>NIC: ${fingerprint.NIC_Number} - Fingerprint ID: ${fingerprint.Fingerprint_ID}</li>`);
                });
            },
            error: function(error) {
                console.error('Error fetching fingerprint IDs', error);
            }
        });
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied to clipboard: ' + text);
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }

    function sendReadyCommand() {
        console.log('Sending ready command...');
        fetch('<?php echo $esp32_ip; ?>/ready', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: "System is ready" })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response from ESP32:', data);
            showNotification('Ready command sent to ESP32', 'success');
        })
        .catch(error => {
            console.error('Failed to send ready command to ESP32:', error);
            showNotification('Failed to send ready command to ESP32', 'error');
        });
    }

    function registerFingerprint() {
        console.log('Starting fingerprint registration...');
        showNotification('Starting fingerprint registration...', 'notification');
        if (currentOperation) {
            console.log('Terminating current operation:', currentOperation);
            sendCancelCommand();
        }

        const nic = "<?php echo $userDetails['NIC_Number'] ?? ''; ?>";
        const fingerprintID = getRandomInt(1, 127);

        if (nic) {
            currentOperation = new AbortController();
            fetch('<?php echo $esp32_ip; ?>/register_fingerprint', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ nic: nic, fingerprintID: fingerprintID }),
                signal: currentOperation.signal
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response from ESP32:', data);
                if (data.success) {
                    updateFingerprintID(nic, fingerprintID);
                    showNotification('Fingerprint registered successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('Failed to register fingerprint', 'error');
                }
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    console.log('Register fingerprint operation aborted');
                } else {
                    showNotification('Error registering fingerprint', 'error');
                    console.error('Error:', error);
                }
            });
        } else {
            alert('No NIC number found.');
        }
    }

    function updateFingerprintID(nic, fingerprintID) {
        $.ajax({
            url: 'update_fingerprint_id.php',
            method: 'POST',
            data: { nic: nic, fingerprintID: fingerprintID },
            success: function(response) {
                console.log('Database updated successfully');
            },
            error: function(error) {
                console.error('Error updating database', error);
            }
        });
    }

    function compareFingerprint() {
        console.log('Starting fingerprint comparison...');
        showNotification('Starting fingerprint comparison...', 'notification');
        if (currentOperation) {
            console.log('Terminating current operation:', currentOperation);
            sendCancelCommand();
        }

        const nic = "<?php echo $userDetails['NIC_Number'] ?? ''; ?>";
        const storedFingerprintID = "<?php echo $userDetails['Fingerprint_ID'] ?? ''; ?>";

        if (nic) {
            currentOperation = new AbortController();
            fetch('<?php echo $esp32_ip; ?>/compare_fingerprint', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ nic: nic }),
                signal: currentOperation.signal
            })
            .then(response => response.json())
            .then(data => {
                const matchedFingerprintID = document.getElementById('matchedFingerprintID');
                const status = document.getElementById('status');

                console.log('Response from ESP32:', data);

                if (data.matchedID !== null) {
                    matchedFingerprintID.textContent = data.matchedID;

                    if (data.matchedID == storedFingerprintID) {
                        status.textContent = 'Matched';
                        status.classList.remove('not-matched', 'default-status');
                        status.classList.add('matched');
                    } else {
                        status.textContent = 'Not Matched';
                        status.classList.remove('matched', 'default-status');
                        status.classList.add('not-matched');
                    }
                } else {
                    matchedFingerprintID.textContent = 'Not matched';
                    status.textContent = 'Not Matched';
                    status.classList.remove('matched', 'default-status');
                    status.classList.add('not-matched');
                }

                showNotification('Fingerprint comparison completed', 'success');

                setTimeout(() => {
                    window.location.href = 'index2.php';
                }, 1000); // Short delay before redirecting
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    console.log('Compare fingerprint operation aborted');
                } else {
                    showNotification('Error comparing fingerprint', 'error');
                    console.error('Error:', error);
                }
            });
        } else {
            alert('No NIC number found.');
        }
    }

    function deleteAllFingerprints() {
        showNotification('Deleting fingerprints...', 'notification');
        
        fetch('<?php echo $esp32_ip; ?>/delete_fingerprints', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response from ESP32:', data);
            if (data.success) {
                clearFingerprintIDs();
                showNotification('All fingerprints deleted successfully', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showNotification('Failed to delete fingerprints', 'error');
            }
        })
        .catch(error => {
            showNotification('Error deleting fingerprints', 'error');
            console.error('Error:', error);
        });
    }

    function clearFingerprintIDs() {
        $.ajax({
            url: 'clear_fingerprint_ids.php',
            method: 'POST',
            success: function(response) {
                console.log('Database cleared successfully');
            },
            error: function(error) {
                console.error('Error clearing database', error);
            }
        });
    }

    function sendCancelCommand() {
        fetch('<?php echo $esp32_ip; ?>/cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response from ESP32:', data);
            if (data.success) {
                console.log('Operation cancelled successfully');
            } else {
                console.log('Failed to cancel operation');
            }
        })
        .catch(error => {
            console.error('Error cancelling operation:', error);
        });
    }

    function getRandomInt(min, max) {
        min = Math.ceil(min);
        max = Math.floor(max);
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }
</script>

</body>
</html>
