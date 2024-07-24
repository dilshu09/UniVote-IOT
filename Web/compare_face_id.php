<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "rothila"; // Actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$mode = isset($_SESSION['mode']) ? $_SESSION['mode'] : 'manual';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['nic']) && isset($_POST['liveFace'])) {
        $liveFace = base64_decode($_POST['liveFace']);
        $nic = $_POST['nic'];

        $stmt = $conn->prepare("UPDATE users SET Live_Face = :liveFace WHERE NIC_Number = :nic");
        if ($stmt->execute(['liveFace' => $liveFace, 'nic' => $nic])) {
            echo json_encode(['success' => true]);
        } else {
            $errorInfo = $stmt->errorInfo();
            echo json_encode(['success' => false, 'message' => 'Failed to update Live Face', 'error' => $errorInfo]);
        }
        exit;
    } elseif (isset($_POST['nic'])) {
        $nic = $_POST['nic'];
        $stmt = $conn->prepare("SELECT Face_ID, Live_Face FROM users WHERE NIC_Number = :nic");
        $stmt->execute(['nic' => $nic]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $storedFace = $user['Face_ID'];
            $liveFace = $user['Live_Face'];

            $storedFaceID = $storedFace ? base64_encode($storedFace) : 'No Face ID';
            $liveFace = $liveFace ? base64_encode($liveFace) : 'No Live Face';

            echo json_encode([
                'storedFaceID' => $storedFaceID,
                'liveFace' => $liveFace,
                'success' => true
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['term'])) {
    $term = $_GET['term'];
    $stmt = $conn->prepare("SELECT NIC_Number FROM users WHERE NIC_Number LIKE :term");
    $stmt->execute(['term' => $term . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($results);
    exit;
}

$nic = isset($_GET['nic']) ? htmlspecialchars($_GET['nic']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compare Face ID</title>
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
            align-items: center;
            min-height: 100vh;
            flex-direction: column;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 20px;
        }
        .control-panel {
            width: 300px;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .instruction-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .face-control-button {
            background-color: #333;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
            transition: background-color 0.3s, transform 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .face-control-button i {
            font-size: 24px;
        }
        .face-control-button:hover {
            background-color: #555;
            transform: translateY(-2px);
        }
        .main-content {
            flex-grow: 1;
            background-color: #ffffff;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            box-sizing: border-box;
            text-align: center;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        h1 {
            margin: 0;
            color: #333;
            font-size: 3em;
        }
        .input-group {
            text-align: center;
            margin-bottom: 20px;
        }
        .input-container {
            width: 500px;
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
        .scan-face-id-button { background-color: #1E90FF; }
        .scan-face-id-button:hover { background-color: #1C86EE; transform: translateY(-2px); }
        .clear-face-id-button { background-color: #FF4500; }
        .clear-face-id-button:hover { background-color: #FF0000; transform: translateY(-2px); }
        .compare-fingerprints-button { background-color: #5D4037; }
        .compare-fingerprints-button:hover { background-color: #4E342E; transform: translateY(-2px); }
        .start-stream-button { background-color: #388e3c; }
        .start-stream-button:hover { background-color: #2e7d32; transform: translateY(-2px); }
        .stop-stream-button { background-color: #d32f2f; }
        .stop-stream-button:hover { background-color: #b71c1c; transform: translateY(-2px); }
        .store-button { background-color: #5D4037; }
        .store-button:hover { background-color: #4E342E; transform: translateY(-2px); }
        .home-button {
            background-color: #FFEB3B;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
        }
        .home-button:hover { background-color: #fbc02d; transform: translateY(-2px); }
        .face-id-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            table-layout: fixed;
        }
        .face-id-table th, .face-id-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        .face-id-table th {
            background-color: #4A0010;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .face-id-table tr:nth-child(even) { background-color: #f2f2f2; }
        .face-id-table tr:hover { background-color: #ddd; }
        .face-id-table th, .face-id-table td {
            width: 50%;
        }
        .confirm-button {
            background-color: #0D47A1;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 5px #999;
            transition: background-color 0.3s, transform 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 200px;
            margin-top: 20px;
        }
        .confirm-button:hover {
            background-color: #0B3A77;
            transform: translateY(-2px);
        }
        .confirm-button:active {
            background-color: #0B3A77;
            box-shadow: 0 2px #666;
            transform: translateY(4px);
        }
        .confirm-button .fas {
            margin-right: 10px;
        }
        .resolution-button { background-color: #000; color: white; }
        .resolution-button:hover { background-color: #333; transform: translateY(-2px); }
        .progress-bar-3d {
            position: relative;
            width: 50%;
            height: 40px;
            background: #e0e0e0;
            border-radius: 20px;
            box-shadow: inset 5px 5px 10px #aaa, inset -5px -5px 10px #fff;
            margin: 20px auto;
            overflow: hidden;
        }
        .progress-inner {
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(145deg, #3498db, #2980b9);
            border-radius: 20px;
            box-shadow: inset 5px 5px 10px #2980b9, inset -5px -5px 10px #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 2s ease;
        }
        .progress-inner span {
            position: relative;
            margin-left: 40px;
            font-size: 1.2em;
            color: #fff;
            font-weight: bold;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: -300px;
            width: 250px;
            padding: 20px;
            background-color: #44c767;
            color: white;
            text-align: center;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: right 0.5s ease-in-out;
            z-index: 1000;
        }
        .notification.failed { background-color: #d9534f; }
        .show { right: 20px; }
        .progress-container { display: flex; flex-direction: column; align-items: center; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.9);
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        @media only screen and (max-width: 700px) {
            .modal-content { width: 100%; }
        }
        #stream-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 400px;
            height: 400px;
            margin: 20px auto;
            position: relative;
        }
        #stream-container.hidden {
            display: none;
        }
        #stream {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform-origin: center center;
        }
        .control-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .countdown {
            font-size: 2em;
            margin-top: 20px;
            color: #FF4500;
            font-weight: bold;
        }
        .rotate-button {
            background-color: #ffa500;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rotate-button:hover {
            background-color: #ff8c00;
            transform: translateY(-2px) rotate(360deg);
        }
        .rotate-button i {
            font-size: 24px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body data-mode="<?php echo htmlspecialchars($mode); ?>">

<div class="container">
    <div class="control-panel" id="controlPanel">
        <div class="instruction-box">
            <button class="face-control-button" onclick="sendInstruction('Face Up')"><i class="fas fa-arrow-up"></i> Face Up</button>
            <button class="face-control-button" onclick="sendInstruction('Face Down')"><i class="fas fa-arrow-down"></i> Face Down</button>
            <button class="face-control-button" onclick="sendInstruction('Move Left')"><i class="fas fa-arrow-left"></i> Move Left</button>
            <button class="face-control-button" onclick="sendInstruction('Move Right')"><i class="fas fa-arrow-right"></i> Move Right</button>
        </div>
        <div class="control-section">
            <button class="button start-stream-button" id="startStream"><i class="fas fa-play"></i> Start Stream</button>
            <button class="button stop-stream-button" id="stopStream"><i class="fas fa-stop"></i> Stop Stream</button>
        </div>
        <div class="control-section">
            <button class="button store-button" id="storeFaceID"><i class="fas fa-save"></i> Store Face ID</button>
        </div>
        <div class="control-section hidden" id="ledIntensityControl">
            <label for="ledIntensitySlider">LED Intensity:</label>
            <input type="range" id="ledIntensitySlider" class="slider" min="0" max="255" value="0">
        </div>
        <div class="control-section hidden" id="resolutionControl">
            <label for="resolution">Resolution:</label>
            <select id="resolution" class="button resolution-button">
                <option value="13">UXGA(1600x1200)</option>
                <option value="12">SXGA(1280x1024)</option>
                <option value="11">HD(1280x720)</option>
                <option value="10">XGA(1024x768)</option>
                <option value="9">SVGA(800x600)</option>
                <option value="8">VGA(640x480)</option>
                <option value="7">HVGA(480x320)</option>
                <option value="6">CIF(400x296)</option>
                <option value="5">QVGA(320x240)</option>
                <option value="4">240x240</option>
                <option value="3">HQVGA(240x176)</option>
                <option value="2">QCIF(176x144)</option>
                <option value="1">QQVGA(160x120)</option>
                <option value="0">96x96</option>
            </select>
            <button id="setResolution" class="button resolution-button">Set Resolution</button>
        </div>
        <div class="control-section">
            <button class="rotate-button" id="rotateStream"><i class="fas fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Compare Face ID</h1>
        </div>
        <div id="message" class="message"></div>
        <div class="input-group">
            <div class="input-container">
                <input list="nicSuggestions" type="text" id="nicInput" class="input" placeholder="Enter NIC Number" value="<?php echo $nic; ?>">
                <i class="fas fa-search icon"></i>
                <datalist id="nicSuggestions"></datalist>
            </div>
        </div>
        <a href="index.php" class="home-button"><i class="fas fa-home"></i> Go Home</a>
        <div class="button-group">
            <button id="scanFaceIDButton" class="button scan-face-id-button" onclick="sendScanFaceIDCommand(this)"><i class="fas fa-id-card"></i> Scan the Face ID</button>
            <button class="button clear-face-id-button" onclick="sendClearFaceIDCommand(this)"><i class="fas fa-eraser"></i> Clear Face ID</button>
            <a href="#" id="compareFingerprintsButton" class="button compare-fingerprints-button"><i class="fas fa-fingerprint"></i> Compare Fingerprints</a>
        </div>
        <div id="stream-container" class="hidden">
            <img id="stream" src="" crossorigin>
        </div>
        <table class="face-id-table">
            <thead>
                <tr>
                    <th>Stored Face ID</th>
                    <th>Live Face</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td id="storedFaceID">No Face ID</td>
                    <td id="liveFace">No Live Face</td>
                </tr>
            </tbody>
        </table>
        <button id="confirmFaceIDs" class="confirm-button"><i class="fas fa-exchange-alt"></i> Confirm</button>
        <div class="progress-container">
            <div class="progress-bar-3d" id="progress-bar-3d">
                <div class="progress-inner" id="progress-inner">
                    <span id="percentage-text">0%</span>
                </div>
            </div>
        </div>
        <div class="countdown" id="countdown" style="display: none;">60</div>
    </div>
    <div id="notification" class="notification">Matched!</div>
    <div id="comparingNotification" class="notification">Comparing...</div>
</div>

<div id="myModal" class="modal">
  <span class="close">&times;</span>
  <img class="modal-content" id="img01">
</div>

<script>function speak(message) {
    if ('speechSynthesis' in window) {
        const synth = window.speechSynthesis;
        let voices = [];

        const getVoices = () => {
            voices = synth.getVoices().filter(voice => voice.name.includes('Female') || voice.gender === 'female');
            return voices.length ? voices[0] : null;
        };

        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = 'en-US';
        utterance.pitch = 1.2; // Higher pitch for female voice
        utterance.rate = 1; // Normal speaking rate

        const voice = getVoices();
        if (voice) {
            utterance.voice = voice;
            synth.speak(utterance);
        } else {
            synth.onvoiceschanged = () => {
                const voice = getVoices();
                if (voice) {
                    utterance.voice = voice;
                    synth.speak(utterance);
                }
            };
        }
    } else {
        console.warn("Speech synthesis not supported");
    }
}

let streamStarted = false;
let rotationAngle = 0;

window.onload = function() {
    document.getElementById('storedFaceID').innerText = 'No Face ID';
    document.getElementById('liveFace').innerText = 'No Live Face';

    const mode = document.body.getAttribute('data-mode');
    if (mode === 'automatic') {
        startStream();
    }

    const urlParams = new URLSearchParams(window.location.search);
    const nic = urlParams.get('nic');
    if (nic) {
        fetchUserDetails(nic);
    }
};

function fetchUserDetails(nic) {
    if (nic) {
        var formData = new FormData();
        formData.append('nic', nic);

        fetch('compare_face_id.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json()).then(data => {
            if (data.success) {
                if (data.storedFaceID && data.storedFaceID !== 'No Face ID') {
                    document.getElementById('storedFaceID').innerHTML = '<img src="data:image/jpeg;base64,' + data.storedFaceID + '" width="100" height="100">';
                } else {
                    document.getElementById('storedFaceID').innerText = 'No Face ID';
                }

                if (data.liveFace && data.liveFace !== 'No Live Face') {
                    document.getElementById('liveFace').innerHTML = '<img src="data:image/jpeg;base64,' + data.liveFace + '" width="100" height="100">';
                } else {
                    document.getElementById('liveFace').innerText = 'No Live Face';
                }

                document.getElementById('controlPanel').style.display = 'flex';

                if (data.storedFaceID && data.liveFace) {
                    document.getElementById('confirmFaceIDs').style.display = 'block';
                } else {
                    document.getElementById('confirmFaceIDs').style.display = 'none';
                }

                document.getElementById('scanFaceIDButton').click();
            } else {
                alert('Invalid NIC. User not found.');
            }
        }).catch(error => {
            console.error('Error:', error);
        });
    } else {
        alert('Please enter a NIC number');
    }
}

document.getElementById('nicInput').addEventListener('input', function(event) {
    const nic = event.target.value;
    if (nic.length >= 1) {
        fetch(`compare_face_id.php?term=${nic}`)
            .then(response => response.json())
            .then(data => {
                const datalist = document.getElementById('nicSuggestions');
                datalist.innerHTML = '';
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item;
                    datalist.appendChild(option);
                });
                if (data.includes(nic)) {
                    fetchUserDetails(nic);
                }
            })
            .catch(error => console.error('Error:', error));
    }
});

function startStream() {
    var stream = document.getElementById('stream');
    var streamContainer = document.getElementById('stream-container');
    streamContainer.classList.remove('hidden');
    stream.style.display = 'block';
    stream.src = 'http://192.168.43.234:81/stream';

    stream.onload = function() {
        console.log("Stream started successfully");
    };

    stream.onerror = function() {
        console.error("Failed to load the stream. Please check the IP address and port.");
        alert("Failed to start the stream. Please check the IP address and port.");
    };

    streamStarted = true;
    document.getElementById('controlPanel').style.display = 'flex';
    document.getElementById('ledIntensityControl').classList.remove('hidden');
    document.getElementById('resolutionControl').classList.remove('hidden');
}

document.getElementById('startStream').addEventListener('click', startStream);

document.getElementById('stopStream').addEventListener('click', function() {
    var stream = document.getElementById('stream');
    stream.style.display = 'none';
    stream.removeAttribute('src');
    streamStarted = false;
    document.getElementById('ledIntensityControl').classList.add('hidden');
    document.getElementById('resolutionControl').classList.add('hidden');
});

document.getElementById('storeFaceID').addEventListener('click', function() {
    if (!streamStarted) {
        document.getElementById('message').innerText = 'Stream is not started. Please start the stream first.';
        return;
    }

    var nic = document.getElementById('nicInput').value;
    if (nic) {
        var canvas = document.createElement("canvas");
        var context = canvas.getContext('2d');
        var img = document.getElementById('stream');

        canvas.width = img.width;
        canvas.height = img.height;

        if (rotationAngle !== 0) {
            context.save();
            context.translate(canvas.width / 2, canvas.height / 2);
            context.rotate(rotationAngle * Math.PI / 180);
            context.drawImage(img, -img.width / 2, -img.height / 2, img.width, img.height);
            context.restore();
        } else {
            context.drawImage(img, 0, 0, img.width, img.height);
        }

        var faceID = canvas.toDataURL('image/jpeg').split(',')[1];

        var formData = new FormData();
        formData.append('nic', nic);
        formData.append('liveFace', faceID);

        fetch('compare_face_id.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json()).then(data => {
            if (data.success) {
                document.getElementById('liveFace').innerHTML = '<img src="data:image/jpeg;base64,' + faceID + '" width="100" height="100">';
                document.getElementById('message').innerText = 'Live Face updated successfully.';
                speak('Live Face updated successfully.');

                const mode = document.body.getAttribute('data-mode');
                if (mode === 'automatic') {
                    showNotification('Comparing...');
                    speak('Comparing...');
                    document.getElementById('confirmFaceIDs').click();
                }
            } else {
                document.getElementById('message').innerText = 'Failed to update Live Face.';
                speak('Failed to update Live Face.');
                console.error('Error:', data.message, data.error);
            }

            if (document.getElementById('storedFaceID').querySelector('img') && document.getElementById('liveFace').querySelector('img')) {
                document.getElementById('confirmFaceIDs').style.display = 'block';
            } else {
                document.getElementById('confirmFaceIDs').style.display = 'none';
            }
        }).catch(error => {
            console.error('Error:', error);
        });

        document.getElementById('stream').style.display = 'none';
        document.getElementById('controlPanel').style.display = 'none';
        streamStarted = false;
    } else {
        alert('Please enter a NIC number to store the Face ID');
        speak('Please enter a NIC number to store the Face ID');
    }
});

document.getElementById('ledIntensitySlider').addEventListener('change', function() {
    var intensity = document.getElementById('ledIntensitySlider').value;
    fetch('http://192.168.43.234/control?var=led_intensity&val=' + intensity);
});

document.getElementById('setResolution').addEventListener('click', function() {
    var resolution = document.getElementById('resolution').value;
    fetch('http://192.168.43.234/control?var=framesize&val=' + resolution)
        .then(response => {
            if (response.ok) {
                alert("Resolution changed successfully");
            } else {
                alert("Failed to change resolution");
            }
        })
        .catch(error => console.error('Error:', error));
});

document.getElementById('confirmFaceIDs').addEventListener('click', function() {
    showNotification('Comparing...');
    speak('Comparing...');

    var storedFace = document.getElementById('storedFaceID').querySelector('img').src.split(',')[1];
    var liveFace = document.getElementById('liveFace').querySelector('img').src.split(',')[1];

    if (storedFace && liveFace) {
        var formData = new FormData();
        formData.append('storedFace', storedFace);
        formData.append('liveFace', liveFace);

        var progressInner = document.getElementById('progress-inner');
        var percentageText = document.getElementById('percentage-text');
        var notification = document.getElementById('notification');

        fetch('compare_faces.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json()).then(data => {
            hideNotification('comparingNotification');

            let matchPercentage = data.match_percentage || 0;

            progressInner.style.width = matchPercentage + '%';
            percentageText.innerText = matchPercentage.toFixed(2) + '%';

            if (data.success) {
                if (matchPercentage >= 70) {
                    notification.innerText = 'Matched!';
                    notification.classList.remove('failed');
                    speak('Matched!');
                } else {
                    notification.innerText = 'Not Matched!';
                    notification.classList.add('failed');
                    speak('Not Matched!');
                }

                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 3000);

                setTimeout(() => {
                    confirmStayOrGo();
                }, 3000);
            } else {
                notification.innerText = data.message;
                notification.classList.add('failed');
                speak(data.message);
                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                    confirmStayOrGo();
                }, 3000);
            }
        }).catch(error => {
            console.error('Error:', error);
            alert('Face comparison failed.');
            speak('Face comparison failed.');
            confirmStayOrGo();
        });
    } else {
        hideNotification('comparingNotification');
        alert('Both face images must be available for comparison.');
        speak('Both face images must be available for comparison.');
    }
});

var modal = document.getElementById("myModal");
var modalImg = document.getElementById("img01");

document.addEventListener('click', function(event) {
    if (event.target.tagName === 'IMG' && event.target.closest('.face-id-table')) {
        modal.style.display = "block";
        modalImg.src = event.target.src;
    }
});

var span = document.getElementsByClassName("close")[0];
span.onclick = function() { 
    modal.style.display = "none";
}

document.getElementById('rotateStream').addEventListener('click', function() {
    const stream = document.getElementById('stream');
    rotationAngle = (rotationAngle + 90) % 360;
    stream.style.transform = `rotate(${rotationAngle}deg)`;
});

function showNotification(message) {
    const notification = document.getElementById('comparingNotification');
    notification.innerText = message;
    notification.classList.add('show');
    speak(message);
}

function hideNotification(id) {
    const notification = document.getElementById(id);
    notification.classList.remove('show');
}

function confirmStayOrGo() {
    const userResponse = confirm("Face ID comparison completed. Do you want to stay for 1 minute or proceed to the next step?");
    if (userResponse) {
        startCountdown();
    } else {
        window.location.href = 'add_fingerprint.php?nic=' + document.getElementById('nicInput').value;
    }
}

function startCountdown() {
    const countdownElement = document.getElementById('countdown');
    countdownElement.style.display = 'block';
    let timeLeft = 60;

    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            alert("You stayed for 1 minute. Now proceeding to the next step.");
            speak("You stayed for 1 minute. Now proceeding to the next step.");
            window.location.href = 'add_fingerprint.php?nic=' + document.getElementById('nicInput').value;
        } else {
            countdownElement.innerText = timeLeft;
            timeLeft -= 1;
        }
    }, 1000);
}

document.getElementById('compareFingerprintsButton').addEventListener('click', function() {
    const nic = document.getElementById('nicInput').value;
    window.location.href = 'add_fingerprint.php?nic=' + nic;
});

document.getElementById('rotateStream').addEventListener('click', function() {
    const stream = document.getElementById('stream');
    rotationAngle = (rotationAngle + 90) % 360;
    stream.style.transform = `rotate(${rotationAngle}deg)`;
});

function sendScanFaceIDCommand(button) {
    if (button.disabled) return;
    button.disabled = true;
    console.log('Sending scan face ID command...');
    const notification = document.getElementById('notification');
    fetch('http://192.168.43.211/scan_face_id', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: "Scan the Face ID" })
    })
    .then(response => {
        console.log('Received response for scan face ID command');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        notification.textContent = 'Scan Face ID command sent to ESP32';
        notification.className = 'notification success';
        notification.style.display = 'block';
        speak('Scan Face ID command sent to ESP32');
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        notification.textContent = 'Failed to send command to ESP32';
        notification.className = 'notification error';
        notification.style.display = 'block';
        speak('Failed to send command to ESP32');
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
        button.disabled = false;
    });
}

function sendClearFaceIDCommand(button) {
    if (button.disabled) return;
    button.disabled = true;
    console.log('Sending clear face ID command...');
    const notification = document.getElementById('notification');
    fetch('http://192.168.43.211/clear_face_id', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: "Clear Face ID" })
    })
    .then(response => {
        console.log('Received response for clear face ID command');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        notification.textContent = 'Clear Face ID command sent to ESP32';
        notification.className = 'notification success';
        notification.style.display = 'block';
        speak('Clear Face ID command sent to ESP32');
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        notification.textContent = 'Failed to send command to ESP32';
        notification.className = 'notification error';
        notification.style.display = 'block';
        speak('Failed to send command to ESP32');
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
        button.disabled = false;
    });
}

function sendInstruction(command) {
    const notification = document.getElementById('notification');
    fetch('http://192.168.43.211/' + command.replace(/\s/g, '_').toLowerCase(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: command })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        notification.textContent = command + ' command sent to ESP32';
        notification.className = 'notification success';
        notification.style.display = 'block';
        speak(command + ' command sent to ESP32');
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    })
    .catch(error => {
        console.error('Error:', error);
        notification.textContent = 'Failed to send ' + command + ' command to ESP32';
        notification.className = 'notification error';
        notification.style.display = 'block';
        speak('Failed to send ' + command + ' command to ESP32');
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    });
}

</script>

</body>
</html>
