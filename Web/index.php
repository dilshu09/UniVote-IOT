<?php
session_start();

// Always reset to manual mode when loading the index page
$_SESSION['mode'] = 'manual';

$servername = "localhost";
$username = "root";
$password = "rothila"; // Actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

// Create connection using PDO for better security
try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$sql = "SELECT NIC_Number, User_Name, Face_ID, Fingerprint_ID, NIC_Picture FROM users";
$stmt = $conn->prepare($sql);
$stmt->execute();

$userCount = $stmt->rowCount();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_mode'])) {
    $_SESSION['mode'] = ($_SESSION['mode'] === 'manual') ? 'automatic' : 'manual';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Data</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container {
            width: 100%;
            max-width: 1400px;
            margin: auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            box-sizing: border-box;
            overflow-x: auto;
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
            justify-content: space-between;
            align-items: center;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
        }
        h1 {
            margin: 0;
            color: #333;
            text-align: center;
            font-size: 2em;
        }
        h1 span {
            font-size: 0.5em;
            color: #666;
        }
        .mode-container {
            display: flex;
            align-items: center;
            margin-left: auto;
        }
        .mode-button {
            color: white;
            padding: 10px;
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
        .mode-button i {
            font-size: 20px; /* Icon size */
        }
        .manual-mode {
            background-color: #006400; /* Dark green for manual mode */
        }
        .automatic-mode {
            background-color: #000000; /* Black for automatic mode */
        }
        .mode-button:hover {
            transform: translateY(-2px);
        }
        .mode-status {
            font-size: 1.2em;
            margin-left: 10px;
            color: #333;
        }
        .countdown {
            font-size: 1.2em;
            margin-left: 10px;
            color: #333;
        }
        .search-bar {
            text-align: center;
            margin-bottom: 20px;
            margin-top: 20px;
        }
        .input-container {
            width: 500px; /* Increased width for the search bar */
            position: relative;
            margin: auto;
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
            font-size: 14px;
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
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
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
        .add-user-button { background-color: #6A1B9A; }
        .add-user-button:hover { background-color: #4A0072; transform: translateY(-2px); }
        .add-face-id-button { background-color: #8B4513; }
        .add-face-id-button:hover { background-color: #5A2E0A; transform: translateY(-2px); }
        .qr-scan-button { background-color: #1E90FF; }
        .qr-scan-button:hover { background-color: #1C86EE; transform: translateY(-2px); }
        .compare-face-id-button { background-color: #006400; } /* Dark green */
        .compare-face-id-button:hover { background-color: #004d00; transform: translateY(-2px); } /* Darker green */
        .view-candidates-button { background-color: #FFA500; }
        .view-candidates-button:hover { background-color: #FF8C00; transform: translateY(-2px); }
        .add-fingerprint-button { background-color: #DC143C; }
        .add-fingerprint-button:hover { background-color: #B22222; transform: translateY(-2px); }
        .time-button { background-color: #FF4500; }
        .time-button:hover { background-color: #CD3700; transform: translateY(-2px); }
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
            overflow-x: auto;
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
            padding: 7px;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
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
        tr:hover td { background-color: #f1f1f1; }
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
        .modal.active { display: flex; }
        .copy-button {
            background-color: #FF0000;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .copy-button:hover { background-color: #CC0000; }
        @media (max-width: 600px) {
            .header-content {
                flex-direction: column;
                align-items: center;
            }
            h1 {
                font-size: 1.5em;
            }
            h1 span {
                font-size: 0.4em;
            }
            .mode-container {
                margin-top: 10px;
            }
            .input-container {
                width: 100%;
            }
            th, td {
                font-size: 12px;
            }
            .image-cell img, .qrcode {
                width: 75px;
                height: 75px;
            }
        }
        .Btn {
            width: 120px;
            height: 45px;
            background-color: rgb(65, 64, 64);
            border: none;
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.342);
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            position: relative;
            overflow: hidden;
            transition-duration: .5s;
            margin: auto;
            text-decoration: none; /* Remove underline */
        }
        .text {
            color: rgb(184, 236, 104);
            font-weight: 800;
            letter-spacing: 1.1px;
            z-index: 2;
        }
        .svgIcon {
            z-index: 2;
        }
        .svgIcon path {
            fill: rgb(184, 236, 104);
        }
        .Btn:hover {
            color: rgb(230, 255, 193);
        }
        .effect {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: rgb(184, 236, 104);
            border-radius: 50%;
            z-index: 1;
            opacity: 0;
            transition-duration: .5s;
        }
        .Btn:hover .effect {
            transform: scale(15);
            transform-origin: center;
            opacity: 1;
            transition-duration: .5s;
        }
        .Btn:hover {
            box-shadow: 0px 0px 5px rgb(184, 236, 104),
            0px 0px 10px rgb(184, 236, 104),
            0px 0px 30px rgb(184, 236, 104);
            transition-duration: .7s;
        }
        .Btn:hover .text {
            color: rgb(65, 64, 64);
        }
        .Btn:hover .svgIcon path {
            fill: rgb(65, 64, 64);
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>User Data <span>(Total Users: <?php echo htmlspecialchars($userCount); ?>)</span></h1>
            <div class="mode-container">
                <form method="post" style="display: flex;">
                    <button type="submit" name="toggle_mode" id="modeButton" class="mode-button <?php echo ($_SESSION['mode'] === 'manual') ? 'manual-mode' : 'automatic-mode'; ?>">
                        <i id="modeIcon" class="fas <?php echo ($_SESSION['mode'] === 'manual') ? 'fa-hand-paper' : 'fa-robot'; ?>"></i>
                    </button>
                </form>
                <span id="modeStatus" class="mode-status"><?php echo ($_SESSION['mode'] === 'manual') ? 'Manual Mode Activated' : 'Automatic Mode Activated'; ?></span>
                <span id="countdown" class="countdown"></span>
            </div>
        </div>
    </div>
    <div class="button-group">
        <a href="new_user.php" class="button add-user-button">
            <i class="fas fa-user-plus"></i> Add User
        </a>
        <a href="add_face_id.php" class="button add-face-id-button">
            <i class="fas fa-camera"></i> Face ID
        </a>
        <a id="qrScanButton" href="qr_scanning_page.php" class="button qr-scan-button">
            <i class="fas fa-qrcode"></i> QR Scan
        </a>
        <a href="compare_face_id.php" class="button compare-face-id-button">
            <i class="fas fa-user-check"></i> Compare Face
        </a>
        <a href="javascript:void(0);" onclick="goToIndex2()" class="button view-candidates-button">
            <i class="fas fa-users"></i> Candidates
        </a>
        <a href="add_fingerprint.php" class="button add-fingerprint-button">
            <i class="fas fa-fingerprint"></i> Fingerprint
        </a>
        <a href="http://localhost/Pin%20Entering/pin_for_start.php" class="button time-button">
    <i class="fas fa-clock"></i> Time
</a>

    </div>
    <div class="search-bar">
        <div class="input-container">
            <input type="text" id="searchInput" class="input" placeholder="Search by NIC Number" autocomplete="off">
            <i class="fas fa-search icon"></i>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>NIC Number</th>
                <th>QR Code</th>
                <th>User Name</th>
                <th>Face ID</th>
                <th>Fingerprint ID</th>
                <th>NIC Picture</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="userTable">
            <?php
            if ($userCount > 0) {
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["NIC_Number"]) . "<br><button class='copy-button' onclick='copyToClipboard(\"" . htmlspecialchars($row["NIC_Number"]) . "\")'>Copy</button></td>";
                    echo "<td class='qrcode-cell' data-nic='" . htmlspecialchars($row["NIC_Number"]) . "'></td>";
                    echo "<td class='username-cell'>" . nl2br(htmlspecialchars($row["User_Name"])) . "</td>";
                    echo "<td class='image-cell'>" . ($row["Face_ID"] ? "<img src='data:image/jpeg;base64," . base64_encode($row["Face_ID"]) . "'>" : "<div class='empty-cell'></div>") . "</td>";
                    echo "<td>" . htmlspecialchars($row["Fingerprint_ID"]) . "</td>";
                    echo "<td class='image-cell'>" . ($row["NIC_Picture"] ? "<img src='data:image/jpeg;base64," . base64_encode($row["NIC_Picture"]) . "'>" : "<div class='empty-cell'></div>") . "</td>";
                    echo "<td><a class='Btn' href='edit.php?nic=" . urlencode($row["NIC_Number"]) . "'>
                            <svg viewBox='0 0 512 512' class='svgIcon' height='1em'>
                                <path d='M288 448H64V224h64V160H64c-35.3 0-64 28.7-64 64V448c0 35.3 28.7 64 64 64H288c35.3 0 64-28.7 64-64V384H288v64zm-64-96H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H224c-35.3 0-64 28.7-64 64V288c0 35.3 28.7 64 64 64z'></path>
                            </svg>
                            <p class='text'>EDIT</p>
                            <span class='effect'></span>
                        </a></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No results found</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<div class="modal" id="imageModal">
    <img id="modalImage" src="" alt="Enlarged image">
</div>

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

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#userTable tr');
            rows.forEach(row => {
                const nicCell = row.querySelector('td:first-child');
                if (nicCell && nicCell.textContent.toLowerCase().includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        const modeButton = document.getElementById('modeButton');
        const modeStatus = document.getElementById('modeStatus');
        const countdownElement = document.getElementById('countdown');
        const qrScanButton = document.getElementById('qrScanButton');
        let isManualMode = "<?php echo $_SESSION['mode']; ?>" === 'manual';  // Default to Manual Mode

        function updateModeButton() {
            if (isManualMode) {
                modeButton.classList.remove('automatic-mode');
                modeButton.classList.add('manual-mode');
                modeButton.innerHTML = '<i id="modeIcon" class="fas fa-hand-paper"></i>';
                modeStatus.textContent = "Manual Mode Activated";
                countdownElement.textContent = "";
                console.log("Manual mode activated");
            } else {
                modeButton.classList.remove('manual-mode');
                modeButton.classList.add('automatic-mode');
                modeButton.innerHTML = '<i id="modeIcon" class="fas fa-robot"></i>';
                modeStatus.textContent = "Automatic Mode Activated";
                startCountdown();
                console.log("Automatic mode activated");
            }
        }

        function startCountdown() {
            let countdown = 3;
            countdownElement.textContent = `Starting QR Scan in ${countdown}...`;
            const countdownInterval = setInterval(() => {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    countdownElement.textContent = "";
                    qrScanButton.click(); // Trigger the QR Scan button click
                } else {
                    countdownElement.textContent = `Starting QR Scan in ${countdown}...`;
                }
            }, 1000);
        }

        updateModeButton(); // Initial update to set the button text and color correctly
    });

    function goToIndex2() {
        window.location.href = 'index2.php';
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied to clipboard: ' + text);
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }
</script>

</body>
</html>
