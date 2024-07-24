<?php
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

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nic = $_POST['nic'];
    $faceID = isset($_POST['faceID']) ? base64_decode($_POST['faceID']) : null;

    $stmt = $conn->prepare("SELECT NIC_Number FROM users WHERE NIC_Number = :nic");
    $stmt->execute(['nic' => $nic]);
    $userExists = $stmt->rowCount() > 0;

    if ($userExists) {
        if ($faceID) {
            $updateStmt = $conn->prepare("UPDATE users SET Face_ID = :faceID WHERE NIC_Number = :nic");
            $updateStmt->execute(['faceID' => $faceID, 'nic' => $nic]);
            $message = "Face ID updated successfully for NIC: $nic";
        }
    } else {
        $message = "User with NIC: $nic does not exist.";
    }
    echo json_encode(['message' => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['term'])) {
    $term = $_GET['term'];
    $stmt = $conn->prepare("SELECT NIC_Number FROM users WHERE NIC_Number LIKE :term");
    $stmt->execute(['term' => $term . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($results);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Face ID</title>
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
            display: none;
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
        .start-stream-button {
            background-color: #388e3c; /* Green */
        }
        .start-stream-button:hover {
            background-color: #2e7d32;
            transform: translateY(-2px);
        }
        .stop-stream-button {
            background-color: #d32f2f; /* Red */
        }
        .stop-stream-button:hover {
            background-color: #b71c1c;
            transform: translateY(-2px);
        }
        .store-button {
            background-color: #5D4037; /* Dark brown */
        }
        .store-button:hover {
            background-color: #4E342E;
            transform: translateY(-2px);
        }
        .resolution-button {
            background-color: #333;
            color: white;
        }
        .resolution-button:hover {
            background-color: #222;
            transform: translateY(-2px);
        }
        #stream {
            width: 100%;
            height: auto;
            background-color: #000;
            display: none;
            margin-top: 20px;
            border-radius: 10px;
            max-width: 100%;
        }
        .slider {
            width: 100%;
            max-width: 300px;
        }
        .hidden {
            display: none;
        }
        .message {
            text-align: center;
            margin-bottom: 20px;
            color: red;
            font-size: 1.2em;
            font-weight: bold;
        }
        .home-button {
            background-color: #FFEB3B;
            color: #333;
            margin-top: 20px;
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
        .home-button:hover {
            background-color: #fbc02d;
            transform: translateY(-2px);
        }
        .control-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .control-section label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>

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
        <div class="control-section">
            <label for="ledIntensitySlider">LED Intensity:</label>
            <input type="range" id="ledIntensitySlider" class="slider" min="0" max="255" value="0">
        </div>
        <div class="control-section hidden" id="resolution-controls">
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
            <button id="setResolution" class="button resolution-button"><i class="fas fa-cog"></i> Set Resolution</button>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Add Face ID</h1>
        </div>
        <div id="message" class="message"></div>
        <div class="input-group">
            <div class="input-container">
                <input list="nicSuggestions" type="text" id="nicInput" class="input" placeholder="Enter NIC Number" autocomplete="new-password">
                <i class="fas fa-search icon"></i>
                <datalist id="nicSuggestions"></datalist>
            </div>
        </div>
        <a href="index.php" class="home-button"><i class="fas fa-home"></i> Go Home</a>
        <div id="stream-container">
            <img id="stream" src="http://192.168.43.234:81/stream" crossorigin>
        </div>
    </div>
</div>

<script>
    let streamStarted = false;

    document.getElementById('nicInput').addEventListener('input', function(event) {
        const nic = event.target.value;
        if (nic.length >= 1) {
            fetch(`?term=${nic}`)
                .then(response => response.json())
                .then(data => {
                    const datalist = document.getElementById('nicSuggestions');
                    datalist.innerHTML = ''; // Clear previous suggestions
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item;
                        datalist.appendChild(option);
                    });
                    if (data.includes(nic)) {
                        document.getElementById('controlPanel').style.display = 'flex';
                    } else {
                        document.getElementById('controlPanel').style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    });

    document.getElementById('startStream').addEventListener('click', function() {
        var stream = document.getElementById('stream');
        stream.style.display = 'block';
        stream.src = 'http://192.168.43.234:81/stream';
        document.getElementById('resolution-controls').classList.remove('hidden');
        streamStarted = true;
    });

    document.getElementById('stopStream').addEventListener('click', function() {
        var stream = document.getElementById('stream');
        stream.style.display = 'none';
        stream.src = '';
        document.getElementById('resolution-controls').classList.add('hidden');
        streamStarted = false;
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
            context.drawImage(img, 0, 0, img.width, img.height);
            var faceID = canvas.toDataURL('image/jpeg').split(',')[1];

            var formData = new FormData();
            formData.append('nic', nic);
            formData.append('faceID', faceID);

            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(data => {
                document.getElementById('message').innerText = data.message;
            }).catch(error => {
                console.error('Error:', error);
            });
        } else {
            alert('Please enter a NIC number to store the Face ID');
        }
    });

    document.getElementById('ledIntensitySlider').addEventListener('change', function() {
        var intensity = document.getElementById('ledIntensitySlider').value;
        fetch('http://192.168.43.234/control?var=led_intensity&val=' + intensity)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });

    document.getElementById('setResolution').addEventListener('click', function() {
        var resolution = document.getElementById('resolution').value;
        fetch('http://192.168.43.234/control?var=framesize&val=' + resolution)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });

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

    document.querySelectorAll('.face-control-button').forEach(button => {
        button.addEventListener('click', function() {
            const command = this.getAttribute('data-command');
            sendInstruction(command);
        });
    });
</script>

</body>
</html>
