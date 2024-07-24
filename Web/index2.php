<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "rothila"; // Your actual password
$dbname = "hardware_project";
$port = 3307; // Updated port number

// Create connection using PDO for better security
try {
    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if the action is check_votes
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_votes') {
    $sql = "SELECT Candidate_id, Vote_Count FROM candidates";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log the response
    error_log('check_votes action called');
    error_log(json_encode($candidates));

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($candidates);
    exit();
}

$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']); // Clear the notification after fetching
}

$from_fingerprint = isset($_SESSION['from_fingerprint']) ? $_SESSION['from_fingerprint'] : false;
if ($from_fingerprint) {
    unset($_SESSION['from_fingerprint']); // Clear the variable after checking
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates Management</title>
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
            font-size: 2em;
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
        .start-voting-button {
            background-color: #4caf50; /* Green color */
        }
        .start-voting-button:hover {
            background-color: #388e3c; /* Darker green on hover */
            transform: translateY(-2px);
        }
        .disable-voting-button {
            background-color: #FF5733;
        }
        .disable-voting-button:hover {
            background-color: #C70039;
            transform: translateY(-2px);
        }
        .go-home-button {
            background-color: #FFEB3B; /* Yellow color */
            color: #333; /* Dark text for better contrast */
        }
        .go-home-button:hover {
            background-color: #FDD835; /* Darker yellow on hover */
            transform: translateY(-2px);
        }
        .button i {
            font-size: 20px;
        }
        .notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 25px;
            background-color: #4caf50; /* Green */
            color: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translate3d(100%, 0, 0);
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .notification.show {
            display: block;
            transform: translate3d(0, 0, 0);
            opacity: 1;
        }
        .notification.error {
            background-color: #f44336; /* Red */
        }
        .status-indicator {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .status-indicator.voting {
            background-color: #ffeb3b; /* Yellow */
            color: #333; /* Dark text for better contrast */
        }
        .status-indicator.not-voting {
            background-color: #f44336; /* Red */
            color: white;
        }
        .status-indicator.vote-success {
            background-color: #4caf50; /* Green */
            color: white;
        }
    </style>
    <script>
        let previousVoteCounts = {};

        function startVoting() {
            fetch('http://192.168.43.211/start-voting')
                .then(response => response.json())
                .then(data => {
                    console.log('Voting started:', data);
                    showNotification('Voting started: ' + data.message, 'success');
                    updateStatusIndicator('Voting in Process', 'voting');
                })
                .catch((error) => {
                    console.error('Error:', error);
                    showNotification('Error starting voting', 'error');
                });
        }

        function disableVoting() {
            fetch('http://192.168.43.211/disable-voting')
                .then(response => response.json())
                .then(data => {
                    console.log('Voting disabled:', data);
                    showNotification('Voting disabled: ' + data.message, 'success');
                    updateStatusIndicator('Voting disabled', 'not-voting');
                })
                .catch((error) => {
                    console.error('Error:', error);
                    showNotification('Error disabling voting', 'error');
                });
        }

        function checkVoteCounts() {
            fetch('index2.php?action=check_votes')
                .then(response => {
                    console.log('Response:', response);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text(); // Temporarily change to text to see raw response
                })
                .then(data => {
                    console.log('Fetched data (raw text):', data); // Log raw text
                    try {
                        const jsonData = JSON.parse(data); // Parse the JSON
                        console.log('Parsed JSON data:', jsonData); // Log parsed JSON
                        let voteChanged = false;
                        jsonData.forEach(candidate => {
                            const id = candidate.Candidate_id;
                            const newVoteCount = candidate.Vote_Count;

                            if (previousVoteCounts[id] !== undefined && previousVoteCounts[id] !== newVoteCount) {
                                voteChanged = true;
                                console.log('Vote count changed for candidate ID:', id); // Debugging output
                            }
                            previousVoteCounts[id] = newVoteCount;
                        });

                        if (voteChanged) {
                            showNotification('Vote Succeeded', 'vote-success');
                            updateStatusIndicator('Vote Success', 'vote-success');
                        }
                    } catch (error) {
                        console.error('Error parsing JSON:', error);
                        showNotification('Failed to parse vote counts', 'error');
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                    showNotification('Failed to check vote counts', 'error');
                });
        }

        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification show';
            if (type === 'error') {
                notification.classList.add('error');
            } else if (type === 'vote-success') {
                notification.classList.add('vote-success');
            } else {
                notification.classList.remove('error');
                notification.classList.remove('vote-success');
            }
            setTimeout(() => {
                notification.className = 'notification';
            }, 3000);
        }

        function updateStatusIndicator(message, status) {
            const statusIndicator = document.getElementById('status-indicator');
            statusIndicator.textContent = message;
            if (status === 'voting') {
                statusIndicator.className = 'status-indicator voting';
            } else if (status === 'vote-success') {
                statusIndicator.className = 'status-indicator vote-success';
            } else {
                statusIndicator.className = 'status-indicator not-voting';
            }
        }

        // Check for server-side notification
        document.addEventListener('DOMContentLoaded', () => {
            <?php if ($notification): ?>
            showNotification("<?php echo $notification['message']; ?>", "<?php echo $notification['status'] ? 'success' : 'error'; ?>");
            <?php endif; ?>

            // Automatically trigger start voting if in automatic mode
            if ("<?php echo $_SESSION['mode']; ?>" === 'automatic') {
                startVoting();
            }

            // Automatically trigger start voting if coming from the fingerprint page
            if ("<?php echo $from_fingerprint; ?>" === '1') {
                startVoting();
            }

            // Start polling for vote counts
            setInterval(checkVoteCounts, 5000); // Check every 5 seconds
        });
    </script>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <h1>Candidates Management</h1>
        </div>
    </div>
    <div class="status-indicator" id="status-indicator">Not Voting</div>
    <div class="button-group">
        <a href="javascript:void(0);" class="button start-voting-button" onclick="startVoting()">
            <i class="fas fa-play"></i> Start Voting
        </a>
        <a href="javascript:void(0);" class="button disable-voting-button" onclick="disableVoting()">
            <i class="fas fa-stop"></i> Disable Voting
        </a>
        <a href="index.php" class="button go-home-button">
            <i class="fas fa-home"></i> Go Home
        </a>
    </div>
</div>
<div id="notification" class="notification"></div>
<?php if ($notification): ?>
<script>
    showNotification("<?php echo $notification['message']; ?>", "<?php echo $notification['status'] ? 'success' : 'error'; ?>");
</script>
<?php endif; ?>
</body>
</html>
