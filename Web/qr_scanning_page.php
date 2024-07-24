<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "rothila"; // Actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

$esp32_ip = "http://192.168.43.211";  // Replace with the actual IP address of your ESP32

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age", 86400); // Cache for 1 day

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

$nic = '';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanning</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background: url('https://www.qr-code-generator.com/wp-content/themes/qr/simple/images/homepages/qr-code-static/background.png') repeat;
            background-size: cover;
            background-color: #f0f0f0;
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
        .qr-scan-button {
            background-color: #1E90FF;
        }
        .qr-scan-button:hover {
            background-color: #1C86EE;
            transform: translateY(-2px);
        }
        .back-home-button {
            background-color: #FFEB3B; /* Yellow background */
            color: black; /* Black text */
        }
        .back-home-button:hover {
            background-color: #FFD700; /* Darker yellow on hover */
            transform: translateY(-2px);
        }
        .start-button, .scanned-button {
            background-color: #228B22; /* Forest Green background */
        }
        .start-button:hover, .scanned-button:hover {
            background-color: #006400; /* Darker Forest Green on hover */
            transform: translateY(-2px);
        }
        .clear-display-button {
            background-color: #FF4500; /* OrangeRed background */
        }
        .clear-display-button:hover {
            background-color: #FF6347; /* Tomato on hover */
            transform: translateY(-2px);
        }
        .button i {
            font-size: 20px;
        }
        .compare-face-button {
            background-color: #4CAF50; /* Green background */
            margin-top: 20px; /* Added margin top */
        }
        .compare-face-button:hover {
            background-color: #45A049; /* Darker green on hover */
            transform: translateY(-2px);
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
            border-bottom: 1px solid #ddd;
            font-weight: bold; /* Make the content bold */
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
        .edit-button {
            background-color: #FFEB3B; /* Yellow background */
            color: black; /* Black text */
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
        }
        .edit-button:hover {
            background-color: #FFD700; /* Darker yellow on hover */
            transform: translateY(-2px);
        }
        .copy-button {
            background-color: #FF0000; /* Red background */
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .copy-button:hover {
            background-color: #CC0000; /* Darker red on hover */
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
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>QR Scanning <span>Search by NIC</span></h1>
        </div>
    </div>
    <div class="button-group">
        <a href="index.php" class="button back-home-button">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <button class="button start-button" onclick="sendStartCommand(this)"><i class="fas fa-play"></i> Start</button>
        <button class="button scanned-button" onclick="sendScannedCommand(this)"><i class="fas fa-check-circle"></i> Scanned</button>
        <button class="button clear-display-button" onclick="sendClearCommand(this)"><i class="fas fa-times-circle"></i> Clear Display</button>
    </div>
    <div class="search-bar">
        <form method="POST" action="" id="nicForm">
            <div class="input-container">
                <input type="text" name="nic" id="nicInput" class="input" placeholder="Enter NIC Number" value="<?php echo htmlspecialchars($nic); ?>" autocomplete="off" maxlength="14">
                <i class="fas fa-search icon"></i>
            </div>
            <ul id="suggestions"></ul>
        </form>
        <button class="button compare-face-button" onclick="compareFaceId()"><i class="fas fa-id-card"></i> Compare Face ID</button>
    </div>
    <div id="countdown" class="countdown"></div>
    <?php if ($error_message): ?>
        <p class="error-message"><?php echo $error_message; ?></p>
    <?php endif; ?>
    <?php if ($userDetails): ?>
        <table>
            <thead>
                <tr>
                    <th>NIC Number</th>
                    <th>QR Code</th>
                    <th>User Name</th>
                    <th>Face ID</th>
                    <th>Fingerprint ID</th>
                    <th>NIC Picture</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($userDetails["NIC_Number"]); ?><br><button class='copy-button' onclick='copyToClipboard("<?php echo htmlspecialchars($userDetails["NIC_Number"]); ?>")'>Copy</button></td>
                    <td class='qrcode-cell' data-nic='<?php echo htmlspecialchars($userDetails["NIC_Number"]); ?>'></td>
                    <td><?php echo nl2br(htmlspecialchars($userDetails["User_Name"])); ?></td>
                    <td class='image-cell'><?php echo $userDetails["Face_ID"] ? "<img src='data:image/jpeg;base64," . base64_encode($userDetails["Face_ID"]) . "'>" : "<div class='empty-cell'></div>"; ?></td>
                    <td><?php echo htmlspecialchars($userDetails["Fingerprint_ID"]); ?></td>
                    <td class='image-cell'><?php echo $userDetails["NIC_Picture"] ? "<img src='data:image/jpeg;base64," . base64_encode($userDetails["NIC_Picture"]) . "'>" : "<div class='empty-cell'></div>"; ?></td>
                </tr>
            </tbody>
        </table>
    <?php elseif ($nic): ?>
        <p>No results found for NIC: <?php echo htmlspecialchars($nic); ?></p>
    <?php endif; ?>
</div>

<div class="modal" id="imageModal">
    <img id="modalImage" src="" alt="Enlarged image">
</div>

<div id="notification"></div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const qrCells = document.querySelectorAll('.qrcode-cell');
        qrCells.forEach(cell => {
            const nic = cell.getAttribute('data-nic');
            if (nic) {
                const qrDiv = document.createElement('div');
                cell.appendChild(qrDiv);
                new QRCode(qrDiv, {
                    text: nic,
                    width: 100,
                    height: 100,
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        });

        const imageCells = document.querySelectorAll('.image-cell img');
        const modal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        const container = document.querySelector('.container');

        imageCells.forEach(img => {
            img.addEventListener('click', () => {
                modalImage.src = img.src;
                modal.classList.add('active');
                container.classList.add('blurred');
            });
        });

        modal.addEventListener('click', () => {
            modal.classList.remove('active');
            container.classList.remove('blurred');
        });

        // AJAX NIC suggestions
        $('#nicInput').on('input', function() {
            const query = $(this).val().substring(0, 14);
            $(this).val(query);
            if (query.length >= 1) {
                $.ajax({
                    url: '',
                    method: 'GET',
                    data: { query: query },
                    success: function(data) {
                        $('#suggestions').html(data).show();
                        // Check if there's an exact match
                        if ($('#suggestions li').length > 0 && $('#suggestions li').first().text() === query) {
                            // Automatically submit the form
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

        // Hide suggestions on clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-bar').length) {
                $('#suggestions').hide();
            }
        });

        // Trigger start button if mode is automatic
        if ("<?php echo $mode; ?>" === "automatic") {
            document.querySelector('.start-button').click();
        }

        // Trigger scanned button if NIC input has value and user details are available
        if ("<?php echo $mode; ?>" === "automatic" && <?php echo $userDetails ? 'true' : 'false'; ?>) {
            setTimeout(() => {
                document.querySelector('.scanned-button').click();
                setTimeout(() => {
                    document.querySelector('.compare-face-button').click();
                }, 2000); // Delay for compare face id button click
            }, 2000); // Delay for scanned button click
        }

        // Set focus to the NIC input field
        document.getElementById('nicInput').focus();
    });

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied to clipboard: ' + text);
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }

    function sendStartCommand(button) {
        if (button.disabled) return;
        button.disabled = true; // Disable the button to prevent multiple clicks
        console.log('Sending start command...'); // Debug log
        const notification = document.getElementById('notification');
        fetch('<?php echo $esp32_ip; ?>/scan', {  // Update the URL with your ESP32's IP address
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: "Scan the QR code" })
        })
        .then(response => {
            console.log('Received response for start command'); // Debug log
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const message = 'Scan command sent to ESP32';
            notification.textContent = message;
            notification.className = 'notification success';
            notification.style.display = 'block';
            speak(message);
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);  // Reduced delay to 2 seconds
            button.disabled = false; // Re-enable the button after the request completes
        })
        .catch(error => {
            const message = 'Failed to send command to ESP32';
            console.error('Error:', error);
            notification.textContent = message;
            notification.className = 'notification error';
            notification.style.display = 'block';
            speak(message);
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);  // Reduced delay to 2 seconds
            button.disabled = false; // Re-enable the button after the request completes
        });
    }

    function sendScannedCommand(button) {
        if (button.disabled) return;
        button.disabled = true; // Disable the button to prevent multiple clicks
        console.log('Sending scanned command...'); // Debug log
        const notification = document.getElementById('notification');
        fetch('<?php echo $esp32_ip; ?>/scanned', {  // Update the URL with your ESP32's IP address
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: "QR code scanned" })
        })
        .then(response => {
            console.log('Received response for scanned command'); // Debug log
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const message = 'Scanned command sent to ESP32';
            notification.textContent = message;
            notification.className = 'notification success';
            notification.style.display = 'block';
            speak(message);
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);  // Reduced delay to 2 seconds
            button.disabled = false; // Re-enable the button after the request completes
        })
        .catch(error => {
            const message = 'Failed to send command to ESP32';
            console.error('Error:', error);
            notification.textContent = message;
            notification.className = 'notification error';
            notification.style.display = 'block';
            speak(message);
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);  // Reduced delay to 2 seconds
            button.disabled = false; // Re-enable the button after the request completes
        });
    }

    function sendClearCommand(button) {
        if (button.disabled) return;
        button.disabled = true; // Disable the button to prevent multiple clicks
        console.log('Sending clear display command...'); // Debug log
        const notification = document.getElementById('notification');
        fetch('<?php echo $esp32_ip; ?>/clear_display', {  // Corrected URL
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: "Clear display" }) // Adjusted message
        })
        .then(response => {
            console.log('Received response for clear display command'); // Debug log
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const message = 'Clear display command sent to ESP32';
            notification.textContent = message;
            notification.className = 'notification success';
            notification.style.display = 'block';
            speak(message);
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);  // Reduced delay to 2 seconds
            button.disabled = false; // Re-enable the button after the request completes
        })
        .catch(error => {
            const message = 'Failed to send command to ESP32';
            console.error('Error:', error);
            notification.textContent = message;
            notification.className = 'notification error';
            notification.style.display = 'block';
            speak(message);
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);  // Reduced delay to 2 seconds
            button.disabled = false; // Re-enable the button after the request completes
        });
    }

    function compareFaceId() {
        const nicInput = document.getElementById('nicInput').value;
        if (nicInput) {
            const notification = document.getElementById('notification');
            const userConfirmed = confirm("Do you want to stay on this page?");
            if (userConfirmed) {
                notification.textContent = 'You chose to stay. Redirecting in 20 seconds...';
                notification.className = 'notification success';
                notification.style.display = 'block';
                speak('You chose to stay. Redirecting in 20 seconds...');
                startCountdown();
            } else {
                window.location.href = `compare_face_id.php?nic=${encodeURIComponent(nicInput)}`;
                notification.textContent = 'Compare Face ID initiated';
                notification.className = 'notification success';
                notification.style.display = 'block';
                speak('Compare Face ID initiated');
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 2000);  // Reduced delay to 2 seconds
            }
        } else {
            alert('Please enter a NIC number.');
        }
    }

    function startCountdown() {
        let countdown = 20;
        const countdownElement = document.getElementById('countdown');
        countdownElement.textContent = `Redirecting in ${countdown} seconds...`;
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                const userConfirmed = confirm("Do you want to stay on this page?");
                if (userConfirmed) {
                    startCountdown();
                } else {
                    const nicInput = document.getElementById('nicInput').value;
                    window.location.href = `compare_face_id.php?nic=${encodeURIComponent(nicInput)}`;
                }
            } else {
                countdownElement.textContent = `Redirecting in ${countdown} seconds...`;
            }
        }, 1000);
    }

    function speak(message) {
        const synth = window.speechSynthesis;
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.pitch = 1.2; // Adjust pitch for style
        utterance.rate = 1.1; // Adjust rate for style

        // Select a voice
        const voices = synth.getVoices();
        const selectedVoice = voices.find(voice => voice.name === 'Google UK English Female');
        if (selectedVoice) {
            utterance.voice = selectedVoice;
        } else {
            const femaleVoices = voices.filter(voice => voice.name.includes('Female') || voice.name.includes('female'));
            if (femaleVoices.length > 0) {
                utterance.voice = femaleVoices[0];
            } else {
                utterance.voice = voices.find(voice => voice.lang === 'en-US'); // Fallback to a default English voice
            }
        }
        synth.speak(utterance);
    }

    window.speechSynthesis.onvoiceschanged = () => {}; // Trigger voice list loading in some browsers
</script>

</body>
</html>
